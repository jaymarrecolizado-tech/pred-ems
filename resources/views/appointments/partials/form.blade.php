@php
    $appt = $appointment ?? null;
    $old = fn (string $key, mixed $default = '') => old($key, $appt?->{$key} ?? $default);
    $err = fn (string $key) => $errors->first($key);
@endphp

<div class="form-section">
    <div class="form-section-title">Appointment Details</div>
    <div class="form-grid">
        <div class="field">
            <label for="appointment_type">Appointment Type <span class="req">*</span></label>
            <select id="appointment_type" name="appointment_type" class="{{ $err('appointment_type') ? 'input-error' : '' }}">
                @foreach ($appointmentTypes as $value => $label)
                    <option value="{{ $value }}" @selected(old('appointment_type', $appt?->appointment_type) === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($err('appointment_type'))<div class="error">{{ $err('appointment_type') }}</div>@endif
        </div>
        <div class="field">
            <label for="appointment_status">Status <span class="req">*</span></label>
            <select id="appointment_status" name="appointment_status" class="{{ $err('appointment_status') ? 'input-error' : '' }}">
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}" @selected(old('appointment_status', $appt?->appointment_status ?? 'approved') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @if ($err('appointment_status'))<div class="error">{{ $err('appointment_status') }}</div>@endif
        </div>
        <div class="field">
            <label for="position_id">Position</label>
            <select id="position_id" name="position_id">
                <option value="">— Select —</option>
                @foreach ($positions as $position)
                    <option value="{{ $position->id }}" @selected(old('position_id', $appt?->position_id) == $position->id)>{{ $position->title }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="division_id">Division</label>
            <select id="division_id" name="division_id">
                <option value="">— Select —</option>
                @foreach ($divisions as $division)
                    <option value="{{ $division->id }}" @selected(old('division_id', $appt?->division_id) == $division->id)>{{ $division->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="employment_type_id">Employment Type</label>
            <select id="employment_type_id" name="employment_type_id">
                <option value="">— Select —</option>
                @foreach ($employmentTypes as $type)
                    <option value="{{ $type->id }}" @selected(old('employment_type_id', $appt?->employment_type_id) == $type->id)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="field">
            <label for="effective_from">Effective From <span class="req">*</span></label>
            <input type="date" id="effective_from" name="effective_from" value="{{ $old('effective_from', $appt?->effective_from?->format('Y-m-d')) }}" class="{{ $err('effective_from') ? 'input-error' : '' }}">
            @if ($err('effective_from'))<div class="error">{{ $err('effective_from') }}</div>@endif
        </div>
        <div class="field">
            <label for="effective_to">Effective To <span class="hint" style="text-transform:none; letter-spacing:0">(blank = current)</span></label>
            <input type="date" id="effective_to" name="effective_to" value="{{ $old('effective_to', $appt?->effective_to?->format('Y-m-d')) }}">
            @if ($err('effective_to'))<div class="error">{{ $err('effective_to') }}</div>@endif
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
        <div class="field" style="grid-column:1 / -1">
            <label for="remarks">Remarks</label>
            <textarea id="remarks" name="remarks" rows="3" placeholder="e.g. Step increment per SSL V, promoted to…">{{ $old('remarks') }}</textarea>
        </div>
    </div>
</div>
