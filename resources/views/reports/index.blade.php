@extends('layouts.app')

@section('title', 'Reports')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Reports']]])
@endsection

@section('content')
    <div class="overline">Reporting suite</div>

    <div class="stat-row" style="margin-bottom:20px">
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Total Employees</div>
                <div class="stat-tile-value">{{ $totalEmployees }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Active</div>
                <div class="stat-tile-value">{{ $activeCount }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Separated / Retired</div>
                <div class="stat-tile-value">{{ $separatedCount }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Active Leave Cardholders</div>
                <div class="stat-tile-value">{{ $leaveBalancesCount }}</div>
            </div>
        </div>
        <div class="stat-tile">
            <div>
                <div class="stat-tile-label">Documents Issued</div>
                <div class="stat-tile-value">{{ $documentsCount }}</div>
            </div>
        </div>
    </div>

    <div class="info-grid" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">
        <a href="{{ route('reports.headcount') }}" class="card card-pad" style="text-decoration:none; color:inherit; transition:transform .12s, border-color .12s; display:block">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip blue">@include('partials.icon', ['name' => 'reports'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Headcount Report</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">Employees by employment type, division, status, or source of fund · CSV, Excel &amp; PDF</div>
                </div>
            </div>
        </a>

        <a href="{{ route('reports.leave-balances') }}" class="card card-pad" style="text-decoration:none; color:inherit; transition:transform .12s, border-color .12s; display:block">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip indigo">@include('partials.icon', ['name' => 'leave'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Leave Balances</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">VL/SL balances across all active employees · CSV, Excel &amp; PDF</div>
                </div>
            </div>
        </a>

        <a href="{{ route('reports.leave-utilization') }}" class="card card-pad" style="text-decoration:none; color:inherit; transition:transform .12s, border-color .12s; display:block">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip emerald">@include('partials.icon', ['name' => 'leave'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Leave Utilization</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">Approved leave days taken, per leave type and year · CSV, Excel &amp; PDF</div>
                </div>
            </div>
        </a>

        <a href="{{ route('reports.documents') }}" class="card card-pad" style="text-decoration:none; color:inherit; transition:transform .12s, border-color .12s; display:block">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip amber">@include('partials.icon', ['name' => 'documents'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Documents Issued</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">All issued documents with reference numbers · CSV, Excel &amp; PDF</div>
                </div>
            </div>
        </a>

        <a href="{{ route('reports.attrition') }}" class="card card-pad" style="text-decoration:none; color:inherit; transition:transform .12s, border-color .12s; display:block">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip amber">@include('partials.icon', ['name' => 'audit'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Attrition & Onboarding</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">Separations and new hires per year · CSV, Excel &amp; PDF</div>
                </div>
            </div>
        </a>

        <a href="{{ route('reports.attendance-summary') }}" class="card card-pad" style="text-decoration:none; color:inherit; transition:transform .12s, border-color .12s; display:block">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip emerald">@include('partials.icon', ['name' => 'clock'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Attendance Summary</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">Monthly days present, hours, late/undertime &amp; rest-day OT per employee · CSV, Excel &amp; PDF</div>
                </div>
            </div>
        </a>
    </div>
@endsection
