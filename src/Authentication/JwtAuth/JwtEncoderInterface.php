<?php

declare(strict_types=1);

namespace Modularity\Authentication\JwtAuth;

interface JwtEncoderInterface
{
    /**
     * Encode array data to jwt token string
     *
     * @param  array  $payload    array
     */
    public function encode(array $payload): string;

    /**
     * Decode token as array
     *
     * @param  string $token     token
     */
    public function decode(string $token): array;
}
