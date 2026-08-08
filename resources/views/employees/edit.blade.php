@extends('layouts.app')

@section('title', 'Edit Employee')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Employee Profiles', route('employees.index')], [$employee->full_name, route('employees.show', $employee)], ['Edit']]])
@endsection

@section('content')
    <div class="card card-pad">
        <form method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('employees.partials.form', ['employee' => $employee])

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
