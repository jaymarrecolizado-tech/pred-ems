@extends('layouts.app')

@section('title', 'File Leave')

@section('content')
    <div class="card card-pad" style="max-width:640px">
        <div class="card-header" style="padding:0 0 14px; border-bottom:1px solid var(--line)">
            <h2>File a Leave Application</h2>
        </div>

        <form method="POST" action="{{ route('leave.store') }}" class="mt-16">
            @csrf

            <div class="field" style="margin-bottom:14px">
                <label for="leave_type_id">Leave type <span class="req">*</span></label>
                <select id="leave_type_id" name="leave_type_id" required>
                    <option value="">Select leave type…</option>
                    @foreach ($leaveTypes as $type)
                        <option value="{{ $type->id }}" @selected(old('leave_type_id') == $type->id)>{{ $type->name }} ({{ $type->code }})</option>
                    @endforeach
                </select>
                @error('leave_type_id')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="form-grid" style="margin-bottom:14px">
                <div class="field">
                    <label for="date_from">From <span class="req">*</span></label>
                    <input type="date" id="date_from" name="date_from" value="{{ old('date_from') }}" required>
                    @error('date_from')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label for="date_to">To <span class="req">*</span></label>
                    <input type="date" id="date_to" name="date_to" value="{{ old('date_to') }}" required>
                    @error('date_to')<div class="error">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="field" style="margin-bottom:14px">
                <label for="reason">Reason for leave <span class="req">*</span></label>
                <textarea id="reason" name="reason" rows="3" required>{{ old('reason') }}</textarea>
                @error('reason')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field" style="margin-bottom:20px">
                <label for="contact_during_leave">Contact number during leave</label>
                <input type="text" id="contact_during_leave" name="contact_during_leave" value="{{ old('contact_during_leave') }}" maxlength="100" placeholder="Optional">
                @error('contact_during_leave')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Submit Application</button>
                <a href="{{ route('leave.index') }}" class="btn btn-outline">Back to My Leave</a>
            </div>
        </form>
    </div>

    <div class="card card-pad" style="max-width:640px">
        <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--line)">
            <h2>Current Balances</h2>
        </div>
        <div class="grow-list mt-16">
            @forelse ($balances as $row)
                <li>
                    <span>
                        <strong>{{ $row->leave_type->name }}</strong>
                        @if ($row->leave_type->accrual_per_month > 0)
                            <span class="text-muted"> · accrues {{ rtrim(rtrim(number_format($row->leave_type->accrual_per_month, 2), '0'), '.') }} day(s)/month</span>
                        @endif
                    </span>
                    <strong>{{ number_format($row->balance, 2) }} days</strong>
                </li>
            @empty
                <li class="text-muted">No leave credits on record yet.</li>
            @endforelse
        </div>
        <p class="text-muted" style="font-size:12px; margin:14px 0 0">Days are counted as working days (Mon–Fri) between the selected dates. VL/SL applications require a sufficient credit balance.</p>
    </div>
@endsection
