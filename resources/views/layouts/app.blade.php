<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · DICT RO2 HRIS</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">D</div>
            <div class="brand-text">
                <strong>DICT RO2</strong>
                <span>HR Information System</span>
            </div>
        </div>

        @php
            $canViewDirectory = auth()->user()->hasAnyRole(['admin', 'hr', 'payroll', 'unit_head']);
            $myEmployee = auth()->user()->employee;
        @endphp
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <span class="nav-icon">▦</span> Dashboard
            </a>
            @if ($canViewDirectory)
                <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.index') ? 'active' : '' }}">
                    <span class="nav-icon">👤</span> Employee Profiles
                </a>
            @elseif ($myEmployee)
                <a href="{{ route('employees.show', $myEmployee) }}" class="nav-link {{ request()->routeIs('employees.show') ? 'active' : '' }}">
                    <span class="nav-icon">👤</span> My Profile
                </a>
            @endif

            <div class="nav-section">Modules (upcoming)</div>
            <span class="nav-link disabled"><span class="nav-icon">🏖</span> Leave Management <em class="phase">P2</em></span>
            <span class="nav-link disabled"><span class="nav-icon">📄</span> Documents <em class="phase">P3</em></span>
            <span class="nav-link disabled"><span class="nav-icon">💰</span> Payroll <em class="phase">P4</em></span>
            <span class="nav-link disabled"><span class="nav-icon">📊</span> Reports <em class="phase">P5</em></span>
        </nav>

        <div class="sidebar-footer">
            <span class="version">Phase 1 · Foundation</span>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="topbar-user">
                <span class="user-name">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                </form>
            </div>
        </header>

        <div class="content">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    <strong>Please fix the following:</strong>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
</div>
</body>
</html>
