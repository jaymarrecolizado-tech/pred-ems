@php
    $emp = $employee ?? null;
    $old = fn (string $key, mixed $default = '') => old($key, $emp?->{$key} ?? $default);
    $err = fn (string $key) => $errors->first($key);
@endphp

<div class="form-section">
    <div class="form-section-title">Profile Photo</div>
    <div class="field" style="max-width:420px">
        <label for="photo">Photo (JPG, PNG or WebP · max 2 MB)</label>
        <div style="display:flex; align-items:center; gap:14px">
            @include('partials.avatar', ['employee' => $emp, 'size' => 56])
            <input type="file" id="photo" name="photo" accept="image/png,image/jpeg,image/webp" class="{{ $err('photo') ? 'input-error' : '' }}">
        </div>
        @if ($err('photo'))<div class="error">{{ $err('photo') }}</div>@endif
    </div>
</div>

<div class="form-section">
    <div class="form-section-title">Employment Details</div>
    <div class="form-grid">
        <div class="field">
            <label for="employment_type_id">Employment Type <span class="req">*</span></label>
            <select id="employment_type_id" name="employment_type_id" class="{{ $err('employment_type_id') ? 'input-error' : '' }}">
                <option value="">— Select —</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->id }}" @selected(old('employment_type_id', $emp?->employment_type_id) == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
            @if ($err('employment_type_id'))<div class="error">{{ $err('employment_type_id') }}</div>@endif
        </div>
        <div class="field">
            <label for="status">Status <span class="req">*</span></label>
            <select id="status" name="status" class="{{ $err('status') ? 'input-error' : '' }}">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $emp?->status ?? 'active') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($err('status'))<div class="error">{{ $err('status') }}</div>@endif
        </div>
        <div class="field">
            <label for="employee_number">Employee Number</label>
            <input type="text" id="employee_number" name="employee_number" value="{{ $old('employee_number') }}" placeholder="Auto-generated if blank">
            @if ($err('employee_number'))<div class="error">{{ $err('employee_number') }}</div>@endif
        </div>
        <div class="field">
            <label for="division_id">Division</label>
            <select id="division_id" name="division_id">
                <option value="">— Select —</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected(old('division_id', $emp?->division_id) == $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="position_id">Position</label>
            <select id="position_id" name="position_id">
                <option value="">— Select —</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(old('position_id', $emp?->position_id) == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="plantilla_item_no">Plantilla Item No.</label>
            <input type="text" id="plantilla_item_no" name="plantilla_item_no" value="{{ $old('plantilla_item_no') }}" placeholder="OSEC-DICTB-…">
        </div>
        <div class="field">
            <label for="bp_number">GSIS BP No.</label>
            <input type="text" id="bp_number" name="bp_number" value="{{ $old('bp_number') }}">
        </div>
        <div class="field">
            <label for="source_of_fund">Source of Fund</label>
            <input type="text" id="source_of_fund" name="source_of_fund" value="{{ $old('source_of_fund') }}" list="source-fund-options" placeholder="PLANTILLA, MOOE, PNPKI…">
            <datalist id="source-fund-options">
                <option value="PLANTILLA"></option>
                <option value="MOOE"></option>
                <option value="PNPKI"></option>
                <option value="ELGU"></option>
                <option value="e-LGU"></option>
                <option value="FREE WI-FI"></option>
                <option value="ILCDB"></option>
                <option value="GECS"></option>
                <option value="NIPPSB"></option>
                <option value="GovNet"></option>
                <option value="Tech4ED-DTC"></option>
                <option value="IIDB"></option>
                <option value="eGov"></option>
                <option value="GIP"></option>
            </datalist>
        </div>
        <div class="field">
            <label for="salary_grade">Salary Grade</label>
            <input type="number" id="salary_grade" name="salary_grade" min="1" max="33" value="{{ $old('salary_grade') }}">
        </div>
        <div class="field">
            <label for="step">Step</label>
            <input type="number" id="step" name="step" min="1" max="8" value="{{ $old('step') }}">
        </div>
        <div class="field">
            <label for="monthly_salary">Monthly Salary (₱)</label>
            <input type="number" id="monthly_salary" name="monthly_salary" step="0.01" min="0" value="{{ $old('monthly_salary') }}">
        </div>
        <div class="field">
            <label for="date_original_appointment">Date of Original Appointment</label>
            <input type="date" id="date_original_appointment" name="date_original_appointment" value="{{ $old('date_original_appointment', $emp?->date_original_appointment?->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label for="date_last_promotion">Date of Last Promotion</label>
            <input type="date" id="date_last_promotion" name="date_last_promotion" value="{{ $old('date_last_promotion', $emp?->date_last_promotion?->format('Y-m-d')) }}">
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-title">Personal Information</div>
    <div class="form-grid">
        <div class="field">
            <label for="first_name">First Name <span class="req">*</span></label>
            <input type="text" id="first_name" name="first_name" value="{{ $old('first_name') }}" class="{{ $err('first_name') ? 'input-error' : '' }}">
            @if ($err('first_name'))<div class="error">{{ $err('first_name') }}</div>@endif
        </div>
        <div class="field">
            <label for="middle_name">Middle Name</label>
            <input type="text" id="middle_name" name="middle_name" value="{{ $old('middle_name') }}">
        </div>
        <div class="field">
            <label for="maiden_name">Maiden Name <span class="hint" style="text-transform:none; letter-spacing:0">(if married woman)</span></label>
            <input type="text" id="maiden_name" name="maiden_name" value="{{ $old('maiden_name') }}">
        </div>
        <div class="field">
            <label for="last_name">Last Name <span class="req">*</span></label>
            <input type="text" id="last_name" name="last_name" value="{{ $old('last_name') }}" class="{{ $err('last_name') ? 'input-error' : '' }}">
            @if ($err('last_name'))<div class="error">{{ $err('last_name') }}</div>@endif
        </div>
        <div class="field">
            <label for="suffix">Suffix</label>
            <input type="text" id="suffix" name="suffix" value="{{ $old('suffix') }}" placeholder="Jr., Sr., III">
        </div>
        <div class="field">
            <label for="birth_date">Birth Date</label>
            <input type="date" id="birth_date" name="birth_date" value="{{ $old('birth_date', $emp?->birth_date?->format('Y-m-d')) }}">
        </div>
        <div class="field">
            <label for="gender">Gender</label>
            <select id="gender" name="gender">
                <option value="">— Select —</option>
                <option value="Male" @selected($old('gender') === 'Male')>Male</option>
                <option value="Female" @selected($old('gender') === 'Female')>Female</option>
            </select>
        </div>
        <div class="field">
            <label for="civil_status">Civil Status</label>
            <select id="civil_status" name="civil_status">
                <option value="">— Select —</option>
                @foreach (['Single', 'Married', 'Widowed', 'Separated'] as $status)
                    <option value="{{ $status }}" @selected($old('civil_status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="citizenship">Citizenship</label>
            <input type="text" id="citizenship" name="citizenship" value="{{ $old('citizenship', 'Filipino') }}">
        </div>
        <div class="field">
            <label for="blood_type">Blood Type</label>
            <input type="text" id="blood_type" name="blood_type" value="{{ $old('blood_type') }}" placeholder="O+, A-">
        </div>
        <div class="field">
            <label for="contact_number">Contact Number</label>
            <input type="text" id="contact_number" name="contact_number" value="{{ $old('contact_number') }}">
        </div>
        <div class="field">
            <label for="personal_email">Personal Email</label>
            <input type="email" id="personal_email" name="personal_email" value="{{ $old('personal_email') }}">
        </div>
        <div class="field">
            <label for="gov_email">Gov Email</label>
            <input type="email" id="gov_email" name="gov_email" value="{{ $old('gov_email') }}" placeholder="name@dict.gov.ph">
        </div>
        <div class="field">
            <label for="residential_address">Residential Address</label>
            <input type="text" id="residential_address" name="residential_address" value="{{ $old('residential_address') }}">
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-title">Government IDs</div>
    <div class="form-grid">
        <div class="field">
            <label for="gsis_no">GSIS Number</label>
            <input type="text" id="gsis_no" name="gsis_no" value="{{ $old('gsis_no') }}">
        </div>
        <div class="field">
            <label for="philhealth_no">PhilHealth Number</label>
            <input type="text" id="philhealth_no" name="philhealth_no" value="{{ $old('philhealth_no') }}">
        </div>
        <div class="field">
            <label for="pagibig_no">PAG-IBIG Number</label>
            <input type="text" id="pagibig_no" name="pagibig_no" value="{{ $old('pagibig_no') }}">
        </div>
        <div class="field">
            <label for="tin_no">TIN</label>
            <input type="text" id="tin_no" name="tin_no" value="{{ $old('tin_no') }}">
        </div>
        <div class="field">
            <label for="sss_no">SSS Number (if applicable)</label>
            <input type="text" id="sss_no" name="sss_no" value="{{ $old('sss_no') }}">
        </div>
    </div>
</div>

<div class="form-section">
    <div class="form-section-title">Remarks</div>
    <div class="field">
        <textarea id="remarks" name="remarks" rows="3">{{ $old('remarks') }}</textarea>
    </div>
</div>
