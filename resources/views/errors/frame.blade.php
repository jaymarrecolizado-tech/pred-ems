{{-- Friendly error page shell. Params:
    $code       int     HTTP status code
    $title      string  short friendly title
    $message    ?string exception message (custom abort() text) — optional
    $description string  helpful next-step line
    $icon       string  optional inline SVG icon path set
    Works standalone (masthead only, no auth sidebar) so it is safe for
    unauthenticated errors too. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1b2a4a">
    <title>{{ $code }} · {{ $title }} · DICT RO2 HRIS</title>
    <link href="{{ asset('fonts/fonts.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="error-body">
    <div class="masthead">
        <div class="masthead-inner">
            <span class="masthead-text">Republic of the Philippines</span>
            <span class="masthead-sep"></span>
            <span class="masthead-text strong optional">Department of Information and Communications Technology</span>
            <span class="masthead-sep"></span>
            <span class="masthead-text optional">Regional Office 2</span>
        </div>
    </div>

    <main class="error-shell" id="main-content">
        <div class="error-card">
            <div class="error-code" aria-hidden="true">{{ $code }}</div>

            @include('partials.error-cat', ['size' => 180])

            <h1 class="error-title">{{ $title }}</h1>

            @if (! empty($message))
                <p class="error-message" role="alert">{{ $message }}</p>
            @endif

            <p class="error-desc">{{ $description }}</p>

            <div class="error-actions">
                @if (auth()->check())
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Back to Dashboard</a>
                    <a href="javascript:history.back()" class="btn btn-ghost">Go Back</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Sign In</a>
                @endif
            </div>

            <p class="error-foot">DICT Regional Office 2 · Human Resource Information System</p>
        </div>
    </main>
</body>
</html>
