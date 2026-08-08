@extends('layouts.app')

@section('title', 'Request a Document')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['My Documents', route('documents.requests')], ['Request a Document']]])
@endsection

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card" style="max-width:640px">
        <div class="card-header">
            <h2>Request a Document</h2>
        </div>
        <div class="card-pad">
            <form method="POST" action="{{ route('documents.requests.store') }}" class="form-grid" style="grid-template-columns:1fr">
                @csrf

                <div class="field">
                    <label for="document_type">Document <span class="req">*</span></label>
                    <select id="document_type" name="document_type" required>
                        <option value="">— Select a document —</option>
                        @foreach (\App\Models\DocumentRequest::TYPES as $key => $meta)
                            <option value="{{ $key }}" @selected(old('document_type') === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field" id="period-field" style="display:none">
                    <label for="period">For the month of <span class="req">*</span></label>
                    <input type="month" id="period" name="period" value="{{ old('period') }}">
                    <div class="hint" style="font-size:11px; margin-top:3px">Daily Time Records are issued per calendar month.</div>
                </div>

                <div class="field">
                    <label for="purpose">Purpose <span class="req">*</span></label>
                    <textarea id="purpose" name="purpose" rows="4" placeholder="e.g. Loan application, government transaction, clearance, GSIS claims…" required>{{ old('purpose') }}</textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                    <a href="{{ route('documents.requests') }}" class="btn btn-ghost">Back</a>
                </div>
            </form>
        </div>
    </div>
@endsection

<script>
(function () {
    var select = document.getElementById('document_type');
    var period = document.getElementById('period-field');
    var input = document.getElementById('period');

    function sync() {
        var isDtr = select.value === 'dtr';
        period.style.display = isDtr ? 'block' : 'none';
        input.required = isDtr;
    }

    select.addEventListener('change', sync);
    sync();
})();
</script>
