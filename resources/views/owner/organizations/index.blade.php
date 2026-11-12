<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Panel Owner: Organisasi</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-800">{{ session('status') }}</div>
            @endif
            @error('owner')
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-800">{{ $message }}</div>
            @enderror

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Organisasi</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $totalOrganizations }}</div>
                    <div class="text-xs text-gray-500">{{ $activeOrganizations }} aktif, {{ $totalOrganizations - $activeOrganizations }} nonaktif</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Akun terdaftar</div>
                    <div class="text-2xl font-semibold text-gray-900">{{ $totalUsers }}</div>
                    <div class="text-xs text-gray-500">tidak termasuk akun Owner</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-4">
                    <div class="text-sm text-gray-500">Catatan</div>
                    <div class="text-xs text-gray-600 mt-1">Panel ini sengaja tidak menampilkan data keuangan maupun nama anggota organisasi.</div>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-gray-600">
                        <tr>
                            <th class="px-4 py-3 font-medium">Nama</th>
                            <th class="px-4 py-3 font-medium">Dibuat</th>
                            <th class="px-4 py-3 font-medium">Anggota aktif</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($organizations as $organization)
                            <tr>
                                <td class="px-4 py-3 text-gray-900">
                                    {{ $organization->name }}
                                    @if ($organization->active_admins_count === 0)
                                        <span class="ms-2 inline-block rounded bg-yellow-100 px-2 py-0.5 text-xs text-yellow-800">Tanpa Admin</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-600">{{ $organization->created_at->format('d/m/Y') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $organization->active_members_count }}</td>
                                <td class="px-4 py-3">
                                    @if ($organization->status === 'aktif')
                                        <span class="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800">Aktif</span>
                                    @else
                                        <span class="rounded bg-gray-200 px-2 py-0.5 text-xs text-gray-700">Nonaktif</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($organization->status === 'aktif')
                                        <form method="POST" action="{{ route('owner.organizations.deactivate', $organization) }}"
                                              onsubmit="return confirm('Nonaktifkan organisasi ini? Semua anggotanya tidak bisa mengaksesnya sampai diaktifkan kembali.')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-red-600 hover:underline">Nonaktifkan</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('owner.organizations.activate', $organization) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="text-indigo-600 hover:underline">Aktifkan</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">Belum ada organisasi.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>