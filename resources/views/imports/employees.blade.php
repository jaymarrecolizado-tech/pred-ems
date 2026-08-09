@extends('layouts.app')

@section('title', 'Import Employees')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Data Imports', route('imports.index')], ['Employees']]])
@endsection

@section('content')
    <div class="overline">Bulk data entry</div>

    <div class="card" style="margin-bottom:20px">
        <div class="card-header">
            <h2>Employee Roster Import</h2>
        </div>
        <div class="card-pad">
            <form method="POST" action="{{ route('imports.employees.preview') }}" enctype="multipart/form-data" class="filter-bar">
                @csrf
                <div class="field" style="flex:1 1 320px">
                    <label for="file">Spreadsheet file (CSV, XLS, XLSX)</label>
                    <input id="file" type="file" name="file" accept=".csv,.xls,.xlsx" required>
                    <span class="hint">Header row required — download the template to see the exact columns.</span>
                    @error('file')<span class="error">{{ $message }}</span>@enderror
                </div>
                <div class="field" style="flex:0 0 300px">
                    <label for="mode">Import mode</label>
                    <select id="mode" name="mode">
                        @foreach ($modes as $value => $label)
                            <option value="{{ $value }}" @selected(($mode ?? 'upsert') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-actions" style="margin:0">
                    <button type="submit" class="btn btn-primary">Preview import</button>
                    <a href="{{ route('imports.employees.template') }}" class="btn btn-outline">Download template</a>
                </div>
            </form>
        </div>
    </div>

    @if (! empty($rows))
        <div class="card">
            <div class="card-header">
                <h2>
                    Preview — {{ $validCount }} valid · {{ $errorCount }} with errors
                    <span class="hint">rows are keyed by employee number</span>
                </h2>
            </div>
            <div class="card-pad" style="overflow-x:auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Employee No</th>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Division</th>
                            <th>Position</th>
                            <th>Status</th>
                            <th>Validation</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $index => $row)
                            @php $d = $row['data']; @endphp
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $d['employee_number'] ?: '(auto)' }}</td>
                                <td>{{ trim(($d['first_name'] ?? '').' '.($d['last_name'] ?? '')) }}</td>
                                <td>{{ $d['employment_type'] ?? '—' }}</td>
                                <td>{{ $d['division'] ?? '—' }}</td>
                                <td>{{ $d['position'] ?? '—' }}</td>
                                <td>{{ $d['status'] ?? 'active' }}</td>
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
                    <form method="POST" action="{{ route('imports.employees.commit') }}">
                        @csrf
                        <input type="hidden" name="token" value="{{ $token }}">
                        <input type="hidden" name="mode" value="{{ $mode }}">
                        <button type="submit" class="btn btn-primary" onclick="return confirm('Import {{ $validCount }} valid row(s)? Rows with errors will be skipped.')">
                            Import {{ $validCount }} valid row(s)
                        </button>
                        <span class="hint">Rows with errors are skipped — fix them in the file and re-upload.</span>
                    </form>
                </div>
            @endif
        </div>
    @endif
@endsection
