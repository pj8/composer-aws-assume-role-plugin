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
            ->setDescription('Assume an AWS IAM role with MFA and execute a Composer command or another command.')
            ->addOption(
                'aws-profile',
                null,
                InputOption::VALUE_REQUIRED,
                'The AWS CLI profile to use for retrieving RoleArn and MFA Serial.'
            )
            ->addOption(
                'composer-command',
                null,
                InputOption::VALUE_REQUIRED,
                'The Composer command to execute after assuming the role.'
            )
            ->addOption(
                'command',
                null,
                InputOption::VALUE_REQUIRED,
                'The command to execute after assuming the role.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getOption('aws-profile');
        $composerCommand = $input->getOption('composer-command');
        $command = $input->getOption('command');
        $io = $this->getIO();
        assert($io !== null);

        try {
            $config = $profile ? $this->getConfigWithProfile($profile, $io) : $this->promptForConfig($io);
            $mfaToken = $this->promptForMfaToken($io);
            $credentials = $this->assumeRole($config, $mfaToken, $profile);

            if ($composerCommand && $command) {
                $io->writeError('<error>You cannot specify both --composer-command and --command options at the same time.</error>');

                return 1;
            }

            if ($composerCommand) {
                $io->write('<info>AssumeRole succeeded! Temporary credentials have been set.</info>');
                $io->write(sprintf('Executing Composer command: composer %s', $composerCommand));
                $env = $this->prepareEnvironment($credentials);
                $this->runComposerCommand($composerCommand, $env, $output);

                return 0;
            }

            if ($command) {
                $io->write('<info>AssumeRole succeeded! Temporary credentials have been set.</info>');
                $io->write(sprintf('Executing command: %s', $command));
                $env = $this->prepareEnvironment($credentials);
                $this->runGeneralCommand($command, $env, $output);

                return 0;
            }

            $io->write('<info>AssumeRole succeeded! Temporary credentials have been retrieved.</info>');
            $json = json_encode($credentials, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            if ($json === false) {
                $io->writeError('<error>Failed to encode credentials as JSON.</error>');

                return 1;
            }

            $output->writeln($json);
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
     * @return array{RoleArn: string, MfaSerial: string, region: string}
     *
     * @throws RuntimeException
     */
    private function getConfigWithProfile(string $profile, IOInterface $io): array
    {
        $io->write(sprintf('Using AWS profile: <info>%s</info>', $profile));

        return $this->getProfileConfiguration($profile);
    }

    /** @return array{RoleArn: string, MfaSerial: string, region: string} */
    private function promptForConfig(IOInterface $io): array
    {
        $io->write('<comment>No profile specified. Please enter details manually.</comment>');

        return [
            'RoleArn'    => $io->ask('Enter the Role ARN: '),
            'MfaSerial'  => $io->ask('Enter your MFA Device ARN: '),
            'region'     => $io->ask('Enter the AWS region [us-east-1]: ', 'us-east-1'),
        ];
    }

    private function promptForMfaToken(IOInterface $io): string
    {
        return $io->ask('Enter your AWS MFA token: ');
    }

    /**
     * @param array{RoleArn: string, MfaSerial: string, region: string} $config
     * @return array{AccessKeyId: string, SecretAccessKey: string, SessionToken: string}
     *
     * @throws AwsException
     * @throws CredentialsException
     */
    private function assumeRole(array $config, string $mfaToken, string|null $profile): array
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
            $result = $stsClient->assumeRole([
                'RoleArn'         => $config['RoleArn'],
                'RoleSessionName' => 'ComposerSession_' . time(),
                'SerialNumber'    => $config['MfaSerial'],
                'TokenCode'       => $mfaToken,
            ]);

            return $result['Credentials'];
        } catch (AwsException $e) {
            if ($e->getAwsErrorCode() === 'CredentialsError') {
                throw new CredentialsException('Failed to retrieve credentials.', 0, $e);
            }

            throw $e;
        }
    }

    /**
     * @param array{AccessKeyId: string, SecretAccessKey: string, SessionToken: string} $credentials
     *
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

    /**
     * @param array<string, string> $env
     *
     * @throws ProcessFailedException
     */
    private function runComposerCommand(string $composerCommand, array $env, OutputInterface $output): void
    {
        $fullCommand = sprintf('composer %s', $composerCommand);
        $process = $this->processFactory->create($fullCommand);
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

        if (empty($profileConfig['role_arn']) || empty($profileConfig['mfa_serial'])) {
            throw new RuntimeException("Profile '{$profile}' must have 'role_arn' and 'mfa_serial' defined.");
        }

        return [
            'RoleArn'    => $profileConfig['role_arn'],
            'MfaSerial'  => $profileConfig['mfa_serial'],
            'region'     => $profileConfig['region'] ?? 'us-east-1',
        ];
    }

    private function handleCredentialsException(CredentialsException $e, IOInterface $io): void
    {
        $io->writeError('<error>Failed to retrieve AWS credentials.</error>');
        $io->writeError(sprintf('<error>Reason: %s</error>', $e->getMessage()));
        $io->writeError('<comment>Please ensure that your AWS credentials are correctly configured and have the necessary permissions.</comment>');
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
}
