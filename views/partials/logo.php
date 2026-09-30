<?php $gid = 'lg' . substr(bin2hex(random_bytes(3)), 0, 6); ?>
<svg class="<?= e($class ?? 'brand-mark') ?>" viewBox="0 0 32 32" aria-hidden="true" focusable="false">
    <defs><linearGradient id="<?= $gid ?>" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2f6bff"/><stop offset="1" stop-color="#22d3ee"/></linearGradient></defs>
    <rect width="32" height="32" rx="9" fill="url(#<?= $gid ?>)"/>
    <path d="M21.6 11.3a7.2 7.2 0 1 0 0 9.4" fill="none" stroke="#fff" stroke-width="2.8" stroke-linecap="round"/>
    <circle cx="22.6" cy="16" r="2.1" fill="#fff"/>
</svg>
