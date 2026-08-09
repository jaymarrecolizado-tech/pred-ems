@extends('layouts.app')

@section('title', 'VL Monetization')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Leave', route('leave.index')], ['Monetization']]])
@endsection

@section('content')
    <form method="GET" action="{{ route('leave.monetization') }}" class="filter-bar">
        <div class="field">
            <label for="search">Search</label>
            <input type="text" id="search" name="search" value="{{ request('search') }}" placeholder="Name or employee no.">
        </div>
        <div class="field" style="flex:0 0 170px">
            <label for="year">Year</label>
            <select id="year" name="year">
                <option value="">All years</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('leave.monetization') }}" class="btn btn-outline">Clear</a>
        </div>
    </form>

    <div style="display:grid; grid-template-columns:1fr 360px; gap:18px; align-items:start">
        <div class="card">
            <div class="card-header">
                <h2>Monetization Records <span class="hint">({{ $monetizations->total() }})</span></h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Reference</th>
                            <th class="num">Days</th>
                            <th class="num">Rate / Day</th>
                            <th class="num">Gross</th>
                            <th>Processed</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($monetizations as $monetization)
                            <tr>
                                <td>
                                    <div class="employee-cell">
                                        @include('partials.avatar', ['employee' => $monetization->employee, 'size' => 34])
                                        <div>
                                            <div class="name">{{ $monetization->employee->full_name }}</div>
                                            <div class="num">{{ $monetization->employee->employee_number }} · {{ $monetization->year }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-blue">{{ $monetization->reference_no }}</span></td>
                                <td class="num"><strong>{{ number_format($monetization->days, 2) }}</strong></td>
                                <td class="num">₱{{ number_format($monetization->per_day_rate, 2) }}</td>
                                <td class="num"><strong>₱{{ number_format($monetization->gross_amount, 2) }}</strong></td>
                                <td>
                                    <div>{{ $monetization->processed_at?->format('M d, Y') }}</div>
                                    <div class="text-muted" style="font-size:11.5px">{{ $monetization->processedBy?->name ?? '—' }}</div>
                                </td>
                                <td class="actions">
                                    <a href="{{ route('leave.monetization.voucher', $monetization) }}" class="btn btn-outline btn-sm" title="Download voucher PDF">Voucher</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No monetization records yet — use the form on the right to process one.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination">
                {{ $monetizations->links('vendor.pagination.custom') }}
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2>Process Monetization</h2>
            </div>
            <div class="card-pad">
                <form method="POST" action="{{ route('leave.monetization.store') }}">
                    @csrf

                    <div class="field" style="margin-bottom:14px">
                        <label for="employee_id">Employee <span class="req">*</span></label>
                        <select id="employee_id" name="employee_id" required>
                            <option value="">Select employee…</option>
                            @foreach ($employees as $row)
                                <option value="{{ $row->employee->id }}" @selected(old('employee_id') == $row->employee->id)>
                                    {{ $row->employee->full_name }} — VL {{ number_format($row->balance, 2) }} · max {{ number_format($row->max_days, 1) }} days
                                </option>
                            @endforeach
                        </select>
                        @error('employee_id')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-grid" style="margin-bottom:14px">
                        <div class="field">
                            <label for="year">Year <span class="req">*</span></label>
                            <select id="year" name="year" required>
                                @foreach ($years as $y)
                                    <option value="{{ $y }}" @selected(old('year', $year) == $y)>{{ $y }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="days">Days <span class="req">*</span></label>
                            <input type="number" id="days" name="days" min="0.5" max="30" step="0.5" value="{{ old('days') }}" required placeholder="e.g. 5">
                            @error('days')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="field" style="margin-bottom:14px">
                        <label for="remarks">Remarks</label>
                        <textarea id="remarks" name="remarks" rows="2" placeholder="Optional">{{ old('remarks') }}</textarea>
                        @error('remarks')<div class="error">{{ $message }}</div>@enderror
                    </div>

                    <div class="form-actions" style="margin-bottom:14px">
                        <button type="submit" class="btn btn-primary">Process Monetization</button>
                    </div>
                </form>

                <div class="hint" style="font-size:12px; line-height:1.6; border-top:1px solid var(--line); padding-top:12px">
                    <strong>CSC rules</strong> (Omnibus Rules on Leave):<br>
                    · Only vacation leave is monetizable<br>
                    · ≥ 10 VL days must be accumulated<br>
                    · Retain ≥ 5 days after monetization<br>
                    · Max 30 days monetized per year<br>
                    · Rate: monthly salary ÷ 22 working days
                </div>
            </div>
        </div>
    </div>
@endsection
