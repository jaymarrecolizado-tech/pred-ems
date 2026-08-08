<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1b2a4a">
    <title>Sign in · DICT RO2 HRIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-body">
    <div class="masthead">
        <div class="masthead-inner">
            <span class="masthead-text">Republic of the Philippines</span>
            <span class="masthead-sep"></span>
            <span class="masthead-text strong optional">Department of Information and Communications Technology</span>
            <span class="masthead-sep"></span>
            <span class="masthead-text optional">Regional Office 2</span>
        </div>
    </div>

    <div class="login-shell">
        <div class="login-card">
            <div class="login-brand">
                <div class="brand-mark">D</div>
                <h1>DICT Regional Office 2</h1>
                <p>Human Resource Information System</p>
            </div>

            <form method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="field" style="margin-bottom:14px">
                    <label for="email">Email address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                </div>
                <div class="field" style="margin-bottom:20px">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            </form>

            <p class="login-hint">DICT Regional Office 2 · Internal use only</p>
        </div>
    </div>

    <p class="login-foot">An official website of the Republic of the Philippines</p>

    @if ($errors->any())
        <div class="toast-stack" aria-live="polite">
            @include('partials.toast', ['type' => 'error', 'html' => true, 'dismiss' => 12000, 'message' => '<strong>Unable to sign in:</strong><ul>' . implode('', array_map(fn ($e) => '<li>' . e($e) . '</li>', $errors->all())) . '</ul>'])
        </div>
    @endif

    <script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
