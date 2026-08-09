@extends('layouts.app')

@section('title', 'Change Password')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Profile', route('profile.show')], ['Change Password']]])
@endsection

@section('content')
    <div class="card" style="max-width:520px">
        <div class="card-header">
            <h2>Change Password</h2>
            <a href="{{ route('profile.show') }}" class="btn btn-outline btn-sm">← Back to profile</a>
        </div>

        <div class="card-pad">
        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')

            <div class="field">
                <label for="current_password">Current Password</label>
                <input type="password" id="current_password" name="current_password" class="{{ $errors->first('current_password') ? 'input-error' : '' }}">
                @error('current_password')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field" style="margin-top:14px">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="{{ $errors->first('password') ? 'input-error' : '' }}">
                @error('password')<div class="error">{{ $message }}</div>@enderror
            </div>

            <div class="field" style="margin-top:14px">
                <label for="password_confirmation">Confirm New Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation">
            </div>

            <p class="text-muted" style="font-size:12px; margin:12px 0 0">Use at least 8 characters. Choose a combination of letters, numbers, and symbols.</p>

            <div class="form-actions" style="margin-top:16px">
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
        </div>
    </div>
@endsection
