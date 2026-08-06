<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in · DICT RO2 HRIS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-body">
    <div class="login-card">
        <div class="login-brand">
            <div class="brand-mark">D</div>
            <h1>DICT RO2 HRIS</h1>
            <p>Employee Management System</p>
        </div>

        @if ($errors->any())
            <div class="alert alert-error">
                <strong>Unable to sign in:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

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
</body>
</html>
