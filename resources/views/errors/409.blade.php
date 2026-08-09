@php
    $raw = $exception?->getMessage();
    $message = ($raw && ! in_array($raw, ['Conflict'], true)) ? $raw : null;
@endphp

@include('errors.frame', [
    'code' => 409,
    'title' => 'This action is already handled',
    'message' => $message,
    'description' => 'This request conflicts with the current state of the record — it may have been processed already. Go back and refresh to see the latest status.',
])
