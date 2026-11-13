<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Panel Owner: Organisasi</h1>
        <p class="mt-0.5 text-mute">Kelola status organisasi di platform.</p>
    </x-slot>

    <div class="mx-auto max-w-7xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
        @if (session('status'))
            <div class="rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">{{ session('status') }}</div>
        @endif
        @error('owner')
            <div class="rounded-2xl border border-expense/40 bg-[#fdeeea] px-4 py-3 font-semibold text-expense">{{ $message }}</div>
        @enderror

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-line bg-white p-5">
                <div class="font-semibold text-mute">Organisasi</div>
                <div class="mt-1.5 text-[22px] font-extrabold tracking-tight">{{ $totalOrganizations }}</div>
                <div class="text-xs text-mute">{{ $activeOrganizations }} aktif, {{ $totalOrganizations - $activeOrganizations }} nonaktif</div>
            </div>
            <div class="rounded-2xl border border-line bg-white p-5">
                <div class="font-semibold text-mute">Akun terdaftar</div>
                <div class="mt-1.5 text-[22px] font-extrabold tracking-tight">{{ $totalUsers }}</div>
                <div class="text-xs text-mute">tidak termasuk akun Owner</div>
            </div>
            <div class="rounded-2xl bg-deep p-5 text-white">
                <div class="font-semibold text-[#9ccfc9]">Catatan</div>
                <div class="mt-1.5 text-xs text-[#cfe9e5]">Panel ini sengaja tidak menampilkan data keuangan maupun nama anggota organisasi.</div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-line bg-white p-5">
            <table class="w-full min-w-[640px] text-left">
                <thead>
                    <tr class="border-b border-line text-xs font-bold uppercase tracking-wider text-mute">
                        <th class="py-2.5 pr-4">Nama</th>
                        <th class="py-2.5 pr-4">Dibuat</th>
                        <th class="py-2.5 pr-4">Anggota aktif</th>
                        <th class="py-2.5 pr-4">Status</th>
                        <th class="py-2.5">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($organizations as $organization)
                        <tr class="border-b border-line last:border-0">
                            <td class="py-3 pr-4 font-semibold">
                                {{ $organization->name }}
                                @if ($organization->active_admins_count === 0)
                                    <x-badge tone="yellow" class="ms-2">Tanpa Admin</x-badge>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-mute">{{ $organization->created_at->format('d/m/Y') }}</td>
                            <td class="py-3 pr-4 text-mute">{{ $organization->active_members_count }}</td>
                            <td class="py-3 pr-4">
                                @if ($organization->status === 'aktif')
                                    <x-badge tone="green">Aktif</x-badge>
                                @else
                                    <x-badge tone="gray">Nonaktif</x-badge>
                                @endif
                            </td>
                            <td class="py-3 font-semibold">
                                @if ($organization->status === 'aktif')
                                    <form method="POST" action="{{ route('owner.organizations.deactivate', $organization) }}"
                                          onsubmit="return confirm('Nonaktifkan organisasi ini? Semua anggotanya tidak bisa mengaksesnya sampai diaktifkan kembali.')">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-expense hover:opacity-80">Nonaktifkan</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('owner.organizations.activate', $organization) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-brand hover:text-brand-dark">Aktifkan</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-mute">Belum ada organisasi.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>