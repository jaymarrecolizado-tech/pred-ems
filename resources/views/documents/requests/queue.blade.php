@extends('layouts.app')

@section('title', 'Document Requests')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Document Requests']]])
@endsection

@section('content')
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">HR fulfillment queue</div>
        <div class="hint">Issue the requested document — the system mints a reference number, generates the PDF, and records the issuance in the documents ledger. Rejected requests notify the employee with your reason.</div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Requests <span class="hint">({{ $requests->total() }})</span></h2>
            <div style="display:flex; gap:6px; flex-wrap:wrap">
                @foreach ($statuses as $value => $label)
                    <a href="{{ route('documents.requests.queue', ['status' => $value]) }}"
                       class="btn btn-sm {{ $status === $value ? 'btn-primary' : 'btn-outline' }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Document</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th class="actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>
                                <strong>{{ $request->employee->full_name }}</strong>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">{{ $request->employee->position?->title ?? '—' }}</div>
                            </td>
                            <td>
                                <strong>{{ $request->type_label }}</strong>
                                @if ($request->period)
                                    <div class="num" style="font-size:11.5px; color:var(--ink-400)">Period: {{ $request->period }}</div>
                                @endif
                                @if ($request->reference_no)
                                    <div class="num" style="font-size:11.5px; color:var(--ink-400)">Ref: {{ $request->reference_no }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="hint" style="font-size:12px">{{ \Illuminate\Support\Str::limit($request->purpose, 70) }}</span>
                                @if ($request->status === 'rejected' && $request->rejection_reason)
                                    <div class="num" style="font-size:11px; color:#b91c1c; margin-top:2px">Rejected: {{ $request->rejection_reason }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $request->status === 'issued' ? 'badge-green' : ($request->status === 'rejected' ? 'badge-red' : ($request->status === 'canceled' ? 'badge-gray' : 'badge-amber')) }}">
                                    {{ ucfirst($request->status) }}
                                </span>
                                @if ($request->processed_at)
                                    <div class="num" style="font-size:11px; color:var(--ink-400); margin-top:2px">{{ $request->processed_at->format('M j, Y g:i A') }} · {{ $request->processedBy?->name }}</div>
                                @endif
                            </td>
                            <td>{{ $request->created_at->format('M j, Y') }}</td>
                            <td class="actions">
                                @if ($request->isPending())
                                    <div style="display:flex; gap:6px; justify-content:flex-end; flex-wrap:wrap">
                                        <form method="POST" action="{{ route('documents.requests.issue', $request) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Issue</button>
                                        </form>
                                        <details style="position:relative">
                                            <summary class="btn btn-sm" style="background:#dc2626; color:#fff; cursor:pointer">Reject</summary>
                                            <form method="POST" action="{{ route('documents.requests.reject', $request) }}" class="card" style="position:absolute; right:0; top:calc(100% + 6px); z-index:20; padding:12px; width:280px; box-shadow:0 10px 30px rgba(15,23,42,.15)">
                                                @csrf
                                                <div class="field" style="margin-bottom:8px">
                                                    <label for="reason-{{ $request->id }}">Reason <span class="req">*</span></label>
                                                    <input type="text" id="reason-{{ $request->id }}" name="rejection_reason" placeholder="e.g. Records incomplete" required>
                                                </div>
                                                <button type="submit" class="btn btn-primary" style="width:100%">Confirm Rejection</button>
                                            </form>
                                        </details>
                                    </div>
                                @elseif ($request->status === 'issued')
                                    <a href="{{ route('documents.requests.download', $request) }}" class="btn btn-outline btn-sm" target="_blank">PDF</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No {{ $status }} requests.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-pad">
            {{ $requests->links() }}
        </div>
    </div>
@endsection
