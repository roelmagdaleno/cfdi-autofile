<?php

namespace App\Cloud\Data;

/**
 * The provider-independent half of the integration: whether it runs at all, and
 * who it runs against.
 *
 * Everything a particular provider needs -- a folder id, a bucket, a path
 * prefix -- is configured separately and read through its own accessor, so this
 * stays true no matter how many providers exist.
 */
final readonly class CloudConfig
{
    public function __construct(
        public bool $enabled,
        public CloudProvider $provider,
    ) {}

    public static function defaults(): self
    {
        return new self(enabled: false, provider: CloudProvider::default());
    }
}
