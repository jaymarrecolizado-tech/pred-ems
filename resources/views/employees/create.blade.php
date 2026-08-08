@extends('layouts.app')

@section('title', 'Add Employee')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Employee Profiles', route('employees.index')], ['Add Employee']]])
@endsection

@section('content')
    <div class="card card-pad">
        <form method="POST" action="{{ route('employees.store') }}" enctype="multipart/form-data">
            @csrf
            @include('employees.partials.form', ['employee' => null])

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Employee</button>
                <a href="{{ route('employees.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
