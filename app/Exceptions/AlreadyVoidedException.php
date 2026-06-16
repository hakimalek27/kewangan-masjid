<?php

namespace App\Exceptions;

use Exception;

class AlreadyVoidedException extends Exception
{
    public function __construct(string $ref = '')
    {
        parent::__construct(trim("Voucher {$ref} telah dibatalkan sebelum ini."));
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => ['code' => 'ALREADY_VOIDED', 'message' => $this->getMessage()],
            ], 409);
        }

        return back()->withErrors(['voucher' => $this->getMessage()]);
    }
}
