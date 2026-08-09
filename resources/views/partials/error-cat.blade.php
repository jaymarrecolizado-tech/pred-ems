{{-- Friendly animated cat for error pages (pure CSS + inline SVG, no external assets).
    Usage: @include('partials.error-cat', ['size' => 190]) --}}
<div class="error-cat" style="--cat-size: {{ $size ?? 190 }}px" aria-hidden="true">
    <svg class="error-cat-svg" viewBox="0 0 220 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        {{-- Tail (sways) --}}
        <path class="cat-tail" d="M160 150 C 196 148 210 126 204 104 C 200 90 186 88 182 96" stroke="#d97706" stroke-width="12" stroke-linecap="round"/>
        {{-- Body --}}
        <ellipse class="cat-body" cx="108" cy="146" rx="46" ry="40" fill="#f59e0b"/>
        <ellipse class="cat-belly" cx="108" cy="156" rx="27" ry="26" fill="#fde68a"/>
        {{-- Paws --}}
        <ellipse class="cat-paw cat-paw-l" cx="82" cy="184" rx="13" ry="9" fill="#f59e0b"/>
        <ellipse class="cat-paw cat-paw-r" cx="134" cy="184" rx="13" ry="9" fill="#f59e0b"/>
        {{-- Head group (bobs) --}}
        <g class="cat-head">
            {{-- Ears --}}
            <path class="cat-ear cat-ear-l" d="M76 84 L58 44 L100 62 Z" fill="#f59e0b"/>
            <path class="cat-ear cat-ear-r" d="M140 84 L158 44 L116 62 Z" fill="#f59e0b"/>
            <path d="M78 78 L66 52 L92 63 Z" fill="#fcd34d"/>
            <path d="M138 78 L150 52 L124 63 Z" fill="#fcd34d"/>
            {{-- Face --}}
            <ellipse class="cat-face" cx="108" cy="96" rx="42" ry="38" fill="#f59e0b"/>
            <ellipse class="cat-face-lite" cx="108" cy="108" rx="24" ry="20" fill="#fbbf24"/>
            {{-- Stripes --}}
            <path d="M96 62 L99 74 M108 60 L108 73 M120 62 L117 74" stroke="#d97706" stroke-width="4" stroke-linecap="round"/>
            {{-- Eyes (blink) --}}
            <g class="cat-eyes">
                <ellipse class="cat-eye" cx="94" cy="92" rx="5.5" ry="7" fill="#1b2a4a"/>
                <ellipse class="cat-eye" cx="122" cy="92" rx="5.5" ry="7" fill="#1b2a4a"/>
            </g>
            {{-- Nose + mouth --}}
            <path d="M104 102 L112 102 L108 107 Z" fill="#f472b6"/>
            <path d="M108 107 C 105 112 100 111 98 108 M108 107 C 111 112 116 111 118 108" stroke="#78350f" stroke-width="2" stroke-linecap="round" fill="none"/>
            {{-- Whiskers --}}
            <g stroke="#92400e" stroke-width="2" stroke-linecap="round" opacity=".8">
                <line x1="70" y1="98" x2="88" y2="102"/>
                <line x1="70" y1="110" x2="88" y2="108"/>
                <line x1="146" y1="98" x2="128" y2="102"/>
                <line x1="146" y1="110" x2="128" y2="108"/>
            </g>
        </g>
        {{-- Speech bubble (pops in periodically) --}}
        <g class="cat-speech">
            <rect x="118" y="6" width="78" height="34" rx="17" fill="#ffffff" stroke="#e5e7eb"/>
            <path d="M136 38 L144 52 L152 38 Z" fill="#ffffff"/>
            <text x="157" y="29" text-anchor="middle" font-family="Inter, sans-serif" font-size="13" font-weight="700" fill="#1b2a4a">meow?</text>
        </g>
    </svg>
</div>
