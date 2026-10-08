{{-- Uso: @include('partials.icono', ['n' => 'mail', 'c' => 'w-5 h-5']) --}}
@php $c = $c ?? 'w-5 h-5'; @endphp
<svg class="{{ $c }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($n)
        @case('mail')
            <rect x="2.5" y="4.5" width="19" height="15" rx="2.5"/><path d="m3 7 9 6 9-6"/>
            @break
        @case('ia')
            <rect x="5" y="7" width="14" height="12" rx="3"/><path d="M12 3.5V7M9.5 12.5v.5M14.5 12.5v.5M9.5 16h5M3 12v3M21 12v3"/><circle cx="12" cy="3" r=".6" fill="currentColor"/>
            @break
        @case('flag')
            <path d="M5 21V4M5 4h11l-2 4 2 4H5"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5.5M12 7.6v.1"/>
            @break
        @case('key')
            <circle cx="8" cy="15" r="3.5"/><path d="M10.5 12.5 20 3m-3 3 2.5 2.5M14 6l2.5 2.5"/>
            @break
        @case('folder')
            <path d="M3 7.5A2.5 2.5 0 0 1 5.5 5h3.2c.6 0 1.2.25 1.6.7l1 1.1h7.2A2.5 2.5 0 0 1 21 9.3v8.2a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 3 17.5Z"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m15.5 15.5 4.5 4.5"/>
            @break
        @case('phone')
            <path d="M6.5 3.5h3l1.5 4-2 1.5a12 12 0 0 0 6 6l1.5-2 4 1.5v3a2 2 0 0 1-2.2 2A16.5 16.5 0 0 1 4.5 5.7 2 2 0 0 1 6.5 3.5Z"/>
            @break
        @case('lock')
            <rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>
            @break
        @case('check')
            <path d="m4.5 12.5 5 5 10-11"/>
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>
            @break
        @case('trophy')
            <path d="M8 4h8v5a4 4 0 0 1-8 0Z"/><path d="M8 5.5H5.5v1A3.5 3.5 0 0 0 9 10M16 5.5h2.5v1A3.5 3.5 0 0 1 15 10M10 13h4l.5 4h-5Z"/><path d="M8 20h8"/>
            @break
        @case('shield')
            <path d="M12 3 5 6v5.5c0 4.2 2.9 7.6 7 9.5 4.1-1.9 7-5.3 7-9.5V6Z"/>
            @break
        @case('back')
            <path d="M15 5 8 12l7 7"/>
            @break
        @case('alert')
            <path d="M12 4.5 2.8 20h18.4Z"/><path d="M12 10v4M12 17.2v.1"/>
            @break
        @case('drag')
            <path d="M9 7h.01M9 12h.01M9 17h.01M15 7h.01M15 12h.01M15 17h.01" stroke-width="2.6"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="3.8"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/>
            @break
        @case('play')
            <path d="M8 5.5v13l10-6.5Z"/>
            @break
        @case('clip')
            <path d="M16.5 7.5 9 15a2.8 2.8 0 0 0 4 4l7.5-7.5a5 5 0 0 0-7-7L6 12a7 7 0 0 0 10 10l6-6"/>
            @break
        @case('link')
            <path d="M10 13.5a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 0 0-5.7-5.7L11.5 6.3"/><path d="M14 10.5a4 4 0 0 0-5.7 0L5.5 13.3a4 4 0 0 0 5.7 5.7l1.3-1.3"/>
            @break
        @case('eye')
            <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('eye-off')
            <path d="M4 4l16 16"/><path d="M9.6 6.1A8.6 8.6 0 0 1 12 5.5c6 0 9.5 6.5 9.5 6.5a17 17 0 0 1-3.3 4"/><path d="M6.4 8A17 17 0 0 0 2.5 12S6 18.5 12 18.5a8.7 8.7 0 0 0 3.2-.6"/>
            @break
        @case('file')
            <path d="M13.5 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8.5Z"/><path d="M13.5 3v5.5H19"/>
            @break
        @case('doc-open')
            <path d="M4 5.5A1.5 1.5 0 0 1 5.5 4H10l1.5 2H18a1.5 1.5 0 0 1 1.5 1.5V9"/><path d="m3 20 2.4-8.2A1.5 1.5 0 0 1 6.8 10.7h13.4a1 1 0 0 1 1 1.3L18.8 20Z"/>
            @break
        @case('download')
            <path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4.5 18.5h15"/>
            @break
        @case('camera')
            <rect x="2.5" y="6.5" width="14" height="10" rx="2"/><path d="m16.5 10 5-3v10l-5-3"/><circle cx="6.5" cy="10" r="1"/>
            @break
        @case('bug')
            <rect x="7" y="7.5" width="10" height="12" rx="5"/><path d="M9 7.5a3 3 0 0 1 6 0M12 11v8.5M3.5 10.5 7 12m-3.5 5L7 15.5m13.5-5L17 12m3.5 5L17 15.5M8.5 3.5 10 5.5m5.5-2L14 5.5"/>
            @break
        @case('network')
            <rect x="9" y="2.5" width="6" height="5" rx="1"/><rect x="2.5" y="16.5" width="6" height="5" rx="1"/><rect x="15.5" y="16.5" width="6" height="5" rx="1"/><path d="M12 7.5v4.5M5.5 16.5V12h13v4.5"/>
            @break
        @case('coin')
            <circle cx="12" cy="12" r="8.5"/><path d="M14.5 9.2c-.6-.8-1.5-1.2-2.5-1.2-1.4 0-2.5.8-2.5 2s1.1 1.7 2.5 2 2.5.8 2.5 2-1.1 2-2.5 2c-1 0-1.9-.4-2.5-1.2M12 6.5V8m0 8v1.5"/>
            @break
        @case('stamp')
            <path d="M9.5 3.5h5l-1 8h-3z"/><path d="M4.5 15a3.5 3.5 0 0 1 3.5-3.5h8a3.5 3.5 0 0 1 3.5 3.5v1.5h-15z"/><path d="M5 20.5h14"/>
            @break
        @default
            <circle cx="12" cy="12" r="8.5"/>
    @endswitch
</svg>
