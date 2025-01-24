<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin;

use Composer\Command\BaseCommand;
use Composer\IO\IOInterface;
use Exception;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Aws\Exception\AwsException;
use Pj8\AwsAssumeRolePlugin\Exception\CredentialsException;
use Symfony\Component\Process\Exception\ProcessFailedException;

final class AssumeRoleCommand extends BaseCommand
{
    public function __construct(
        private readonly StsClientFactoryInterface $stsClientFactory,
        private readonly ProcessFactoryInterface $processFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('assume-role')
            ->setDescription('Assume an AWS IAM role with optional MFA and execute a command or export environment variables.')
            ->addOption(
                'aws-profile',
                null,
                InputOption::VALUE_REQUIRED,
                'The AWS CLI profile to use for retrieving RoleArn and MFA Serial.'
            )
            ->addOption(
                'command',
                null,
                InputOption::VALUE_REQUIRED,
                'The command to execute after assuming the role.'
            )
            ->addOption(
                'code',
                null,
                InputOption::VALUE_REQUIRED,
                'The MFA code to use for authentication (if required).'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getOption('aws-profile');
        $command = $input->getOption('command');
        $mfaCode = $input->getOption('code');
        $io = $this->getIO();
        assert($io !== null);

        try {
            $config = $profile ? $this->getProfileConfiguration($profile) : $this->promptForConfig($io);
            $credentials = $this->assumeRole($config, $profile, $io, $mfaCode);

            if ($command) {
                $env = $this->prepareEnvironment($credentials);
                $this->runGeneralCommand($command, $env, $output);

                return 0;
            }

            $envExports = $this->prepareEnvExports($credentials);
            $output->writeln($envExports);
        } catch (CredentialsException $e) {
            $this->handleCredentialsException($e, $io);

            return 1;
        } catch (AwsException $e) {
            $this->handleAwsException($e, $io);

            return 1;
        } catch (ProcessFailedException $e) {
            $io->writeError(sprintf('<error>Command Execution Failed: %s</error>', $e->getMessage()));

            return 1;
        } catch (Exception $e) {
            $io->writeError(sprintf('<error>Error: %s</error>', $e->getMessage()));

            return 1;
        }

        return 0;
    }

    /**
     * @param array{RoleArn: string, MfaSerial: string, region: string} $config
     * @return array{AccessKeyId: string, SecretAccessKey: string, SessionToken: string}
     *
     * @throws AwsException
     * @throws CredentialsException
     */
    private function assumeRole(array $config, string|null $profile, IOInterface $io, string|null $mfaCode): array
    {
        $stsClientConfig = [
            'version' => '2011-06-15',
            'region'  => $config['region'],
        ];

        if ($profile !== null) {
            $stsClientConfig['profile'] = $profile;
        }

        $stsClient = $this->stsClientFactory->create($stsClientConfig);
        try {
            $assumeRoleParams = [
                'RoleArn'         => $config['RoleArn'],
                'RoleSessionName' => 'ComposerSession_' . time(),
            ];

            $assumeRoleParams['SerialNumber'] = $config['MfaSerial'];
            $assumeRoleParams['TokenCode'] = $mfaCode ?? $this->promptForMfaToken($io);
            $result = $stsClient->assumeRole($assumeRoleParams);

            return [
                'AccessKeyId'     => $result['Credentials']['AccessKeyId'],
                'SecretAccessKey' => $result['Credentials']['SecretAccessKey'],
                'SessionToken'    => $result['Credentials']['SessionToken'],
            ];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'CredentialsError') {
                throw new CredentialsException('Failed to retrieve credentials.', 0, $e);
            }

            throw $e;
        }
    }

    /** @param array{AccessKeyId: string, SecretAccessKey: string, SessionToken: string} $credentials */
    private function prepareEnvExports(array $credentials): string
    {
        return sprintf(
            "export AWS_ACCESS_KEY_ID=%s\nexport AWS_SECRET_ACCESS_KEY=%s\nexport AWS_SESSION_TOKEN=%s",
            escapeshellarg($credentials['AccessKeyId']),
            escapeshellarg($credentials['SecretAccessKey']),
            escapeshellarg($credentials['SessionToken'])
        );
    }

    /**
     * @param array<string, string> $env
     *
     * @throws ProcessFailedException
     */
    private function runGeneralCommand(string $command, array $env, OutputInterface $output): void
    {
        $process = $this->processFactory->create($command);
        $process->setEnv($env);
        $process->setTimeout(null);

        $process->run(function ($type, $buffer) use ($output): void {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * @param array{AccessKeyId: string, SecretAccessKey: string, SessionToken: string} $credentials
     * @return array<string, string>
     */
    private function prepareEnvironment(array $credentials): array
    {
        $existingEnv = $_ENV;

        foreach ($_SERVER as $key => $value) {
            if (!isset($existingEnv[$key])) {
                $existingEnv[$key] = $value;
            }
        }

        return array_merge($existingEnv, [
            'AWS_ACCESS_KEY_ID'     => $credentials['AccessKeyId'],
            'AWS_SECRET_ACCESS_KEY' => $credentials['SecretAccessKey'],
            'AWS_SESSION_TOKEN'     => $credentials['SessionToken'],
        ]);
    }

    /** @return array{RoleArn: string, MfaSerial?: string, region: string} */
    private function promptForConfig(IOInterface $io): array
    {
        $io->write('<comment>No profile specified. Please enter details manually.</comment>');

        $roleArn = $io->ask('Enter the Role ARN: ');
        $mfaSerial = $io->ask('Enter your MFA Device ARN (leave blank if not using MFA): ');
        $region = $io->ask('Enter the AWS region [us-east-1]: ', 'us-east-1');

        $config = [
            'RoleArn'   => $roleArn,
            'region'    => $region,
        ];

        if (!empty($mfaSerial)) {
            $config['MfaSerial'] = $mfaSerial;
        }

        return $config;
    }

    /**
     * @return array{RoleArn: string, MfaSerial: string, region: string}
     *
     * @throws RuntimeException
     */
    private function getProfileConfiguration(string $profile): array
    {
        $homeDir = getenv('HOME') ?: getenv('USERPROFILE');
        $configFile = $homeDir . '/.aws/config';

        if (!file_exists($configFile)) {
            throw new RuntimeException("AWS config file not found at {$configFile}");
        }

        $config = parse_ini_file($configFile, true);
        $profileKey = "profile {$profile}";

        if (!isset($config[$profileKey])) {
            throw new RuntimeException("Profile '{$profile}' not found in AWS config file.");
        }

        $profileConfig = $config[$profileKey];

        if (empty($profileConfig['role_arn'])) {
            throw new RuntimeException("Profile '{$profile}' must have 'role_arn' defined.");
        }

        if (empty($profileConfig['mfa_serial'])) {
            throw new RuntimeException("Profile '{$profile}' must have 'mfa_serial' defined.");
        }

        return [
            'RoleArn'   => $profileConfig['role_arn'],
            'region'    => $profileConfig['region'] ?? 'us-east-1',
            'MfaSerial' => $profileConfig['mfa_serial'],
        ];
    }

    private function promptForMfaToken(IOInterface $io): string
    {
        return $io->ask('Enter your AWS MFA token: ');
    }

    private function handleAwsException(AwsException $e, IOInterface $io): void
    {
        $errorCode = $e->getAwsErrorCode();
        $errorMessage = $e->getAwsErrorMessage();

        if (str_contains($errorMessage, 'cURL error')) {
            $io->writeError('<error>Network error while retrieving AWS credentials.</error>');
            $io->writeError(sprintf('<error>%s</error>', $errorMessage));
            $io->writeError('<comment>Please check your network connectivity and AWS SDK configuration.</comment>');

            return;
        }

        if (in_array($errorCode, ['InvalidClientTokenId', 'SignatureDoesNotMatch'], true)) {
            $io->writeError('<error>Invalid AWS credentials. Please check your AWS profile configuration.</error>');
            return;
        }

        if ($errorCode === 'AccessDenied') {
            $io->writeError('<error>Access denied. Please ensure your AWS credentials have the necessary permissions.</error>');
            return;
        }

        if ($errorCode === 'UnrecognizedClient') {
            $io->writeError('<error>Unrecognized AWS client. Please verify your AWS SDK configuration.</error>');
            return;
        }

        $io->writeError(sprintf('<error>AWS Error [%s]: %s</error>', $errorCode, $errorMessage));
    }

    private function handleCredentialsException(CredentialsException $e, IOInterface $io): void
    {
        $io->writeError('<error>Failed to retrieve AWS credentials.</error>');
        $io->writeError(sprintf('<error>Reason: %s</error>', $e->getMessage()));
        $io->writeError('<comment>Please ensure that your AWS credentials are correctly configured and have the necessary permissions.</comment>');
    }
}
