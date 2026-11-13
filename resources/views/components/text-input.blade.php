@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-line bg-white text-ink shadow-sm placeholder:text-mute focus:border-brand focus:ring-brand disabled:bg-canvas']) }}>