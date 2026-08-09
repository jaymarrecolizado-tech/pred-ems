@extends('layouts.app')

@section('title', 'Import Attendance')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Data Imports', route('imports.index')], ['Attendance']]])
@endsection

@section('content')
    <div class="overline">Bulk data entry</div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h2>Attendance Log Import</h2>
        </div>
        <div class="card-pad">
            <form method="POST" action="{{ route('imports.attendance.preview') }}" enctype="multipart/form-data" class="filter-bar">
                @csrf
                <div class="field" style="flex:1 1 320px">
                    <label for="file">Spreadsheet file (CSV, XLS, XLSX)</label>
                    <input id="file" type="file" name="file" accept=".csv,.xls,.xlsx" required>
                    <span class="hint">Columns: employee_number, log_date (YYYY-MM-DD), am_in, am_out, pm_in, pm_out (HH:MM or blank).</span>
                    @error('file')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="form-actions" style="margin:0">
                    <button type="submit" class="btn btn-primary">Preview import</button>
                    <a href="{{ route('imports.attendance.template') }}" class="btn btn-outline">Download template</a>
                </div>
            </form>
        </div>
    </div>

    @if (! empty($rows))
        <div class="card">
            <div class="card-header">
                <h2>
                    Preview — {{ $validCount }} valid · {{ $errorCount }} with errors
                    <span class="hint">one row per employee per day</span>
                </h2>
            </div>
            <div class="card-pad" style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee No</th>
                            <th>Date</th>
                            <th>AM In</th>
                            <th>AM Out</th>
                            <th>PM In</th>
                            <th>PM Out</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            @php $d = $row['data']; @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $d['employee_number'] ?? '—' }}</td>
                                <td>{{ $d['log_date'] ?? '—' }}</td>
                                <td>{{ $d['am_in'] ?? '—' }}</td>
                                <td>{{ $d['am_out'] ?? '—' }}</td>
                                <td>{{ $d['pm_in'] ?? '—' }}</td>
                                <td>{{ $d['pm_out'] ?? '—' }}</td>
                                <td>
                                    @if (empty($row['errors']))
                                        <span class="badge badge-green">✓ Valid</span>
                                    @else
                                        <span class="badge badge-red">✗ {{ count($row['errors']) }} issue(s)</span>
                                        <div style="font-size:11.5px; color:var(--red-text); margin-top:3px">{{ implode(' ', $row['errors']) }}</div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($validCount > 0)
                <div class="card-pad" style="border-top:1px solid var(--line)">
                    <form method="POST" action="{{ route('imports.attendance.commit') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Import {{ $validCount }} day-record(s)? Rows with errors will be skipped.')">
                            Import {{ $validCount }} day-record(s)
                        </button>
                        <span class="hint">Re-importing the same employee + date replaces that day's punches.</span>
                    </form>
                </div>
            @endif
        </div>
    @endif
@endsection
