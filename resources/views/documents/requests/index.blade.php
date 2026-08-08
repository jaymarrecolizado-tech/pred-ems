@extends('layouts.app')

@section('title', 'My Documents')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Documents']]])
@endsection

@section('content')
    <div class="card card-pad" style="margin-bottom:18px; display:flex; align-items:center; gap:14px; flex-wrap:wrap">
        <div style="min-width:0; flex:1">
            <div class="overline" style="margin-bottom:6px">Document request self-service</div>
            <div class="hint">Request an official document — it lands in the HR queue, is issued with a reference number, and appears below once ready.</div>
        </div>
        <a href="{{ route('documents.requests.create') }}" class="btn btn-primary">＋ Request a Document</a>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>My Requests <span class="hint">({{ $requests->total() }})</span></h2>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Reference No.</th>
                        <th>Requested</th>
                        <th class="actions"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $request)
                        <tr>
                            <td>
                                <strong>{{ $request->type_label }}</strong>
                                @if ($request->period)
                                    <div class="num" style="font-size:11.5px; color:var(--ink-400)">Period: {{ $request->period }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="hint" style="font-size:12px">{{ \Illuminate\Support\Str::limit($request->purpose, 60) }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $request->status === 'issued' ? 'badge-green' : ($request->status === 'rejected' ? 'badge-red' : ($request->status === 'canceled' ? 'badge-gray' : 'badge-amber')) }}">
                                    {{ ucfirst($request->status) }}
                                </span>
                                @if ($request->status === 'rejected' && $request->rejection_reason)
                                    <div class="num" style="font-size:11px; color:#b91c1c; margin-top:2px">{{ $request->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="num">{{ $request->reference_no ?? '—' }}</td>
                            <td>{{ $request->created_at->format('M j, Y') }}</td>
                            <td class="actions">
                                @if ($request->status === 'issued')
                                    <a href="{{ route('documents.requests.download', $request) }}" class="btn btn-outline btn-sm" target="_blank">PDF</a>
                                @elseif ($request->status === 'pending')
                                    <form method="POST" action="{{ route('documents.requests.cancel', $request) }}" class="inline" onsubmit="return confirm('Cancel this request?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm" style="background:#dc2626; color:#fff">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">No document requests yet. Click <strong>Request a Document</strong> to ask HR for a COE, Service Record, Leave Balances, No Pending Case, or DTR.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-pad">
            {{ $requests->links() }}
        </div>
    </div>
@endsection
