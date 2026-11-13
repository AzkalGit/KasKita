<x-guest-layout>
    <h2 class="text-2xl font-extrabold tracking-tight text-ink">Verifikasi email</h2>
    <p class="mt-1 text-mute">
        Terima kasih sudah mendaftar! Sebelum mulai, verifikasi alamat emailmu lewat tautan yang baru kami kirim. Kalau belum menerima emailnya, kami bisa mengirim ulang.
    </p>

    @if (session('status') == 'verification-link-sent')
        <div class="mt-4 rounded-2xl border border-line bg-soft px-4 py-3 font-semibold text-deep">
            Tautan verifikasi baru sudah dikirim ke alamat email yang kamu pakai saat mendaftar.
        </div>
    @endif

    <div class="mt-6 flex items-center justify-between gap-4">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <x-primary-button>Kirim ulang email verifikasi</x-primary-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="rounded-md text-sm font-semibold text-mute hover:text-ink focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2">Keluar</button>
        </form>
    </div>
</x-guest-layout>