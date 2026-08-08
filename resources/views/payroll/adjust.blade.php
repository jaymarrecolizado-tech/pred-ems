@extends('layouts.app')

@section('title', 'Adjust Payroll Item')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Payroll', route('payroll.index')], [$item->period->name, route('payroll.show', $item->period)], ['Adjust ' . $item->employee->full_name]]])
@endsection

@section('content')
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">Adjust payroll item</div>
        <h2 style="margin:0 0 4px">{{ $item->employee->full_name }}</h2>
        <div class="hint">
            {{ $item->employee->position?->title ?? '—' }} · {{ $item->employee->division?->name ?? '—' }} ·
            {{ $item->period->name }}
        </div>
        <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px">
            <div class="stat-tile" style="margin:0">
                <div class="stat-tile-label">Basic salary</div>
                <div class="stat-tile-value" style="font-size:16px">₱{{ number_format((float) $item->basic_salary, 2) }}</div>
            </div>
            <div class="stat-tile" style="margin:0">
                <div class="stat-tile-label">PERA</div>
                <div class="stat-tile-value" style="font-size:16px">₱{{ number_format((float) $item->pera, 2) }}</div>
            </div>
            <div class="stat-tile" style="margin:0">
                <div class="stat-tile-label">Current gross</div>
                <div class="stat-tile-value" style="font-size:16px">₱{{ number_format((float) $item->gross_amount, 2) }}</div>
            </div>
            <div class="stat-tile" style="margin:0">
                <div class="stat-tile-label">Current net</div>
                <div class="stat-tile-value" style="font-size:16px; color:var(--brand-600)">₱{{ number_format((float) $item->net_amount, 2) }}</div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('payroll.adjust.update', $item) }}" id="adjust-form">
        @csrf

        <div class="card">
            <div class="card-header">
                <h2>Additional Income</h2>
                <span class="hint">Added to gross and included in the BIR withholding tax base. Leave blank or 0 to skip.</span>
            </div>
            <div class="card-pad">
                <div class="form-grid">
                    <div class="field">
                        <label for="honoraria">Honoraria <span class="req">₱</span></label>
                        <input id="honoraria" name="honoraria" type="number" step="0.01" min="0"
                               value="{{ old('honoraria', $item->honoraria > 0 ? number_format((float) $item->honoraria, 2, '.', '') : '') }}"
                               placeholder="0.00">
                        @error('honoraria') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="overtime_pay">Overtime Pay <span class="req">₱</span></label>
                        <input id="overtime_pay" name="overtime_pay" type="number" step="0.01" min="0"
                               value="{{ old('overtime_pay', $item->overtime_pay > 0 ? number_format((float) $item->overtime_pay, 2, '.', '') : '') }}"
                               placeholder="0.00">
                        @error('overtime_pay') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="other_income">Other Income <span class="req">₱</span></label>
                        <input id="other_income" name="other_income" type="number" step="0.01" min="0"
                               value="{{ old('other_income', $item->other_income > 0 ? number_format((float) $item->other_income, 2, '.', '') : '') }}"
                               placeholder="0.00">
                        @error('other_income') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:18px">
            <div class="card-header">
                <h2>Additional Deductions</h2>
                <span class="hint">Reduced from net pay. Leave blank or 0 to skip.</span>
            </div>
            <div class="card-pad">
                <div class="form-grid">
                    <div class="field">
                        <label for="lwop">Leave Without Pay (LWOP) <span class="req">₱</span></label>
                        <input id="lwop" name="lwop" type="number" step="0.01" min="0"
                               value="{{ old('lwop', $item->lwop_deduction > 0 ? number_format((float) $item->lwop_deduction, 2, '.', '') : '') }}"
                               placeholder="0.00">
                        <div class="hint" style="margin-top:4px">Amount = days on LWOP × daily rate (monthly salary ÷ 22).</div>
                        @error('lwop') <div class="error">{{ $message }}</div> @enderror
                    </div>

                    <div class="field">
                        <label for="other_deductions">Other Deductions <span class="req">₱</span></label>
                        <input id="other_deductions" name="other_deductions" type="number" step="0.01" min="0"
                               value="{{ old('other_deductions', $item->other_deductions > 0 ? number_format((float) $item->other_deductions, 2, '.', '') : '') }}"
                               placeholder="0.00">
                        @error('other_deductions') <div class="error">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-pad" style="margin-top:18px">
            <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap">
                <div>
                    <div class="overline" style="margin-bottom:2px">On save</div>
                    <div class="hint" style="font-size:12px">GSIS, PhilHealth, PAG-IBIG and BIR withholding are recomputed automatically with the new lines; the full computation trace is re-persisted on the item.</div>
                </div>
                <div style="margin-left:auto; display:flex; gap:8px">
                    <a href="{{ route('payroll.show', $item->period) }}" class="btn btn-outline">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Adjustments</button>
                </div>
            </div>
        </div>
    </form>
@endsection
