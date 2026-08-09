@php
    $raw = $exception?->getMessage();
    $message = ($raw && ! str_starts_with($raw, 'No query results for model') && ! in_array($raw, ['Not Found', 'Not Found (http error 404)'], true)) ? $raw : null;
@endphp

@include('errors.frame', [
    'code' => 404,
    'title' => 'This page wandered off',
    'message' => $message,
    'description' => 'The page you\'re looking for doesn\'t exist or may have moved. Try the dashboard or use the menu to find what you need.',
])
