<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin;

use Symfony\Component\Process\Process;

final class ProcessFactory implements ProcessFactoryInterface
{
    public function create(string $command): Process
    {
        return Process::fromShellCommandline($command);
    }
}
