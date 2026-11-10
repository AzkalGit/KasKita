<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ $organization->name }}
            <span class="text-sm font-normal text-gray-500">· {{ ucfirst($currentMember->role) }}</span>
        </h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Navigasi sementara (menu organisasi lengkap dibuat di Langkah H) --}}
            <div class="flex flex-wrap gap-4 text-sm font-semibold">
                @can('viewTransactions', $organization)
                    <a href="{{ route('transactions.index', $organization) }}" class="text-indigo-600 hover:text-indigo-800">Semua transaksi</a>
                @endcan
                @can('manageMembers', $organization)
                    <a href="{{ route('members.index', $organization) }}" class="text-indigo-600 hover:text-indigo-800">Kelola anggota</a>
                @endcan
                @can('manageInvitations', $organization)
                    <a href="{{ route('invitations.index', $organization) }}" class="text-indigo-600 hover:text-indigo-800">Kelola undangan</a>
                @endcan
                @can('manageSettings', $organization)
                    <a href="{{ route('categories.index', $organization) }}" class="text-indigo-600 hover:text-indigo-800">Kelola kategori</a>
                @endcan
            </div>

            <div class="grid md:grid-cols-3 gap-4">
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Saldo</div>
                    <div class="text-2xl font-semibold">Rp {{ number_format($saldo, 0, ',', '.') }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Total pemasukan</div>
                    <div class="text-2xl font-semibold text-green-700">Rp {{ number_format($masuk, 0, ',', '.') }}</div>
                </div>
                <div class="bg-white shadow sm:rounded-lg p-6">
                    <div class="text-sm text-gray-500">Total pengeluaran</div>
                    <div class="text-2xl font-semibold text-red-700">Rp {{ number_format($keluar, 0, ',', '.') }}</div>
                </div>
            </div>

            <div class="bg-white shadow sm:rounded-lg p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="font-semibold">Transaksi terbaru</h3>
                    @can('viewTransactions', $organization)
                        <a href="{{ route('transactions.index', $organization) }}" class="text-sm text-indigo-600">Lihat semua →</a>
                    @endcan
                </div>
                @forelse ($terbaru as $t)
                    <div class="flex justify-between border-b py-2 last:border-0">
                        <div>
                            <div>{{ $t->description }}</div>
                            <div class="text-sm text-gray-500">{{ $t->tanggal->format('d/m/Y') }} · {{ $t->category->name }}</div>
                        </div>
                        <div class="{{ $t->type === 'masuk' ? 'text-green-700' : 'text-red-700' }}">
                            {{ $t->type === 'masuk' ? '+' : '−' }} Rp {{ number_format($t->amount, 0, ',', '.') }}
                        </div>
                    </div>
                @empty
                    <p class="text-gray-500">Belum ada transaksi.</p>
                @endforelse
            </div>

            <a href="{{ route('organizations.index') }}" class="text-sm text-indigo-600">← Kembali ke daftar organisasi</a>
        </div>
    </div>
</x-app-layout>