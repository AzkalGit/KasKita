@props(['active' => false])

<a {{ $attributes->class([
    'flex items-center gap-2.5 rounded-[10px] px-3 py-[11px] text-sm font-semibold transition',
    'bg-brand text-white' => $active,
    'text-[#cfe9e5] hover:bg-white/10' => ! $active,
]) }}>{{ $slot }}</a>