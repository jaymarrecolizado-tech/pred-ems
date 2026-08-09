@extends('layouts.app')

@section('title', 'Documents Issued Report')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Reports', route('reports.index')], ['Documents Issued']]])
@endsection

@section('content')
    <form method="GET" action="{{ route('reports.documents') }}" class="filter-bar">
        <div class="field" style="flex:0 0 220px">
            <label for="type">Document Type</label>
            <select id="type" name="type">
                @foreach ($types as $value => $label)
                    <option value="{{ $value }}" @selected($type === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-actions" style="margin:0">
            <button type="submit" class="btn btn-primary">Filter</button>
            @include('partials.report-exports', [
                'route' => 'reports.documents',
                'params' => ['type' => $type],
            ])
        </div>
    </form>

    <div class="card">
        <div class="card-header">
            <h2>Documents Issued <span class="hint">({{ $documents->total() }} issuances)</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Reference No</th>
                        <th>Document</th>
                        <th>Employee</th>
                        <th>Issued By</th>
                        <th>Date Issued</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($documents as $document)
                        <tr>
                            <td class="num"><strong>{{ $document->reference_no }}</strong></td>
                            <td><span class="badge badge-blue">{{ $document->document_type_label }}</span></td>
                            <td>{{ $document->employee?->full_name ?? '—' }}</td>
                            <td>{{ $document->generatedBy?->name ?? '—' }}</td>
                            <td style="white-space:nowrap">{{ $document->generated_at->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-muted">No documents issued yet. Generate a Service Record or COE from any employee profile.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination">
            {{ $documents->links('vendor.pagination.custom') }}
        </div>
    </div>
@endsection
