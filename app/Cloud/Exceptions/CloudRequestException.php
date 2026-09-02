<?php

namespace App\Cloud\Exceptions;

use App\Cloud\Data\CloudProvider;

/**
 * The provider answered with something other than success.
 *
 * The message keeps the provider's own `error.message` when there is one: "File
 * not found: 1a2b3c" or "storageQuotaExceeded" is far more actionable than a
 * status code, and it is the text the results table shows.
 */
final class CloudRequestException extends CloudException
{
    public static function make(CloudProvider $provider, string $operation, int $status, string $detail): self
    {
        return new self((string) __('cfdi.cloud.request_failed', [
            'provider' => $provider->label(),
            'operation' => $operation,
            'status' => $status,
            'detail' => $detail,
        ]));
    }
}
