<?php

namespace App\Exceptions;

use RuntimeException;

class EncryptedCreditCardStatementException extends RuntimeException
{
    public function __construct(string $message = 'O PDF da fatura está protegido por senha.')
    {
        parent::__construct($message);
    }
}
