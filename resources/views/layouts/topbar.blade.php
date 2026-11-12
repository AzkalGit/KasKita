<nav class="bg-deep">
    <div class="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
        <a href="{{ route('organizations.index') }}" class="text-xl font-extrabold tracking-tight text-white">
            Kas<b class="text-mint">Kita</b>
        </a>

        <div class="flex items-center gap-4 text-sm font-semibold text-[#cfe9e5]">
            @if (Auth::user()->isOwner())
                <a href="{{ route('owner.organizations.index') }}" class="hover:text-white">Panel Owner</a>
            @endif
            <a href="{{ route('profile.edit') }}" class="hover:text-white">{{ Auth::user()->name }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="hover:text-white">Keluar</button>
            </form>
        </div>
    </div>
</nav>