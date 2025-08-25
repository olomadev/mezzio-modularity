<?php

declare(strict_types=1);

namespace Modularity\Authentication\JwtAuth;

use Modularity\Authentication\Util\TokenEncryptHelper;

class StatelessToken extends AbstractToken
{
    public function __construct(
        array $config,
        protected TokenEncryptHelper $tokenEncrypt,
        protected JwtEncoderInterface $jwtEncoder
    ) {
        parent::__construct($config, $tokenEncrypt, $jwtEncoder);
    }
}
