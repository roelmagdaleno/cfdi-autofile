<?php

namespace App\Cloud\Data;

/**
 * What comes back from a successful OAuth code exchange.
 *
 * Only the refresh token: the account's address is NOT read here. Doing both in
 * the exchange meant a second HTTP call whose failure was invisible, and it left
 * the address permanently blank with no way back other than reconnecting. It is
 * resolved by `CloudAccount` instead, which can retry later.
 */
final readonly class CloudCredentials
{
    public function __construct(public string $refreshToken) {}
}
