@extends('layouts.app')

@section('title', 'Attendance Settings')

@section('content')
    <div class="card card-pad" style="max-width:640px">
        <div class="card-header" style="padding:0 0 14px; border-bottom:1px solid var(--line)">
            <h2>Office Hours <span class="hint">(used by DTR late/undertime and totals)</span></h2>
        </div>
        <form method="POST" action="{{ route('attendance.settings.update') }}" class="form-grid mt-16">
            @csrf
            <div class="field">
                <label for="am_start">AM Start</label>
                <input type="time" id="am_start" name="am_start" value="{{ $officeHours['am_start'] }}" required>
            </div>
            <div class="field">
                <label for="am_end">AM End</label>
                <input type="time" id="am_end" name="am_end" value="{{ $officeHours['am_end'] }}" required>
            </div>
            <div class="field">
                <label for="pm_start">PM Start</label>
                <input type="time" id="pm_start" name="pm_start" value="{{ $officeHours['pm_start'] }}" required>
            </div>
            <div class="field">
                <label for="pm_end">PM End</label>
                <input type="time" id="pm_end" name="pm_end" value="{{ $officeHours['pm_end'] }}" required>
            </div>
            <div class="form-actions" style="grid-column:1 / -1">
                <button type="submit" class="btn btn-primary">Save Office Hours</button>
            </div>
        </form>
    </div>
@endsection
