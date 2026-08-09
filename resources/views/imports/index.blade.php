@extends('layouts.app')

@section('title', 'Data Imports')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Data Imports']]])
@endsection

@section('content')
    <div class="overline">Bulk data entry</div>

    <div class="info-grid" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
        <div class="card card-pad" style="display:flex; flex-direction:column; gap:14px">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip indigo">@include('partials.icon', ['name' => 'employees'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Employee Roster Import</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">Bulk-create or update 201-file records from a CSV / XLS / XLSX roster</div>
                </div>
            </div>
            <ul style="margin:0; padding-left:18px; color:var(--ink-600); font-size:12.5px; line-height:1.7">
                <li>Upserts by <strong>employee number</strong></li>
                <li>Employment type &amp; division resolved by code or name</li>
                <li>Positions auto-created by title</li>
                <li>Preview with per-row errors before committing</li>
            </ul>
            <a href="{{ route('imports.employees') }}" class="btn btn-primary" style="align-self:flex-start">Import employees</a>
        </div>

        <div class="card card-pad" style="display:flex; flex-direction:column; gap:14px">
            <div style="display:flex; align-items:center; gap:12px">
                <span class="chip emerald">@include('partials.icon', ['name' => 'clock'])</span>
                <div>
                    <div style="font-weight:700; color:var(--ink-900)">Attendance Log Import</div>
                    <div style="font-size:12.5px; color:var(--ink-500); margin-top:2px">Load AM/PM in-out punches (e.g. from a biometric export)</div>
                </div>
            </div>
            <ul style="margin:0; padding-left:18px; color:var(--ink-600); font-size:12.5px; line-height:1.7">
                <li>Rows keyed by <strong>employee number + date</strong></li>
                <li>Re-importing the same day overwrites that day's punches (idempotent)</li>
                <li>Entries stamped as HR-entered in the append-only log</li>
                <li>Feeds the CSC Form 48 DTR &amp; attendance reports directly</li>
            </ul>
            <a href="{{ route('imports.attendance') }}" class="btn btn-primary" style="align-self:flex-start">Import attendance</a>
        </div>
    </div>
@endsection
