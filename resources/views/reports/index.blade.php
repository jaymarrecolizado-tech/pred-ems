@extends('layouts.app')

@section('title', 'Reports')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Reports']]])
@endsection

@section('content')
    <div class="overline">Reporting suite</div>

    <div class="report-stats">
        <div class="card report-stat">
            <div class="report-stat-label">Total Employees</div>
            <div class="report-stat-value">{{ $totalEmployees }}</div>
        </div>
        <div class="card report-stat">
            <div class="report-stat-label">Active</div>
            <div class="report-stat-value">{{ $activeCount }}</div>
        </div>
        <div class="card report-stat">
            <div class="report-stat-label">Separated / Retired</div>
            <div class="report-stat-value">{{ $separatedCount }}</div>
        </div>
        <div class="card report-stat">
            <div class="report-stat-label">Active Leave Cardholders</div>
            <div class="report-stat-value">{{ $leaveBalancesCount }}</div>
        </div>
        <div class="card report-stat">
            <div class="report-stat-label">Documents Issued</div>
            <div class="report-stat-value">{{ $documentsCount }}</div>
        </div>
    </div>

    <div class="report-grid">
        <a href="{{ route('reports.headcount') }}" class="card report-tile" title="Headcount Report">
            <span class="chip blue">@include('partials.icon', ['name' => 'reports'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Headcount Report</span>
                <span class="report-tile-desc">Employees by employment type, division, status, or source of fund · CSV, Excel &amp; PDF</span>
            </span>
        </a>

        <a href="{{ route('reports.leave-balances') }}" class="card report-tile" title="Leave Balances">
            <span class="chip indigo">@include('partials.icon', ['name' => 'leave'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Leave Balances</span>
                <span class="report-tile-desc">VL/SL balances across all active employees · CSV, Excel &amp; PDF</span>
            </span>
        </a>

        <a href="{{ route('reports.leave-utilization') }}" class="card report-tile" title="Leave Utilization">
            <span class="chip emerald">@include('partials.icon', ['name' => 'leave'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Leave Utilization</span>
                <span class="report-tile-desc">Approved leave days taken, per leave type and year · CSV, Excel &amp; PDF</span>
            </span>
        </a>

        <a href="{{ route('reports.documents') }}" class="card report-tile" title="Documents Issued">
            <span class="chip amber">@include('partials.icon', ['name' => 'documents'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Documents Issued</span>
                <span class="report-tile-desc">All issued documents with reference numbers · CSV, Excel &amp; PDF</span>
            </span>
        </a>

        <a href="{{ route('reports.forced-leave') }}" class="card report-tile" title="Forced Leave Monitoring">
            <span class="chip amber">@include('partials.icon', ['name' => 'leave'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Forced Leave Monitoring</span>
                <span class="report-tile-desc">CSC rule: ≥10 VL credits → take ≥5 VL working days per year · CSV, Excel &amp; PDF</span>
            </span>
        </a>

        <a href="{{ route('reports.attrition') }}" class="card report-tile" title="Attrition & Onboarding">
            <span class="chip amber">@include('partials.icon', ['name' => 'audit'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Attrition &amp; Onboarding</span>
                <span class="report-tile-desc">Separations and new hires per year · CSV, Excel &amp; PDF</span>
            </span>
        </a>

        <a href="{{ route('reports.attendance-summary') }}" class="card report-tile" title="Attendance Summary">
            <span class="chip emerald">@include('partials.icon', ['name' => 'clock'])</span>
            <span class="report-tile-text">
                <span class="report-tile-title">Attendance Summary</span>
                <span class="report-tile-desc">Monthly days present, hours, late/undertime &amp; rest-day OT per employee · CSV, Excel &amp; PDF</span>
            </span>
        </a>
    </div>
@endsection
