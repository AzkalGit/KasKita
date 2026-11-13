<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl bg-expense px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-expense focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>