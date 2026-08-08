{{-- Toast feedback card. Usage:
    @include('partials.toast', ['type' => 'success', 'message' => 'Saved!'])
    @include('partials.toast', ['type' => 'error', 'message' => '<ul>…</ul>', 'html' => true])
    $type: success | error | info — $message is escaped unless $html is true.
    Optional $dismiss: custom auto-dismiss delay in ms (renders data-dismiss). --}}
@php
    $icons = [
        'success' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'error'   => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
        'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
    ];
    $type = $type ?? 'info';
    $body = ($html ?? false) ? $message : e($message ?? '');
@endphp
<div class="toast toast-{{ $type }}" role="{{ $type === 'error' ? 'alert' : 'status' }}" @if (! empty($dismiss)) data-dismiss="{{ (int) $dismiss }}" @endif>
    <span class="toast-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$type] ?? $icons['info'] !!}</svg>
    </span>
    <div class="toast-msg">{!! $body !!}</div>
    <button type="button" class="toast-close" aria-label="Dismiss">&times;</button>
</div>
