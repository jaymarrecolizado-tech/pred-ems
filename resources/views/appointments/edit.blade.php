@extends('layouts.app')

@section('title', 'Edit Appointment')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Employee Profiles', route('employees.index')], [$employee->full_name, route('employees.show', $employee)], ['Edit Appointment']]])
@endsection

@section('content')
    <div class="profile-header" style="margin-bottom:18px">
        @include('partials.avatar', ['employee' => $employee, 'size' => 52])
        <div>
            <h2>Edit Appointment — {{ $employee->full_name }}</h2>
            <div class="meta">{{ $appointment->appointment_type_label }} · effective {{ $appointment->effective_from->format('F d, Y') }}</div>
        </div>
    </div>

    <div class="card card-pad">
        <form method="POST" action="{{ route('appointments.update', $appointment) }}">
            @csrf
            @method('PUT')
            @include('appointments.partials.form', ['appointment' => $appointment])

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
