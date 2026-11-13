@props(['disabled' => false])

<select @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-line bg-white text-ink shadow-sm focus:border-brand focus:ring-brand disabled:bg-canvas']) }}>{{ $slot }}</select>