@extends('layouts.app')

@section('title', 'Employee Profiles')

@section('content')
    <form method="GET" action="{{ route('employees.index') }}" class="filter-bar">
        <div class="field">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Name or employee no.">
        </div>
        <div class="field">
            <label for="employment_type">Employment Type</label>
            <select id="employment_type" name="employment_type">
                <option value="">All types</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->id }}" @selected(request('employment_type') == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="division">Division</label>
            <select id="division" name="division">
                <option value="">All divisions</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected(request('division') == $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="source_of_fund">Source of Fund</label>
            <select id="source_of_fund" name="source_of_fund">
                <option value="">All funds</option>
                @foreach ($sourceFunds as $fund)
                    <option value="{{ $fund }}" @selected(request('source_of_fund') === $fund)>{{ $fund }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                @foreach (['active', 'on_leave', 'separated', 'resigned', 'retired'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('employees.index') }}" class="btn btn-outline">Clear</a>
        </div>
        @if (auth()->user()->hasAnyRole(['admin', 'hr']))
            <a href="{{ route('employees.create') }}" class="btn btn-primary" style="margin-left:auto">+ Add Employee</a>
        @endif
    </form>

    <div class="card">
        <div class="card-header">
            <h2>All Employees <span class="hint">({{ $employees->total() }} record(s))</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Employment Type</th>
                        <th>Position</th>
                        <th>Division</th>
                        <th>Grade/Step</th>
                        <th class="num">Monthly Salary</th>
                        <th>Status</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
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
                            <td>
                                @if ($employee->employmentType)
                                    @include('employees.partials.type_badge', ['type' => $employee->employmentType])
                                @else
                                    <span class="badge badge-gray">—</span>
                                @endif
                            </td>
                            <td>{{ $employee->position?->title ?? '—' }}</td>
                            <td>{{ $employee->division?->name ?? '—' }}</td>
                            <td>
                                @if ($employee->salary_grade)
                                    SG {{ $employee->salary_grade }} / Step {{ $employee->step }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="num">
                                @if ($employee->monthly_salary)
                                    ₱{{ number_format((float) $employee->monthly_salary, 2) }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $employee->status_label }}</span>
                            </td>
                            <td class="actions">
                                <a href="{{ route('employees.show', $employee) }}">View</a>
                                @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                                    <a href="{{ route('employees.edit', $employee) }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-muted">No employees match your filters. @if (auth()->user()->hasAnyRole(['admin', 'hr'])) <a href="{{ route('employees.create') }}">Add the first employee</a>. @endif</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            {{ $employees->links('vendor.pagination.custom') }}
        </div>
    </div>
@endsection
