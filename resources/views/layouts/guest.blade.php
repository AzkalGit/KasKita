<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-canvas font-sans text-sm text-ink antialiased">
        <div class="min-h-screen lg:grid lg:grid-cols-[1.1fr_1fr]">
            {{-- Kolom kiri: hanya di layar lebar --}}
            <div class="hidden flex-col justify-between bg-deep p-14 text-white lg:flex">
                <a href="/" class="text-2xl font-extrabold tracking-tight">Kas<b class="text-mint">Kita</b></a>

                <div>
                    <h1 class="max-w-sm text-4xl font-extrabold leading-tight tracking-tight">
                        Kas organisasi, tercatat rapi dan terbuka.
                    </h1>
                    <p class="mt-4 max-w-sm text-[#a9d5d0]">
                        Catat iuran, pengeluaran, dan lihat ke mana uang organisasi dipakai dalam satu tempat.
                    </p>
                </div>

                <div></div>
            </div>

            {{-- Kolom kanan: formulir --}}
            <div class="flex min-h-screen items-center justify-center px-6 py-10 lg:min-h-0">
                <div class="w-full max-w-sm">
                    <a href="/" class="mb-8 block text-2xl font-extrabold tracking-tight text-deep lg:hidden">
                        Kas<b class="text-brand">Kita</b>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>