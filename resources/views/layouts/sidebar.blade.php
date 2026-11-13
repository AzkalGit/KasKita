@php
    $menungguIuran = auth()->user()->can('manageInvoices', $org)
        ? \App\Models\Invoice::inOrganization($org)
            ->where('invoices.status', \App\Models\Invoice::STATUS_MENUNGGU)
            ->count()
        : 0;
@endphp
<aside
    :class="open ? 'translate-x-0' : '-translate-x-full'"
    class="fixed inset-y-0 left-0 z-40 flex w-60 flex-col gap-1 overflow-y-auto bg-deep p-4 pt-6 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">

    <a href="{{ route('organizations.dashboard', $org) }}" class="px-2.5 pb-5 text-2xl font-extrabold tracking-tight text-white">
        Kas<b class="text-mint">Kita</b>
    </a>

    @can('viewDashboard', $org)
        <x-side-link :href="route('organizations.dashboard', $org)" :active="request()->routeIs('organizations.dashboard')">Dashboard</x-side-link>
    @endcan
    @can('viewTransactions', $org)
        <x-side-link :href="route('transactions.index', $org)" :active="request()->routeIs('transactions.*')">Transaksi</x-side-link>
    @endcan
    @can('viewInvoices', $org)
         <x-side-link :href="route('invoices.index', $org)" :active="request()->routeIs('invoices.*')">
            Iuran
            @if ($menungguIuran > 0)
                <span data-badge-iuran class="ml-auto rounded-full bg-expense px-2 py-0.5 text-xs font-bold text-white">{{ $menungguIuran }}</span>
            @endif
        </x-side-link>
    @endcan
    @can('manageMembers', $org)
        <x-side-link :href="route('members.index', $org)" :active="request()->routeIs('members.*')">Anggota</x-side-link>
    @endcan
    @can('viewReports', $org)
        <x-side-link :href="route('reports.index', $org)" :active="request()->routeIs('reports.*', 'periods.*')">Laporan</x-side-link>
    @endcan

    @if (auth()->user()->can('manageInvitations', $org) || auth()->user()->can('manageSettings', $org))
        <div class="px-3 pb-1 pt-4 text-xs font-bold uppercase tracking-wider text-[#8fc4be]">Pengaturan</div>
        @can('manageInvitations', $org)
            <x-side-link :href="route('invitations.index', $org)" :active="request()->routeIs('invitations.*')">Undangan</x-side-link>
        @endcan
        @can('manageSettings', $org)
            <x-side-link :href="route('categories.index', $org)" :active="request()->routeIs('categories.*')">Kategori</x-side-link>
        @endcan
    @endif

    <div class="mt-auto space-y-1 pt-4">
        <x-side-link :href="route('organizations.index')">← Semua organisasi</x-side-link>

        <div class="flex items-center gap-2.5 rounded-xl bg-white/10 p-3">
            <div class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-mint text-sm font-extrabold text-deep">
                {{ \Illuminate\Support\Str::of(Auth::user()->name)->explode(' ')->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') }}
            </div>
            <div class="min-w-0 text-sm text-white">
                <div class="truncate">{{ Auth::user()->name }}</div>
                <div class="truncate text-xs text-[#8fc4be]">{{ $org->name }}</div>
            </div>
        </div>

        <x-side-link :href="route('profile.edit')">Profil</x-side-link>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-2.5 rounded-[10px] px-3 py-[11px] text-sm font-semibold text-[#cfe9e5] transition hover:bg-white/10">Keluar</button>
        </form>
    </div>
</aside>