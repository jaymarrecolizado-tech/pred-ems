@extends('layouts.app')

@section('title', 'Edit Employee')

@section('content')
    <div class="card card-pad">
        <form method="POST" action="{{ route('employees.update', $employee) }}">
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
