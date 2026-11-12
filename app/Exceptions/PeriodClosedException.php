<?php

namespace App\Exceptions;

use App\Models\Invoice;
use RuntimeException;

// Dilempar Invoice::settle() bila bulan pencatatan transaksi iuran sudah ditutup
class PeriodClosedException extends RuntimeException
{
    public function __construct(public readonly string $period)
    {
        parent::__construct(
            'Bulan '.Invoice::labelFor($period).' sudah ditutup, jadi pelunasan tidak bisa dicatat. '
            .'Minta Admin membuka kembali bulan itu di halaman Laporan.'
        );
    }
}
