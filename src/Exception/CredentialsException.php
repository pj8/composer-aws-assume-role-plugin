<?php

declare(strict_types=1);

namespace Pj8\AwsAssumeRolePlugin\Exception;

use RuntimeException;
use Pj8\AwsAssumeRolePlugin\MonitoringEventsInterface;

final class CredentialsException extends RuntimeException implements MonitoringEventsInterface
{
}
