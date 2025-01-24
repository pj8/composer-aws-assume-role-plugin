<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final class CommandProvider implements CommandProviderCapability
{
    /** @return array{0: AssumeRoleCommand} */
    public function getCommands(): array
    {
        return [new AssumeRoleCommand(new StsClientFactory(), new ProcessFactory())];
    }
}
