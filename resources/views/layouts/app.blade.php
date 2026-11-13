@php
    $org = request()->route('organization');
    $org = $org instanceof \App\Models\Organization ? $org : null;
@endphp
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
        @if ($org)
            <div x-data="{ open: false }" class="min-h-screen lg:grid lg:grid-cols-[240px_1fr]">
                <div class="flex h-14 items-center gap-2 bg-deep px-4 lg:hidden">
                    <button type="button" @click="open = true" class="-ml-2 p-2 text-white" aria-label="Buka menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                    </button>
                    <a href="{{ route('organizations.dashboard', $org) }}" class="text-xl font-extrabold tracking-tight text-white">
                        Kas<b class="text-mint">Kita</b>
                    </a>
                </div>

                <div x-show="open" style="display: none" @click="open = false" class="fixed inset-0 z-30 bg-black/40 lg:hidden"></div>

                @include('layouts.sidebar', ['org' => $org])

                <div class="min-w-0">
                    <main class="px-4 py-6 lg:px-8 lg:py-7">
                        @isset($header)
                            <div class="mb-5">{{ $header }}</div>
                        @endisset
                        {{ $slot }}
                    </main>
                </div>
            </div>
        @else
            <div class="min-h-screen">
                @include('layouts.topbar')

                @isset($header)
                    <header class="border-b border-line bg-white">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <main>
                    {{ $slot }}
                </main>
            </div>
        @endif
    </body>
</html>