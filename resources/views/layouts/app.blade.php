<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · DICT RO2 HRIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<div class="masthead">
    <div class="masthead-inner">
        <span class="masthead-text">Republic of the Philippines</span>
        <span class="masthead-sep"></span>
        <span class="masthead-text strong">Department of Information and Communications Technology</span>
        <span class="masthead-sep"></span>
        <span class="masthead-text optional">Regional Office 2</span>
    </div>
</div>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <div class="brand-mark">D</div>
            <div class="brand-text">
                <strong>DICT Regional Office 2</strong>
                <span>Human Resource Information System</span>
            </div>
        </div>

        @php
            $canViewDirectory = auth()->user()->hasAnyRole(['admin', 'hr', 'payroll', 'unit_head']);
            $myEmployee = auth()->user()->employee;
            $myRole = auth()->user()->roles()->first()?->name ?? 'user';
        @endphp
        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                @include('partials.icon', ['name' => 'dashboard'])
                <span>Dashboard</span>
            </a>
            <a href="{{ route('profile.show') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}">
                @include('partials.icon', ['name' => 'profile'])
                <span>My Profile</span>
            </a>
            @if ($canViewDirectory)
                <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.index') ? 'active' : '' }}">
                    @include('partials.icon', ['name' => 'employees'])
                    <span>Employee Profiles</span>
                </a>
            @endif
            @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}">
                    @include('partials.icon', ['name' => 'audit'])
                    <span>Audit Trail</span>
                </a>
            @endif

            <div class="nav-section">Modules (upcoming)</div>
            <span class="nav-link disabled">@include('partials.icon', ['name' => 'leave'])<span>Leave Management</span><em class="phase">(P2)</em></span>
            <span class="nav-link disabled">@include('partials.icon', ['name' => 'documents'])<span>Documents</span><em class="phase">(P3)</em></span>
            <span class="nav-link disabled">@include('partials.icon', ['name' => 'payroll'])<span>Payroll</span><em class="phase">(P4)</em></span>
            <span class="nav-link disabled">@include('partials.icon', ['name' => 'reports'])<span>Reports</span><em class="phase">(P5)</em></span>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                @include('partials.avatar', ['employee' => $myEmployee, 'size' => 34])
                <div style="min-width:0">
                    <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                    <div class="sidebar-user-role">{{ str_replace('_', ' ', ucfirst($myRole)) }}</div>
                </div>
            </div>
            <span class="version">Phase 1 · Foundation</span>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="topbar-user">
                <a href="{{ route('profile.show') }}" class="topbar-user-link">
                    @include('partials.avatar', ['employee' => $myEmployee, 'size' => 30])
                    <span class="user-name">{{ auth()->user()->name }}</span>
                </a>
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
