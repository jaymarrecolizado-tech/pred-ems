<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1b2a4a">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · DICT RO2 HRIS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Be+Vietnam+Pro:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>    <a class="skip-link" href="#main-content">Skip to content</a>
<div class="masthead">
    <div class="masthead-inner">
        <span class="masthead-text">Republic of the Philippines</span>
        <span class="masthead-sep"></span>
        <span class="masthead-text strong optional">Department of Information and Communications Technology</span>
        <span class="masthead-sep"></span>
        <span class="masthead-text optional">Regional Office 2</span>
    </div>
</div>
<div class="layout">
    <div id="sidebar-backdrop" class="sidebar-backdrop" aria-hidden="true"></div>
    <aside id="sidebar" class="sidebar">
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
            {{-- Overview --}}
            <div class="nav-group" data-nav-group="overview">
                <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-overview">
                    <span>Overview</span>
                    @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                </button>
                <div class="nav-group-panel" id="nav-group-overview">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" {!! request()->routeIs('dashboard') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'dashboard'])
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('profile.show') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" {!! request()->routeIs('profile.*') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'profile'])
                        <span>My Profile</span>
                    </a>
                </div>
            </div>

            @if ($canViewDirectory)
                {{-- Staffing --}}
                <div class="nav-group" data-nav-group="staffing">
                    <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-staffing">
                        <span>Staffing</span>
                        @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                    </button>
                    <div class="nav-group-panel" id="nav-group-staffing">
                        <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.index') ? 'active' : '' }}" {!! request()->routeIs('employees.index') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'employees'])
                            <span>Employee Profiles</span>
                        </a>
                    </div>
                </div>
            @endif

            {{-- Leave --}}
            <div class="nav-group" data-nav-group="leave">
                <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-leave">
                    <span>Leave</span>
                    @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                </button>
                <div class="nav-group-panel" id="nav-group-leave">
                    <a href="{{ route('leave.index') }}" class="nav-link {{ request()->routeIs('leave.index', 'leave.create') ? 'active' : '' }}" {!! request()->routeIs('leave.index', 'leave.create') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'leave'])
                        <span>My Leave</span>
                    </a>
                    @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                        <a href="{{ route('leave.approvals') }}" class="nav-link {{ request()->routeIs('leave.approvals') ? 'active' : '' }}" {!! request()->routeIs('leave.approvals') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'audit'])
                            <span>Leave Approvals</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Attendance --}}
            <div class="nav-group" data-nav-group="attendance">
                <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-attendance">
                    <span>Attendance</span>
                    @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                </button>
                <div class="nav-group-panel" id="nav-group-attendance">
                    <a href="{{ route('attendance.index') }}" class="nav-link {{ request()->routeIs('attendance.index', 'attendance.dtr', 'attendance.dtr.pdf') ? 'active' : '' }}" {!! request()->routeIs('attendance.index', 'attendance.dtr', 'attendance.dtr.pdf') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'clock'])
                        <span>My Attendance</span>
                    </a>
                    @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                        <a href="{{ route('attendance.checkpoints') }}" class="nav-link {{ request()->routeIs('attendance.checkpoints*') ? 'active' : '' }}" {!! request()->routeIs('attendance.checkpoints*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'map'])
                            <span>Checkpoints</span>
                        </a>
                        <a href="{{ route('attendance.corrections') }}" class="nav-link {{ request()->routeIs('attendance.corrections') ? 'active' : '' }}" {!! request()->routeIs('attendance.corrections') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'audit'])
                            <span>Correction Requests</span>
                        </a>
                        <a href="{{ route('attendance.logs') }}" class="nav-link {{ request()->routeIs('attendance.logs') ? 'active' : '' }}" {!! request()->routeIs('attendance.logs') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'documents'])
                            <span>Timelogs</span>
                        </a>
                        <a href="{{ route('attendance.settings') }}" class="nav-link {{ request()->routeIs('attendance.settings') ? 'active' : '' }}" {!! request()->routeIs('attendance.settings') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'audit'])
                            <span>Office Hours</span>
                        </a>
                    @endif
                </div>
            </div>

            @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                {{-- Administration --}}
                <div class="nav-group" data-nav-group="admin">
                    <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-admin">
                        <span>Administration</span>
                        @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                    </button>
                    <div class="nav-group-panel" id="nav-group-admin">
                        <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" {!! request()->routeIs('audit-logs.*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'audit'])
                            <span>Audit Trail</span>
                        </a>
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" {!! request()->routeIs('reports.*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'reports'])
                            <span>Reports</span>
                        </a>
                        <span class="nav-link disabled">@include('partials.icon', ['name' => 'payroll'])<span>Payroll</span><em class="phase">(P6)</em></span>
                    </div>
                </div>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                @include('partials.avatar', ['employee' => $myEmployee, 'size' => 34])
                <div style="min-width:0">
                    <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                    <div class="sidebar-user-role">{{ str_replace('_', ' ', ucfirst($myRole)) }}</div>
                </div>
            </div>
            <span class="version">Phases 1–5 · Foundation → Attendance</span>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">
                <button type="button" id="sidebar-toggle" class="btn-icon" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar">
                    <svg class="btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
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

        <div class="content" id="main-content">
            @hasSection('breadcrumbs')
                @yield('breadcrumbs')
            @endif

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
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
