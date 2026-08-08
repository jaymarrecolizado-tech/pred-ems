@extends('layouts.app')

@section('title', $employee->full_name)

@section('content')
    <div class="profile-header">
        @include('partials.avatar', ['employee' => $employee, 'size' => 76])
        <div>
            <h2>{{ $employee->full_name }}</h2>
            <div class="meta">{{ $employee->employee_number }} · {{ $employee->position?->title ?? 'No position' }} · <strong>{{ $employee->years_of_service }}</strong> in service</div>
            <div class="badges">
                @if ($employee->employmentType)
                    @include('employees.partials.type_badge', ['type' => $employee->employmentType])
                @endif
                <span class="badge {{ $employee->status === 'active' ? 'badge-green' : 'badge-amber' }}">{{ $employee->status_label }}</span>
                @if ($employee->division)
                    <span class="badge badge-blue">{{ $employee->division->name }}</span>
                @endif
            </div>
            <div class="profile-complete">
                <span class="k">201-file completeness</span>
                <span class="progress" style="width:180px"><i style="width:{{ $employee->profile_completeness }}%"></i></span>
                <span class="v">{{ $employee->profile_completeness }}%</span>
            </div>
        </div>
        <div style="margin-left:auto; display:flex; gap:8px">
            @if (auth()->user()->hasAnyRole(['admin', 'hr']))
                <a href="{{ route('employees.edit', $employee) }}" class="btn btn-outline btn-sm" style="color:#fff; border-color:rgba(255,255,255,.35)">Edit</a>
                <form method="POST" action="{{ route('employees.destroy', $employee) }}"
                      onsubmit="return confirm('Delete this employee record? This cannot be undone.')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm" style="background:rgba(220,38,38,.85); color:#fff">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <div class="info-grid">
        <div class="card card-pad">
            <div class="card-header" style="padding:0 0 12px; border-bottom:1px solid var(--line)">
                <h2>Personal Information</h2>
            </div>
            <div class="info-list mt-16">
                <div class="item"><span class="k">Birth Date</span><span class="v">{{ $employee->birth_date?->format('F d, Y') ?? '—' }}</span></div>
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
                <div class="item"><span class="k">GSIS BP No.</span><span class="v">{{ $employee->bp_number ?? '—' }}</span></div>
                <div class="item"><span class="k">Plantilla Item No.</span><span class="v">{{ $employee->plantilla_item_no ?? '—' }}</span></div>
                <div class="item"><span class="k">Source of Fund</span><span class="v">{{ $employee->source_of_fund ?? '—' }}</span></div>
                <div class="item"><span class="k">Original Appointment</span><span class="v">{{ $employee->date_original_appointment?->format('F d, Y') ?? '—' }}</span></div>
                <div class="item"><span class="k">Last Promotion</span><span class="v">{{ $employee->date_last_promotion?->format('F d, Y') ?? '—' }}</span></div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top:18px">
        <div class="card-header">
            <h2>Appointment History <span class="hint">(source of the future Service Record)</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Position</th>
                        <th>Appointment Type</th>
                        <th>Grade/Step</th>
                        <th class="num">Monthly Salary</th>
                        <th>Effective From</th>
                        <th>Effective To</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employee->appointments as $appointment)
                        <tr>
                            <td>{{ $appointment->position?->title ?? '—' }}</td>
                            <td><span class="badge badge-blue">{{ $appointment->appointment_type_label }}</span></td>
                            <td>{{ $appointment->salary_grade ? "SG {$appointment->salary_grade} / Step {$appointment->step}" : '—' }}</td>
                            <td class="num">{{ $appointment->monthly_salary ? '₱' . number_format((float) $appointment->monthly_salary, 2) : '—' }}</td>
                            <td>{{ $appointment->effective_from->format('F d, Y') }}</td>
                            <td>{{ $appointment->effective_to?->format('F d, Y') ?? 'Present' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No appointment records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($employee->educations->isNotEmpty() || $employee->civilServiceEligibilities->isNotEmpty())
        <div class="card" style="margin-top:18px">
            <div class="card-header">
                <h2>Qualifications <span class="hint">(201 file)</span></h2>
            </div>
            <div class="card-pad">
                @foreach ($employee->educations as $education)
                    <div class="info-list" style="margin:0 0 10px">
                        <div class="item"><span class="k">Education</span><span class="v">{{ $education->level }}{{ $education->course ? ' · ' . $education->course : '' }}</span></div>
                    </div>
                @endforeach
                @if ($employee->civilServiceEligibilities->isNotEmpty())
                    <div class="info-list" style="margin:0">
                        <div class="item"><span class="k">Eligibility</span><span class="v">{{ $employee->civilServiceEligibilities->pluck('eligibility')->join(', ') }}</span></div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <div class="card" style="margin-top:18px">
        <div class="card-header">
            <h2>Leave Balances <span class="hint">(from the credit ledger)</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Leave Type</th>
                        <th class="num">Balance (days)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($leaveBalances as $row)
                        <tr>
                            <td>{{ $row->leave_type->name }}</td>
                            <td class="num"><strong>{{ number_format($row->balance, 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-muted">No leave credits for this employee.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
