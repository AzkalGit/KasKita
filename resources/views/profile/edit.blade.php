<x-app-layout>
    <x-slot name="header">
        <h1 class="text-2xl font-extrabold tracking-tight">Profil</h1>
        <p class="mt-0.5 text-mute">Kelola data akun dan kata sandimu.</p>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-4 px-4 py-8 sm:px-6 lg:px-8">
        <div class="rounded-2xl border border-line bg-white p-5 sm:p-6">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-5 sm:p-6">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="rounded-2xl border border-line bg-white p-5 sm:p-6">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>