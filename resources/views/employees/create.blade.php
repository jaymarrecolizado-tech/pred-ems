@extends('layouts.app')

@section('title', 'Add Employee')

@section('content')
    <div class="card card-pad">
        <form method="POST" action="{{ route('employees.store') }}">
            @csrf
            @include('employees.partials.form', ['employee' => null])

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Employee</button>
                <a href="{{ route('employees.index') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
@endsection
