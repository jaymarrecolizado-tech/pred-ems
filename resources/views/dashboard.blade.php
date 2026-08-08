@extends('layouts.app')

@section('title', 'Dashboard')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard']]])
@endsection

@section('content')
    {{-- Hero banner (eGovPay style) --}}
    <section class="hero-banner" aria-label="Welcome">
        <div class="hero-blobs" aria-hidden="true">
            <div class="hero-blob b1"></div>
            <div class="hero-blob b2"></div>
            <div class="hero-blob b3"></div>
        </div>
        <div class="hero-content">
            <h2 class="hero-title">Personnel overview, at a glance</h2>
            <p class="hero-text">
                Live workforce snapshot of DICT Regional Office 2 — headcount,
                employment mix, and the latest additions to the directory.
            </p>
        </div>
        <a href="{{ route('employees.index') }}" class="promo-card">
            <span class="chip blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </span>
            <span>
                <span class="promo-card-title">Employee Directory</span>
                <span class="promo-card-desc">Browse all 201-file profiles, filter by type, division or status.</span>
            </span>
        </a>
    </section>

    {{-- Overview stat row --}}
    <div class="overline">Overview</div>
    <div class="stat-row">
        <div class="stat-tile">
            <span class="chip blue">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
            </span>
            <span>
                <div class="stat-tile-label">Total Employees</div>
                <div class="stat-tile-value">{{ number_format($totalEmployees) }}</div>
            </span>
        </div>
        <div class="stat-tile">
            <span class="chip emerald">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </span>
            <span>
                <div class="stat-tile-label">Active</div>
                <div class="stat-tile-value">{{ number_format($activeEmployees) }}</div>
            </span>
        </div>
        <div class="stat-tile">
            <span class="chip amber">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>
                </svg>
            </span>
            <span>
                <div class="stat-tile-label">Separated / Retired</div>
                <div class="stat-tile-value">{{ number_format($separatedEmployees) }}</div>
            </span>
        </div>
        <div class="stat-tile">
            <span class="chip indigo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
            </span>
            <span>
                <div class="stat-tile-label">Employment Types</div>
                <div class="stat-tile-value">{{ number_format($byType->count()) }}</div>
            </span>
        </div>
    </div>

    {{-- Headcount chart panel --}}
    <div class="card" style="margin-bottom:18px">
        <div class="card-header">
            <h2>Headcount by Employment Type</h2>
            <div class="panel-actions">
                <div class="view-toggle" id="headcount-toggle" role="group" aria-label="Chart view">
                    <button type="button" data-view="table" aria-label="Table view" title="Table">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="5" width="18" height="14" rx="1"/><path d="M3 10h18M9 5v14"/></svg>
                    </button>
                    <button type="button" data-view="area" class="active" aria-pressed="true" aria-label="Area chart view" title="Area chart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 17l5-6 4 4 5-8 4 5"/><path d="M3 20h18"/></svg>
                    </button>
                    <button type="button" data-view="bar" aria-label="Bar chart view" title="Bar chart">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 20V10M12 20V4M20 20v-7"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="chart-body">
            <div id="headcount-chart" data-chart='@json($byType->map(fn ($t) => ['label' => $t->name, 'value' => $t->employees_count])->values())' aria-live="polite"></div>
        </div>
    </div>

    {{-- Recently added --}}
    <div class="card">
        <div class="card-header">
            <h2>Recently Added Employees</h2>
            <a href="{{ route('employees.create') }}" class="btn btn-primary btn-sm">+ Add Employee</a>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employment Type</th>
                        <th>Position</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentEmployees as $employee)
                        <tr>
                            <td>
                                <div class="employee-cell">
                                    @include('partials.avatar', ['employee' => $employee, 'size' => 34])
                                    <div>
                                        <div class="name">
                                            <a href="{{ route('employees.show', $employee) }}">{{ $employee->full_name }}</a>
                                        </div>
                                        <div class="num">{{ $employee->employee_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge {{ $employee->employmentType ? 'badge-blue' : 'badge-gray' }}">{{ $employee->employmentType?->name ?? '—' }}</span></td>
                            <td>{{ $employee->position?->title ?? '—' }}</td>
                            <td><span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $employee->status_label }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted">No employees yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
