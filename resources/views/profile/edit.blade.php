@extends('layouts.app')

@section('title', 'Edit My Profile')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Profile', route('profile.show')], ['Edit']]])
@endsection

@section('content')
    <div class="card">
        <div class="card-header">
            <h2>Edit Personal Information</h2>
            <a href="{{ route('profile.show') }}" class="btn btn-outline btn-sm">← Back to profile</a>
        </div>

        <div class="card-pad">
        <p class="text-muted" style="margin:0 0 14px">
            You can update your personal details and government ID numbers here.
            Employment information (position, salary, status) and your official gov email
            are managed by the HR office — contact them for corrections.
        </p>

        <form method="POST" action="{{ route('profile.update') }}">
            @csrf
            @method('PUT')

            <div class="form-section">
                <div class="form-section-title">Name</div>
                <div class="form-grid">
                    <div class="field">
                        <label for="first_name">First Name <span class="req">*</span></label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $employee->first_name) }}" class="{{ $errors->first('first_name') ? 'input-error' : '' }}">
                        @error('first_name')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="middle_name">Middle Name</label>
                        <input type="text" id="middle_name" name="middle_name" value="{{ old('middle_name', $employee->middle_name) }}">
                    </div>
                    <div class="field">
                        <label for="last_name">Last Name <span class="req">*</span></label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $employee->last_name) }}" class="{{ $errors->first('last_name') ? 'input-error' : '' }}">
                        @error('last_name')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    <div class="field">
                        <label for="suffix">Suffix</label>
                        <input type="text" id="suffix" name="suffix" value="{{ old('suffix', $employee->suffix) }}" placeholder="Jr., Sr., III">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">Personal Details</div>
                <div class="form-grid">
                    <div class="field">
                        <label for="birth_date">Birth Date</label>
                        <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date', $employee->birth_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="field">
                        <label for="birth_place">Birth Place</label>
                        <input type="text" id="birth_place" name="birth_place" value="{{ old('birth_place', $employee->birth_place) }}">
                    </div>
                    <div class="field">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">— Select —</option>
                            <option value="Male" @selected(old('gender', $employee->gender) === 'Male')>Male</option>
                            <option value="Female" @selected(old('gender', $employee->gender) === 'Female')>Female</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="civil_status">Civil Status</label>
                        <select id="civil_status" name="civil_status">
                            <option value="">— Select —</option>
                            @foreach (['Single', 'Married', 'Widowed', 'Separated'] as $status)
                                <option value="{{ $status }}" @selected(old('civil_status', $employee->civil_status) === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="citizenship">Citizenship</label>
                        <input type="text" id="citizenship" name="citizenship" value="{{ old('citizenship', $employee->citizenship) }}">
                    </div>
                    <div class="field">
                        <label for="blood_type">Blood Type</label>
                        <input type="text" id="blood_type" name="blood_type" value="{{ old('blood_type', $employee->blood_type) }}" placeholder="O+, A-">
                    </div>
                    <div class="field">
                        <label for="contact_number">Contact Number</label>
                        <input type="text" id="contact_number" name="contact_number" value="{{ old('contact_number', $employee->contact_number) }}">
                    </div>
                    <div class="field">
                        <label for="personal_email">Personal Email</label>
                        <input type="email" id="personal_email" name="personal_email" value="{{ old('personal_email', $employee->personal_email) }}">
                    </div>
                    <div class="field">
                        <label for="gov_email">Gov Email <span class="hint" style="font-weight:400">(HR-managed)</span></label>
                        <input type="email" id="gov_email" value="{{ $employee->gov_email }}" disabled placeholder="name@dict.gov.ph">
                        <div class="error"></div>
                        <p class="text-muted" style="font-size:11.5px; margin:4px 0 0">Your official email is your login — contact HR to change it.</p>
                    </div>
                    <div class="field" style="grid-column: 1 / -1">
                        <label for="residential_address">Residential Address</label>
                        <input type="text" id="residential_address" name="residential_address" value="{{ old('residential_address', $employee->residential_address) }}">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">Government IDs</div>
                <div class="form-grid">
                    <div class="field">
                        <label for="gsis_no">GSIS Number</label>
                        <input type="text" id="gsis_no" name="gsis_no" value="{{ old('gsis_no', $employee->gsis_no) }}">
                    </div>
                    <div class="field">
                        <label for="philhealth_no">PhilHealth Number</label>
                        <input type="text" id="philhealth_no" name="philhealth_no" value="{{ old('philhealth_no', $employee->philhealth_no) }}">
                    </div>
                    <div class="field">
                        <label for="pagibig_no">PAG-IBIG Number</label>
                        <input type="text" id="pagibig_no" name="pagibig_no" value="{{ old('pagibig_no', $employee->pagibig_no) }}">
                    </div>
                    <div class="field">
                        <label for="tin_no">TIN</label>
                        <input type="text" id="tin_no" name="tin_no" value="{{ old('tin_no', $employee->tin_no) }}">
                    </div>
                    <div class="field">
                        <label for="sss_no">SSS Number</label>
                        <input type="text" id="sss_no" name="sss_no" value="{{ old('sss_no', $employee->sss_no) }}">
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section-title">Remarks</div>
                <div class="field">
                    <textarea id="remarks" name="remarks" rows="3">{{ old('remarks', $employee->remarks) }}</textarea>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <a href="{{ route('profile.show') }}" class="btn btn-outline">Cancel</a>
            </div>
        </form>
        </div>
    </div>
@endsection
