@props(['tone' => 'gray'])
@php
    $kelas = [
        'green' => 'bg-soft text-deep',
        'yellow' => 'bg-[#fff4d6] text-[#7a5600]',
        'red' => 'bg-[#fdeeea] text-expense',
        'gray' => 'bg-canvas text-mute',
    ][$tone] ?? 'bg-canvas text-mute';
@endphp
<span {{ $attributes->class(['inline-block rounded-full px-2.5 py-0.5 text-xs font-bold', $kelas]) }}>{{ $slot }}</span>