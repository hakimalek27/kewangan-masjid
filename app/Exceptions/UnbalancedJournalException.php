<?php

namespace App\Exceptions;

use Exception;

class UnbalancedJournalException extends Exception
{
    public function __construct(public string $debit, public string $kredit)
    {
        parent::__construct("Jurnal tidak seimbang: Debit RM{$debit} ≠ Kredit RM{$kredit}. Transaksi DITOLAK.");
    }

    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => ['code' => 'UNBALANCED', 'message' => $this->getMessage()],
            ], 422);
        }

        return back()->withErrors(['jurnal' => $this->getMessage()])->withInput();
    }
}
