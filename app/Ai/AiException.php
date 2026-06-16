<?php

namespace App\Ai;

use RuntimeException;

/**
 * Ralat berstruktur panggilan AI — simpan provider & status HTTP supaya
 * AiCallLog dapat merekod punca kegagalan dengan tepat.
 */
class AiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $provider = '',
        public readonly ?int $httpStatus = null,
    ) {
        parent::__construct($message);
    }
}
