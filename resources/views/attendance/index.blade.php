@extends('layouts.app')

@section('title', 'My Attendance')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Attendance']]])
@endsection

@section('content')
    <div class="info-grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px">
        {{-- Punch card --}}
        <div class="card">
            <div class="card-header">
                <h2>Time Log — Today</h2>
                <span class="hint" id="clock" style="font-variant-numeric:tabular-nums">{{ now()->format('h:i:s A') }}</span>
            </div>
            <div class="card-pad">
                <div class="grow-list" style="margin-bottom:16px">
                    @foreach (['am_in' => 'AM In', 'am_out' => 'AM Out', 'pm_in' => 'PM In', 'pm_out' => 'PM Out'] as $type => $label)
                        <li>
                            <span>{{ $label }}</span>
                            <strong style="font-variant-numeric:tabular-nums">{{ isset($punches[$type]) ? \Illuminate\Support\Carbon::parse($punches[$type])->format('h:i A') : '—' }}</strong>
                        </li>
                    @endforeach
                </div>

                <button type="button" id="punch-btn" class="btn btn-primary btn-block" style="min-height:48px; font-size:15px">
                    Punch In / Out
                </button>
                <div class="hint" style="margin-top:12px; font-size:12px">
                    Your GPS position is verified against the nearest active checkpoint before the punch is accepted.
                    @if (isset($todaySchedule))
                        @if ($todaySchedule['holiday_name'])
                            <strong>Today: {{ $todaySchedule['holiday_name'] }}</strong> (punch if reporting for duty — hours render as overtime).
                        @elseif (! $todaySchedule['work'])
                            <strong>Today is a rest day.</strong> Punches still log and count toward CTO credit.
                        @else
                            Schedule: {{ $todaySchedule['am_start'] }}–{{ $todaySchedule['am_end'] }} / {{ $todaySchedule['pm_start'] }}–{{ $todaySchedule['pm_end'] }}.
                        @endif
                    @else
                        Work hours: {{ $officeHours['am_start'] }}–{{ $officeHours['am_end'] }} / {{ $officeHours['pm_start'] }}–{{ $officeHours['pm_end'] }}.
                    @endif
                </div>
            </div>
        </div>

        {{-- Corrections card --}}
        <div class="card">
            <div class="card-header">
                <h2>Request a Correction</h2>
            </div>
            <div class="card-pad">
                <form id="correction-form" class="form-grid" style="grid-template-columns:1fr 1fr">
                    <div class="field">
                        <label for="corr_date">Date</label>
                        <input type="date" id="corr_date" name="log_date" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="field">
                        <label for="corr_type">Punch</label>
                        <select id="corr_type" name="punch_type" required>
                            <option value="am_in">AM In</option>
                            <option value="am_out">AM Out</option>
                            <option value="pm_in">PM In</option>
                            <option value="pm_out">PM Out</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="corr_time">Corrected Time</label>
                        <input type="time" id="corr_time" name="requested_time" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="corr_reason">Reason <span class="req">*</span></label>
                        <textarea id="corr_reason" name="reason" rows="2" placeholder="e.g. Forgot to punch out before lunch, GPS outside radius, official travel…" required></textarea>
                    </div>
                    <div style="grid-column:1 / -1">
                        <button type="submit" class="btn btn-primary">Submit to HR</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- My recent correction requests --}}
    @if ($corrections->isNotEmpty())
        <div class="card" style="margin-top:18px">
            <div class="card-header">
                <h2>My Correction Requests</h2>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Punch</th>
                            <th>Requested Time</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>HR Note</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($corrections as $correction)
                            <tr>
                                <td>{{ $correction->log_date->format('M d, Y') }}</td>
                                <td>{{ $correction->punch_type_label }}</td>
                                <td class="num">{{ \Illuminate\Support\Carbon::parse($correction->requested_time)->format('h:i A') }}</td>
                                <td>{{ $correction->reason }}</td>
                                <td><span class="badge {{ $correction->status_badge }}">{{ $correction->status }}</span></td>
                                <td>{{ $correction->denial_reason ?? ($correction->status === 'approved' ? 'Applied to timelog' : '—') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- DTR preview --}}
    <div class="card" style="margin-top:18px">
        <div class="card-header">
            <h2>Daily Time Record — {{ $dtr['monthLabel'] }} <span class="hint">(CSC Form 48 · {{ $dtr['totals']['present'] }} days present, {{ number_format($dtr['totals']['hours'], 2) }} hrs)</span></h2>
            <div class="panel-actions">
                <a href="{{ route('attendance.dtr', ['month' => now()->month, 'year' => now()->year]) }}" class="btn btn-outline btn-sm" target="_blank">View</a>
                <a href="{{ route('attendance.dtr.pdf', ['month' => now()->month, 'year' => now()->year]) }}" class="btn btn-primary btn-sm">Download PDF</a>
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Day</th>
                        <th class="num">AM In</th>
                        <th class="num">AM Out</th>
                        <th class="num">PM In</th>
                        <th class="num">PM Out</th>
                        <th class="num">Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($dtr['days'] as $row)
                        <tr style="{{ $row['is_weekend'] ? 'opacity:.55' : '' }}">
                            <td>{{ $row['day'] }} {{ $row['is_weekend'] ? '(wknd)' : '' }}</td>
                            <td class="num">{{ $row['am_in']?->format('h:i A') ?? '—' }}</td>
                            <td class="num">{{ $row['am_out']?->format('h:i A') ?? '—' }}</td>
                            <td class="num">{{ $row['pm_in']?->format('h:i A') ?? '—' }}</td>
                            <td class="num">{{ $row['pm_out']?->format('h:i A') ?? '—' }}</td>
                            <td class="num">{{ $row['hours'] > 0 ? number_format($row['hours'], 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

<script>
(function () {
    const punchBtn = document.getElementById('punch-btn');
    const clockEl = document.getElementById('clock');

    // Live clock
    setInterval(() => {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-US', { hour12: true });
    }, 1000);

    punchBtn.addEventListener('click', function () {
        punchBtn.disabled = true;
        window.hrisToast('Requesting your location…', 'info');

        if (!navigator.geolocation) {
            window.hrisToast('Geolocation is not supported by this browser. Please use a modern browser on your phone or contact HR.', 'error');
            punchBtn.disabled = false;
            return;
        }

        navigator.geolocation.getCurrentPosition(
            function (position) {
                fetch('{{ route('attendance.punch') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        latitude: position.coords.latitude,
                        longitude: position.coords.longitude
                    })
                })
                .then(r => r.json().then(data => ({ ok: r.ok, data })))
                .then(({ ok, data }) => {
                    if (ok) {
                        window.hrisToast(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1200);
                    } else {
                        window.hrisToast(data.message, 'error');
                        punchBtn.disabled = false;
                    }
                })
                .catch(() => {
                    window.hrisToast('Network error. Please try again.', 'error');
                    punchBtn.disabled = false;
                });
            },
            function (err) {
                let msg = 'Location unavailable. Please enable location access and retry, or contact HR.';
                if (err.code === 1) msg = 'Location access was denied. Enable GPS/location for this site and retry, or contact HR for a manual entry.';
                if (err.code === 2) msg = 'Location unavailable right now. Check your GPS signal and retry.';
                window.hrisToast(msg, 'error');
                punchBtn.disabled = false;
            },
            { enableHighAccuracy: true, timeout: 15000, maximumAge: 30000 }
        );
    });

    // Correction request
    const corrForm = document.getElementById('correction-form');

    corrForm.addEventListener('submit', function (e) {
        e.preventDefault();

        fetch('{{ route('attendance.corrections.request') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify(Object.fromEntries(new FormData(corrForm)))
        })
        .then(r => r.json().then(data => ({ ok: r.ok, data })))
        .then(({ ok, data }) => {
            if (ok) {
                window.hrisToast(data.message, 'success');
                corrForm.reset();
                setTimeout(() => window.location.reload(), 1000);
            } else {
                const msg = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                window.hrisToast(msg || 'Could not submit request.', 'error');
            }
        })
        .catch(() => window.hrisToast('Network error. Please try again.', 'error'));
    });
})();
</script>

