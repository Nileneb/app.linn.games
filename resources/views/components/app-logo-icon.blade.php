<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40" {{ $attributes }}>
    {{-- Secondary network connections --}}
    <line x1="10" y1="5" x2="35" y2="35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.18"/>
    <line x1="10" y1="20" x2="23" y2="35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.28"/>
    <line x1="10" y1="5" x2="23" y2="35" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" opacity="0.14"/>

    {{-- Main L-shaped spine --}}
    <line x1="10" y1="5" x2="10" y2="35" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/>
    <line x1="10" y1="35" x2="35" y2="35" stroke="currentColor" stroke-width="4.5" stroke-linecap="round"/>

    {{-- Primary nodes --}}
    <circle cx="10" cy="5"  r="5"   fill="currentColor"/>
    <circle cx="10" cy="35" r="5"   fill="currentColor"/>
    <circle cx="35" cy="35" r="5"   fill="currentColor"/>

    {{-- Secondary nodes --}}
    <circle cx="10" cy="20" r="3"   fill="currentColor" opacity="0.5"/>
    <circle cx="23" cy="35" r="3"   fill="currentColor" opacity="0.5"/>
</svg>
