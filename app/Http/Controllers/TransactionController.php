<?php

namespace App\Http\Controllers;

use App\Http\Requests\TransactionRequest;
use App\Models\Organization;
use App\Models\Transaction;
use App\Support\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('viewTransactions', $organization);

        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'type' => ['nullable', Rule::in(['masuk', 'keluar'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = $organization->transactions();

        if (! empty($filters['month'])) {
            $start = Carbon::createFromFormat('Y-m-d', $filters['month'].'-01');
            // whereDate agar hasilnya sama di MariaDB dan SQLite (tes)
            $query->whereDate('tanggal', '>=', $start->toDateString())
                ->whereDate('tanggal', '<=', $start->copy()->endOfMonth()->toDateString());
        }
        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        if (! empty($filters['q'])) {
            $query->where('description', 'like', '%'.$filters['q'].'%');
        }

        // Ringkasan mengikuti filter yang sedang dipakai
        $masuk = (clone $query)->where('type', 'masuk')->sum('amount');
        $keluar = (clone $query)->where('type', 'keluar')->sum('amount');

        $transactions = $query->with('category')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('transactions.index', [
            'organization' => $organization,
            'transactions' => $transactions,
            'masuk' => $masuk,
            'keluar' => $keluar,
            'filters' => $filters,
            'categories' => $this->manualCategories($organization),
            'canManage' => Gate::allows('manageTransactions', $organization),
            'canDelete' => Gate::allows('deleteTransactions', $organization),
        ]);
    }

    public function store(TransactionRequest $request, Organization $organization): RedirectResponse
    {
        $transaction = $organization->transactions()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        ActivityLogger::record($organization, $request->user(), 'transaction.created', $transaction, [
            'description' => $transaction->description,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
        ]);

        return $this->done($organization, 'Transaksi dicatat.');
    }

    public function edit(Organization $organization, Transaction $transaction)
    {
        Gate::authorize('manageTransactions', $organization);

        if ($transaction->isLocked()) {
            return $this->refuse($organization, 'Transaksi otomatis dari iuran tidak bisa diubah. Batalkan pelunasannya dari halaman Iuran.');
        }

        return view('transactions.edit', [
            'organization' => $organization,
            'transaction' => $transaction,
            'categories' => $this->manualCategories($organization),
        ]);
    }

    public function update(TransactionRequest $request, Organization $organization, Transaction $transaction): RedirectResponse
    {
        if ($transaction->isLocked()) {
            return $this->refuse($organization, 'Transaksi otomatis dari iuran tidak bisa diubah. Batalkan pelunasannya dari halaman Iuran.');
        }

        $transaction->update($request->validated());

        ActivityLogger::record($organization, $request->user(), 'transaction.updated', $transaction, [
            'description' => $transaction->description,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
        ]);

        return $this->done($organization, 'Transaksi diperbarui.');
    }

    public function destroy(Request $request, Organization $organization, Transaction $transaction): RedirectResponse
    {
        Gate::authorize('deleteTransactions', $organization);

        if ($transaction->isLocked()) {
            return $this->refuse($organization, 'Transaksi otomatis dari iuran tidak bisa dihapus. Batalkan pelunasannya dari halaman Iuran.');
        }

        $transaction->delete(); // soft delete: tetap tersimpan, tidak dihitung ke saldo

        ActivityLogger::record($organization, $request->user(), 'transaction.deleted', $transaction, [
            'description' => $transaction->description,
            'type' => $transaction->type,
            'amount' => $transaction->amount,
        ]);

        return $this->done($organization, 'Transaksi dihapus.');
    }

    private function manualCategories(Organization $organization)
    {
        return $organization->categories()->where('is_system', false)->orderBy('name')->get();
    }

    private function done(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('transactions.index', $organization)->with('status', $message);
    }

    private function refuse(Organization $organization, string $message): RedirectResponse
    {
        return redirect()->route('transactions.index', $organization)->withErrors(['transaction' => $message]);
    }
}
