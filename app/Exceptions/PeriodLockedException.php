<?php

namespace App\Exceptions;

use Exception;

class PeriodLockedException extends Exception
{
    public function __construct(string $periodYm)
    {
        parent::__construct("Tempoh {$periodYm} telah ditutup/dikunci. Transaksi baharu tidak dibenarkan.");
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => ['code' => 'PERIOD_LOCKED', 'message' => $this->getMessage()],
            ], 422);
        }

        return back()->withErrors(['tarikh' => $this->getMessage()])->withInput();
    }
}
