@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="stat-grid">
        <div class="stat">
            <div class="stat-label">Total Employees</div>
            <div class="stat-value">{{ number_format($totalEmployees) }}</div>
            <div class="stat-sub">All employment types</div>
        </div>
        <div class="stat green">
            <div class="stat-label">Active</div>
            <div class="stat-value">{{ number_format($activeEmployees) }}</div>
            <div class="stat-sub">Currently in service</div>
        </div>
        <div class="stat gold">
            <div class="stat-label">Separated / Retired</div>
            <div class="stat-value">{{ number_format($separatedEmployees) }}</div>
            <div class="stat-sub">Resigned, retired, separated</div>
        </div>
    </div>

    <div class="card card-pad" style="margin-bottom:18px">
        <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--border)">
            <h2>Headcount by Employment Type</h2>
            <a href="{{ route('employees.index') }}" class="btn btn-outline btn-sm">View all employees</a>
        </div>
        <ul class="grow-list mt-16">
            @forelse ($byType as $type)
                <li>
                    <span>
                        <strong>{{ $type->name }}</strong>
                        <span class="text-muted"> · {{ $type->employees_count }} employee(s)</span>
                    </span>
                    <span style="width:40%" class="text-muted">
                        <span class="progress">
                            <i style="width:{{ $totalEmployees ? ($type->employees_count / $totalEmployees) * 100 : 0 }}%"></i>
                        </span>
                    </span>
                </li>
            @empty
                <li class="text-muted">No employees yet. Add your first employee profile.</li>
            @endforelse
        </ul>
    </div>

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
