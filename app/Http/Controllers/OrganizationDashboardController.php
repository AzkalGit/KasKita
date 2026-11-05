<?php

namespace App\Http\Controllers;

use App\Models\Organization;

class OrganizationDashboardController extends Controller
{
    public function show(Organization $organization)
    {
        // Saldo tidak disimpan: dihitung dari saldo awal + transaksi
        $masuk = $organization->transactions()->where('type', 'masuk')->sum('amount');
        $keluar = $organization->transactions()->where('type', 'keluar')->sum('amount');
        $saldo = $organization->opening_balance + $masuk - $keluar;

        $terbaru = $organization->transactions()
            ->with('category')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        return view('organizations.dashboard', compact('organization', 'masuk', 'keluar', 'saldo', 'terbaru'));
    }
}