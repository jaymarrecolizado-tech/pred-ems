@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <div class="profile-header">
        @include('partials.avatar', ['employee' => $employee, 'size' => 76])
        <div>
            <h2>{{ $employee ? $employee->full_name : auth()->user()->name }}</h2>
            @if ($employee)
                <div class="meta">{{ $employee->employee_number }} · {{ $employee->position?->title ?? 'No position' }} · <strong>{{ $employee->years_of_service }}</strong> in service</div>
                <div class="badges">
                    @if ($employee->employmentType)
                        @include('employees.partials.type_badge', ['type' => $employee->employmentType])
                    @endif
                    <span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $employee->status_label }}</span>
                </div>
            @else
                <div class="meta">{{ auth()->user()->email }}</div>
                <div class="badges"><span class="badge badge-gray">No 201-file linked</span></div>
            @endif
        </div>
        <div style="margin-left:auto; display:flex; gap:8px">
            @if ($employee)
                <a href="{{ route('profile.edit') }}" class="btn btn-outline btn-sm" style="color:#fff; border-color:rgba(255,255,255,.35)">Edit Personal Info</a>
            @endif
            <a href="{{ route('profile.password') }}" class="btn btn-sm" style="background:#fbbf24; color:#0f172a">Change Password</a>
        </div>
    </div>

    @if ($employee)
        <a href="{{ route('employees.show', $employee) }}" class="btn btn-outline btn-sm" style="margin-bottom:18px">View full 201-file record →</a>
    @endif

    @if (! $employee)
        <div class="card card-pad">
            <h2 style="margin:0 0 8px">No employee record linked</h2>
            <p class="text-muted" style="margin:0">Your login is not yet connected to an employee 201-file profile. Contact the HR office so they can link your account. You can still manage your account security below.</p>
        </div>
    @endif

    @if ($employee)
        <div class="info-grid">
            <div class="card card-pad">
                <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--line)">
                    <h2>Profile Photo</h2>
                </div>
                <div style="display:flex; align-items:center; gap:18px; margin-top:14px">
                    @include('partials.avatar', ['employee' => $employee, 'size' => 96])
                    <div style="flex:1">
                        <form method="POST" action="{{ route('profile.photo.upload') }}" enctype="multipart/form-data" class="photo-upload-form">
                            @csrf
                            <label class="file-btn">
                                Choose photo…
                                <input type="file" name="photo" accept="image/png,image/jpeg,image/webp" hidden>
                            </label>
                            <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                            @if ($employee->profile_photo_path)
                                <button type="submit" form="remove-photo-form" class="btn btn-outline btn-sm">Remove</button>
                            @endif
                            <div class="error">@error('photo'){{ $message }}@enderror</div>
                        </form>
                        @if ($employee->profile_photo_path)
                            <form id="remove-photo-form" method="POST" action="{{ route('profile.photo.remove') }}">
                                @csrf
                                @method('DELETE')
                            </form>
                        @endif
                        <p class="text-muted" style="font-size:12px; margin:8px 0 0">JPG, PNG or WebP · max 2 MB.</p>
                    </div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--line)">
                    <h2>Personal Information</h2>
                    <a href="{{ route('profile.edit') }}" class="btn btn-outline btn-sm">Edit</a>
                </div>
                <div class="info-list mt-16">
                    <div class="item"><span class="k">Full Name</span><span class="v">{{ $employee->full_name }}</span></div>
                    <div class="item"><span class="k">Birth Date</span><span class="v">{{ $employee->birth_date?->format('F d, Y') ?? '—' }}</span></div>
                    <div class="item"><span class="k">Birth Place</span><span class="v">{{ $employee->birth_place ?? '—' }}</span></div>
                    <div class="item"><span class="k">Gender</span><span class="v">{{ $employee->gender ?? '—' }}</span></div>
                    <div class="item"><span class="k">Civil Status</span><span class="v">{{ $employee->civil_status ?? '—' }}</span></div>
                    <div class="item"><span class="k">Citizenship</span><span class="v">{{ $employee->citizenship ?? '—' }}</span></div>
                    <div class="item"><span class="k">Blood Type</span><span class="v">{{ $employee->blood_type ?? '—' }}</span></div>
                    <div class="item"><span class="k">Contact Number</span><span class="v">{{ $employee->contact_number ?? '—' }}</span></div>
                    <div class="item"><span class="k">Personal Email</span><span class="v">{{ $employee->personal_email ?? '—' }}</span></div>
                    <div class="item"><span class="k">Gov Email</span><span class="v">{{ $employee->gov_email ?? '—' }}</span></div>
                    <div class="item"><span class="k">Address</span><span class="v">{{ $employee->residential_address ?? '—' }}</span></div>
                </div>
            </div>
        </div>

        <div class="info-grid" style="margin-top:18px">
            <div class="card card-pad">
                <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--line)">
                    <h2>Government IDs</h2>
                </div>
                <div class="info-list mt-16">
                    <div class="item"><span class="k">GSIS No.</span><span class="v">{{ $employee->gsis_no ?? '—' }}</span></div>
                    <div class="item"><span class="k">PhilHealth No.</span><span class="v">{{ $employee->philhealth_no ?? '—' }}</span></div>
                    <div class="item"><span class="k">PAG-IBIG No.</span><span class="v">{{ $employee->pagibig_no ?? '—' }}</span></div>
                    <div class="item"><span class="k">TIN</span><span class="v">{{ $employee->tin_no ?? '—' }}</span></div>
                    <div class="item"><span class="k">SSS No.</span><span class="v">{{ $employee->sss_no ?? '—' }}</span></div>
                </div>
            </div>

            <div class="card card-pad">
                <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--line)">
                    <h2>Employment <span class="hint">(HR-managed)</span></h2>
                </div>
                <div class="info-list mt-16">
                    <div class="item"><span class="k">Employment Type</span><span class="v">{{ $employee->employmentType?->name ?? '—' }}</span></div>
                    <div class="item"><span class="k">Division</span><span class="v">{{ $employee->division?->name ?? '—' }}</span></div>
                    <div class="item"><span class="k">Position</span><span class="v">{{ $employee->position?->title ?? '—' }}</span></div>
                    <div class="item"><span class="k">Salary Grade / Step</span><span class="v">{{ $employee->salary_grade ? "SG {$employee->salary_grade} / Step {$employee->step}" : '—' }}</span></div>
                    <div class="item"><span class="k">Monthly Salary</span><span class="v">{{ $employee->monthly_salary ? '₱' . number_format((float) $employee->monthly_salary, 2) : '—' }}</span></div>
                    <div class="item"><span class="k">Original Appointment</span><span class="v">{{ $employee->date_original_appointment?->format('F d, Y') ?? '—' }}</span></div>
                </div>
            </div>
        </div>
    @endif

    <div class="card" style="margin-top:18px">
        <div class="card-header">
            <h2>Account &amp; Security</h2>
        </div>
        <div class="card-pad">
            <div class="info-list">
                <div class="item"><span class="k">Login Email</span><span class="v">{{ auth()->user()->email }}</span></div>
                <div class="item"><span class="k">Password</span><span class="v">•••••••• <a href="{{ route('profile.password') }}" class="btn btn-outline btn-sm" style="margin-left:6px">Change</a></span></div>
            </div>
        </div>
    </div>
@endsection
