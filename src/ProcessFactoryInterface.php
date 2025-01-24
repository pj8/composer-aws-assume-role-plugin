<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin;

use Symfony\Component\Process\Process;

interface ProcessFactoryInterface
{
    public function create(string $command): Process;
}
