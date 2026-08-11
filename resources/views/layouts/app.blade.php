<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1b2a4a">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · DICT RO2 HRIS</title>
    <link href="{{ asset('fonts/fonts.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
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
            <button type="button" id="sidebar-toggle" class="sidebar-toggle js-sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar" title="Collapse menu">
                <svg class="btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>
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
                <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-overview" title="Overview">
                    @include('partials.icon', ['name' => 'home'])
                    <span>Overview</span>
                    @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                </button>
                <div class="nav-group-panel" id="nav-group-overview">
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard" {!! request()->routeIs('dashboard') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'dashboard'])
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('profile.show') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'active' : '' }}" title="My Profile" {!! request()->routeIs('profile.*') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'profile'])
                        <span>My Profile</span>
                    </a>
                    <a href="{{ route('payroll.my') }}" class="nav-link {{ request()->routeIs('payroll.my') ? 'active' : '' }}" title="My Payslips" {!! request()->routeIs('payroll.my') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'file-text'])
                        <span>My Payslips</span>
                    </a>
                    @if ($myEmployee)
                    <a href="{{ route('documents.requests') }}" class="nav-link {{ request()->routeIs('documents.requests*') ? 'active' : '' }}" title="My Documents" {!! request()->routeIs('documents.requests*') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'documents'])
                        <span>My Documents</span>
                    </a>
                    @endif
                </div>
            </div>

            @if ($canViewDirectory)
                {{-- Staffing --}}
                <div class="nav-group" data-nav-group="staffing">
                    <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-staffing" title="Staffing">
                        @include('partials.icon', ['name' => 'briefcase'])
                        <span>Staffing</span>
                        @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                    </button>
                    <div class="nav-group-panel" id="nav-group-staffing">
                        <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.index') ? 'active' : '' }}" title="Employee Profiles" {!! request()->routeIs('employees.index') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'employees'])
                            <span>Employee Profiles</span>
                        </a>
                    </div>
                </div>
            @endif

            {{-- Leave --}}                <div class="nav-group" data-nav-group="leave">
                    <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-leave" title="Leave">
                        @include('partials.icon', ['name' => 'calendar-check'])
                    <span>Leave</span>
                    @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                </button>
                <div class="nav-group-panel" id="nav-group-leave">
                    <a href="{{ route('leave.index') }}" class="nav-link {{ request()->routeIs('leave.index', 'leave.create') ? 'active' : '' }}" title="My Leave" {!! request()->routeIs('leave.index', 'leave.create') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'leave'])
                        <span>My Leave</span>
                    </a>
                    @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                        <a href="{{ route('leave.approvals') }}" class="nav-link {{ request()->routeIs('leave.approvals') ? 'active' : '' }}" title="Leave Approvals" {!! request()->routeIs('leave.approvals') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'check-circle'])
                            <span>Leave Approvals</span>
                        </a>
                        <a href="{{ route('leave.monetization') }}" class="nav-link {{ request()->routeIs('leave.monetization') ? 'active' : '' }}" title="VL Monetization" {!! request()->routeIs('leave.monetization') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'dollar-sign'])
                            <span>VL Monetization</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Attendance --}}                <div class="nav-group" data-nav-group="attendance">
                    <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-attendance" title="Attendance">
                        @include('partials.icon', ['name' => 'activity'])
                    <span>Attendance</span>
                    @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                </button>
                <div class="nav-group-panel" id="nav-group-attendance">
                    <a href="{{ route('attendance.index') }}" class="nav-link {{ request()->routeIs('attendance.index', 'attendance.dtr', 'attendance.dtr.pdf') ? 'active' : '' }}" title="My Attendance" {!! request()->routeIs('attendance.index', 'attendance.dtr', 'attendance.dtr.pdf') ? 'aria-current="page"' : '' !!}>
                        @include('partials.icon', ['name' => 'clock'])
                        <span>My Attendance</span>
                    </a>
                    @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                        <a href="{{ route('attendance.checkpoints') }}" class="nav-link {{ request()->routeIs('attendance.checkpoints*') ? 'active' : '' }}" title="Checkpoints" {!! request()->routeIs('attendance.checkpoints*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'map'])
                            <span>Checkpoints</span>
                        </a>
                        <a href="{{ route('attendance.corrections') }}" class="nav-link {{ request()->routeIs('attendance.corrections') ? 'active' : '' }}" title="Correction Requests" {!! request()->routeIs('attendance.corrections') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'edit'])
                            <span>Correction Requests</span>
                        </a>
                        <a href="{{ route('attendance.logs') }}" class="nav-link {{ request()->routeIs('attendance.logs') ? 'active' : '' }}" title="Timelogs" {!! request()->routeIs('attendance.logs') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'list'])
                            <span>Timelogs</span>
                        </a>
                        <a href="{{ route('attendance.settings') }}" class="nav-link {{ request()->routeIs('attendance.settings') ? 'active' : '' }}" title="Office Hours" {!! request()->routeIs('attendance.settings') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'sliders'])
                            <span>Office Hours</span>
                        </a>
                    @endif
                </div>
            </div>

            @if (auth()->user()->hasAnyRole(['admin', 'hr', 'payroll']))
                {{-- Administration --}}
                <div class="nav-group" data-nav-group="admin">
                    <button type="button" class="nav-group-toggle" aria-expanded="true" aria-controls="nav-group-admin" title="Administration">
                        @include('partials.icon', ['name' => 'shield'])
                        <span>Administration</span>
                        @include('partials.icon', ['name' => 'chevron', 'class' => 'nav-group-chevron'])
                    </button>
                    <div class="nav-group-panel" id="nav-group-admin">
                        @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                        <a href="{{ route('audit-logs.index') }}" class="nav-link {{ request()->routeIs('audit-logs.*') ? 'active' : '' }}" title="Audit Trail" {!! request()->routeIs('audit-logs.*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'search'])
                            <span>Audit Trail</span>
                        </a>
                        <a href="{{ route('reports.index') }}" class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" title="Reports" {!! request()->routeIs('reports.*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'reports'])
                            <span>Reports</span>
                        </a>
                        <a href="{{ route('documents.requests.queue') }}" class="nav-link {{ request()->routeIs('documents.requests.queue') ? 'active' : '' }}" title="Document Requests" {!! request()->routeIs('documents.requests.queue') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'inbox'])
                            <span>Document Requests</span>
                        </a>
                        <a href="{{ route('imports.index') }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" title="Data Imports" {!! request()->routeIs('imports.*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'upload'])
                            <span>Data Imports</span>
                        </a>
                        @endif
                        <a href="{{ route('payroll.index') }}" class="nav-link {{ request()->routeIs('payroll.*') ? 'active' : '' }}" title="Payroll" {!! request()->routeIs('payroll.*') ? 'aria-current="page"' : '' !!}>
                            @include('partials.icon', ['name' => 'payroll'])<span>Payroll</span>
                        </a>
                        @if (auth()->user()->hasAnyRole(['admin']))
                            <a href="{{ route('notifications.settings') }}" class="nav-link {{ request()->routeIs('notifications.settings*') ? 'active' : '' }}" title="Notification Settings" {!! request()->routeIs('notifications.settings*') ? 'aria-current="page"' : '' !!}>
                                @include('partials.icon', ['name' => 'bell'])
                                <span>Notification Settings</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                @include('partials.avatar', ['employee' => $myEmployee, 'size' => 34])
                <div class="sidebar-user-text" style="min-width:0">
                    <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                    <div class="sidebar-user-role">{{ str_replace('_', ' ', ucfirst($myRole)) }}</div>
                </div>
            </div>
            <span class="version">Phases 1–6 · Foundation → Payroll</span>
        </div>
    </aside>

    <main class="main">
        <header class="topbar">
            <div class="topbar-title">
                <button type="button" class="btn-icon topbar-toggle js-sidebar-toggle" aria-label="Toggle navigation menu" aria-expanded="false" aria-controls="sidebar" title="Open menu">
                    <svg class="btn-icon-svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                        <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                    </svg>
                </button>
                <h1>@yield('title', 'Dashboard')</h1>
            </div>
            <div class="topbar-user">
                @include('partials.notification-bell')
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

            @yield('content')
        </div>

        {{-- Action feedback — rendered as toasts (auto-dismissed by app.js) --}}
        <div class="toast-stack" aria-live="polite">
            @if (session('success'))
                @include('partials.toast', ['type' => 'success', 'message' => session('success')])
            @endif
            @if (session('error'))
                @include('partials.toast', ['type' => 'error', 'message' => session('error')])
            @endif
            @if ($errors->any())
                @include('partials.toast', ['type' => 'error', 'html' => true, 'dismiss' => 12000, 'message' => '<strong>Please fix the following:</strong><ul>' . implode('', array_map(fn ($e) => '<li>' . e($e) . '</li>', $errors->all())) . '</ul>'])
            @endif
        </div>
    </main>
</div>
<script src="{{ asset('js/app.js') }}" defer></script>
@stack('scripts')
</body>
</html>
