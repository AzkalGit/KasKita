<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-xl border border-line bg-white px-4 py-2.5 text-sm font-bold text-ink transition hover:bg-soft focus:outline-none focus:ring-2 focus:ring-brand focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>