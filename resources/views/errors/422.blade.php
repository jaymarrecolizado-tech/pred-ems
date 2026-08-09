@php
    $raw = $exception?->getMessage();
    $message = ($raw && ! in_array($raw, ['Unprocessable Entity', 'The given data was invalid.'], true)) ? $raw : null;
@endphp

@include('errors.frame', [
    'code' => 422,
    'title' => 'That didn\'t quite work',
    'message' => $message,
    'description' => 'The system couldn\'t process this request as submitted. Go back, double-check the form, and try again.',
])
