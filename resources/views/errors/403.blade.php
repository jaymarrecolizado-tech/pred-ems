@php
    // Show a custom abort() message (e.g. "No employee 201-file record linked
    // to this account.") but not Laravel's generic default.
    $raw = $exception?->getMessage();
    $message = ($raw && ! in_array($raw, ['Forbidden', 'This action is unauthorized.'], true)) ? $raw : null;
@endphp

@include('errors.frame', [
    'code' => 403,
    'title' => 'This page is off-limits',
    'message' => $message,
    'description' => $message
        ? 'If you believe this is a mistake, please contact the HR office for assistance.'
        : 'You don\'t have permission to view this page. If you believe this is a mistake, contact the HR office.',
])
