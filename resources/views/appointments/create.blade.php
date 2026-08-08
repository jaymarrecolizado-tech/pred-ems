@extends('layouts.app')

@section('title', 'Add Appointment')

@section('content')
    <div class="profile-header" style="margin-bottom:18px">
        @include('partials.avatar', ['employee' => $employee, 'size' => 52])
        <div>
            <h2>Add Appointment — {{ $employee->full_name }}</h2>
            <div class="meta">{{ $employee->employee_number }} · {{ $employee->position?->title ?? 'No position' }}</div>
        </div>
    </div>

    <div class="card card-pad">
        <form method="POST" action="{{ route('appointments.store', $employee) }}">
            @csrf
            @include('appointments.partials.form', ['appointment' => null])

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Add Appointment</button>
                <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
