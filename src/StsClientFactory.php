<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin;

use Aws\Sts\StsClient;

final class StsClientFactory implements StsClientFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function create(array $config): StsClient
    {
        return new StsClient($config);
    }
}
