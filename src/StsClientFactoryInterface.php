<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin;

use Aws\Sts\StsClient;

interface StsClientFactoryInterface
{
    /**
     * Creates a new StsClient instance with the given configuration.
     *
     * @param array<string, mixed> $config AWS SDK configuration options.
     * @return StsClient
     */
    public function create(array $config): StsClient;
}
