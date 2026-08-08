@extends('layouts.app')

@section('title', 'Attendance Settings')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Attendance Settings']]])
@endsection

@section('content')
    {{-- Today's schedule status --}}
    @php
        $today = \App\Support\Schedule::day(\Illuminate\Support\Carbon::now());
    @endphp
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">Today · {{ now()->format('l, F j, Y') }}</div>
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap">
            <span class="badge {{ $today['work'] ? 'badge-green' : 'badge-gray' }}">
                {{ $today['work'] ? 'Working day' : ($today['holiday_name'] ? 'Holiday' : 'Rest day') }}
            </span>
            @if ($today['holiday_name'])
                <strong>{{ $today['holiday_name'] }}</strong>
            @endif
            @if ($today['work'] && ! $today['holiday_name'])
                <span class="hint">Hours: {{ $today['am_start'] }}–{{ $today['am_end'] }} / {{ $today['pm_start'] }}–{{ $today['pm_end'] }}</span>
            @endif
            <span class="hint">Schedule: {{ $today['schedule_name'] }}@if ($today['reverted']) (reverted — rest-day holiday)@endif</span>
        </div>
    </div>

    <div class="info-grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px">
        {{-- Schedule form --}}
        <div class="card">
            <div class="card-header">
                <h2>{{ $editingSchedule ? 'Edit Work Schedule' : 'Add Work Schedule' }}</h2>
                @if ($editingSchedule)
                    <a href="{{ route('attendance.settings') }}" class="btn btn-outline btn-sm">Cancel</a>
                @endif
            </div>
            <div class="card-pad">
                <form method="POST" action="{{ $editingSchedule ? route('attendance.schedules.update', $editingSchedule) : route('attendance.schedules.store') }}" class="form-grid" style="grid-template-columns:1fr 1fr">
                    @csrf
                    @if ($editingSchedule) @method('PUT') @endif

                    <div class="field" style="grid-column:1 / -1">
                        <label for="name">Schedule Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', $editingSchedule?->name ?? '') }}" placeholder="e.g. 4-Day Compressed Workweek (Mon–Thu)" required>
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" value="{{ old('description', $editingSchedule?->description ?? '') }}" placeholder="e.g. AOM No. 2026-020 — 10 hrs/day, 40 hrs/week">
                    </div>
                    <div class="field">
                        <label for="starts_on">Effective From</label>
                        <input type="date" id="starts_on" name="starts_on" value="{{ old('starts_on', $editingSchedule?->starts_on?->toDateString() ?? '') }}">
                        <div class="hint" style="font-size:11px; margin-top:3px">Leave blank for open-ended.</div>
                    </div>
                    <div class="field">
                        <label for="ends_on">Effective Until</label>
                        <input type="date" id="ends_on" name="ends_on" value="{{ old('ends_on', $editingSchedule?->ends_on?->toDateString() ?? '') }}">
                    </div>
                    <div class="field">
                        <label for="revert_schedule_id">Revert to (rest-day holiday week)</label>
                        <select id="revert_schedule_id" name="revert_schedule_id">
                            <option value="">— Standard 8-hour (built-in) —</option>
                            @foreach ($schedules->where('id', '!=', $editingSchedule?->id) as $s)
                                <option value="{{ $s->id }}" @selected(old('revert_schedule_id', $editingSchedule?->revert_schedule_id) == $s->id)>{{ $s->name }}</option>
                            @endforeach
                        </select>
                        <div class="hint" style="font-size:11px; margin-top:3px">Used when a holiday falls on a rest day of this schedule's week (CSC Res. 2600838).</div>
                    </div>
                    <div class="field" style="display:flex; align-items:flex-end; gap:8px; padding-bottom:8px">
                        <label style="margin:0">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $editingSchedule?->is_active ?? true)) style="width:auto">
                            Active
                        </label>
                    </div>

                    <div style="grid-column:1 / -1; margin-top:4px">
                        <div class="form-section-title">Working days &amp; hours (Mon = day 1)</div>
                        @php
                            $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'];
                        @endphp
                        @foreach (range(1, 7) as $iso)
                            @php
                                $cfg = old("days.$iso", ($editingSchedule?->days ?? [])[(string) $iso] ?? ['work' => false]);
                                $work = ! empty($cfg['work']);
                            @endphp
                            <div class="schedule-day" data-iso="{{ $iso }}" style="display:flex; align-items:center; gap:10px; padding:9px 0; border-bottom:1px solid var(--line); flex-wrap:wrap">
                                <label style="display:flex; align-items:center; gap:7px; min-width:150px; margin:0; font-weight:600">
                                    <input type="checkbox" name="days[{{ $iso }}][work]" value="1" @checked($work) style="width:auto" class="day-work-toggle">
                                    {{ $dayNames[$iso] }}
                                </label>
                                <div class="day-times" style="display:flex; gap:8px; align-items:center; {{ $work ? '' : 'opacity:.45' }}">
                                    <input type="time" name="days[{{ $iso }}][am_start]" value="{{ $cfg['am_start'] ?? '08:00' }}" class="day-time" style="width:110px">
                                    <span>–</span>
                                    <input type="time" name="days[{{ $iso }}][am_end]" value="{{ $cfg['am_end'] ?? '12:00' }}" class="day-time" style="width:110px">
                                    <span>/</span>
                                    <input type="time" name="days[{{ $iso }}][pm_start]" value="{{ $cfg['pm_start'] ?? '13:00' }}" class="day-time" style="width:110px">
                                    <span>–</span>
                                    <input type="time" name="days[{{ $iso }}][pm_end]" value="{{ $cfg['pm_end'] ?? '17:00' }}" class="day-time" style="width:110px">
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div style="grid-column:1 / -1" class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ $editingSchedule ? 'Save Changes' : 'Create Schedule' }}</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Schedule list --}}
        <div class="card">
            <div class="card-header">
                <h2>Work Schedules <span class="hint">({{ $schedules->count() }})</span></h2>
            </div>
            <div class="grow-list">
                @forelse ($schedules as $schedule)
                    <li>
                        <div style="min-width:0">
                            <div style="font-weight:600">
                                {{ $schedule->name }}
                                <span class="badge {{ $schedule->is_active ? 'badge-green' : 'badge-gray' }}">{{ $schedule->is_active ? 'Active' : 'Inactive' }}</span>
                            </div>
                            <div class="num" style="font-size:11.5px; color:var(--ink-400)">
                                {{ $schedule->summary() }}
                            </div>
                            <div class="num" style="font-size:11px; color:var(--ink-400); margin-top:2px">
                                @if ($schedule->starts_on || $schedule->ends_on)
                                    {{ $schedule->starts_on?->format('M j, Y') ?? 'Open' }} → {{ $schedule->ends_on?->format('M j, Y') ?? 'Open-ended' }}
                                @else
                                    Open-ended
                                @endif
                                @if ($schedule->revertSchedule)
                                    · reverts to {{ $schedule->revertSchedule->name }}
                                @endif
                            </div>
                        </div>
                        <div style="display:flex; gap:6px; flex-shrink:0">
                            <a href="{{ route('attendance.schedules.edit', $schedule) }}" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" action="{{ route('attendance.schedules.destroy', $schedule) }}" class="inline" onsubmit="return confirm('Delete this schedule? Existing DTRs resolve against the remaining schedules.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#dc2626; color:#fff">Delete</button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="text-muted">No work schedules yet. Create the CWW and Standard schedules above.</li>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Holidays --}}
    <div class="card">
        <div class="card-header">
            <h2>Holidays &amp; Work Suspensions <span class="hint">({{ $holidays->count() }})</span></h2>
        </div>
        <div class="card-pad">
            <form method="POST" action="{{ route('attendance.holidays.store') }}" class="form-grid" style="grid-template-columns:1fr 1fr 1fr auto auto; align-items:end">
                @csrf
                <div class="field">
                    <label for="holiday_name">Name <span class="req">*</span></label>
                    <input type="text" id="holiday_name" name="name" placeholder="e.g. Bonifacio Day" required>
                </div>
                <div class="field">
                    <label for="holiday_date">Date <span class="req">*</span></label>
                    <input type="date" id="holiday_date" name="date" required>
                </div>
                <div class="field">
                    <label for="holiday_type">Type</label>
                    <select id="holiday_type" name="type">
                        @foreach (\App\Models\Holiday::TYPES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="display:flex; align-items:flex-end; padding-bottom:8px; white-space:nowrap">
                    <label style="margin:0; font-weight:600">
                        <input type="checkbox" name="is_repeating" value="1" style="width:auto">
                        Repeats yearly
                    </label>
                </div>
                <button type="submit" class="btn btn-primary" style="min-height:38px">Add Holiday</button>
            </form>

            <div class="table-wrap" style="margin-top:16px">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Repeats</th>
                            <th class="actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($holidays as $holiday)
                            <tr>
                                <td>{{ $holiday->date->format('M j, Y') }}</td>
                                <td>{{ $holiday->name }}</td>
                                <td><span class="badge badge-indigo">{{ $holiday->type_label }}</span></td>
                                <td>{{ $holiday->is_repeating ? 'Yes' : 'No' }}</td>
                                <td class="actions">
                                    <form method="POST" action="{{ route('attendance.holidays.destroy', $holiday) }}" class="inline" onsubmit="return confirm('Remove this holiday?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm" style="background:#dc2626; color:#fff">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No holidays recorded. Add regular holidays and work suspensions so the DTR can flag them and apply the Friday-revert rule.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

<script>
(function () {
    // Toggle time inputs on/off with the Working checkbox per day.
    document.querySelectorAll('.schedule-day').forEach(function (row) {
        var toggle = row.querySelector('.day-work-toggle');
        var times = row.querySelector('.day-times');
        function sync() {
            times.style.opacity = toggle.checked ? '1' : '.45';
            times.querySelectorAll('.day-time').forEach(function (input) {
                input.disabled = !toggle.checked;
            });
        }
        toggle.addEventListener('change', sync);
        sync();
    });
})();
</script>
