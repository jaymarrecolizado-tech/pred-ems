@extends('layouts.app')

@section('title', 'Notification Settings')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Notification Settings']]])
@endsection

@section('content')
    <div class="card card-pad" style="margin-bottom:18px">
        <div class="overline" style="margin-bottom:6px">Notification channels</div>
        <h2 style="margin:0 0 6px">Turn notification channels on or off</h2>
        <div class="hint">
            These switches apply office-wide. The in-system inbox (bell + <a href="{{ route('notifications.index') }}">notifications page</a>)
            is always on — you can only disable the <strong>email</strong> and <strong>SMS</strong> delivery legs.
            SMS also requires a configured gateway (<code>SMS_ENABLED=true</code> in <code>.env</code>) and the recipient's
            contact number on their 201-file.
        </div>
    </div>

    <form method="POST" action="{{ route('notifications.settings.update') }}">
        @csrf

        <div class="card">
            <div class="card-pad">
                @foreach ([
                    'email' => [
                        'title' => 'Email notifications',
                        'desc' => 'Send email for document requests, leave approvals/rejections, and other lifecycle events.',
                        'hint' => 'Uses the Laravel mailer (MAIL_MAILER / SMTP config in .env).',
                    ],
                    'sms' => [
                        'title' => 'SMS notifications',
                        'desc' => 'Send SMS via the Android gateway for events that carry an SMS text (e.g. document issued).',
                        'hint' => 'Delivered asynchronously by php artisan sms:send — only when SMS_ENABLED=true and the employee has a phone on file.',
                    ],
                ] as $key => $meta)
                    <div style="display:flex; align-items:flex-start; gap:16px; padding:16px 0; {{ ! $loop->first ? 'border-top:1px solid var(--line)' : '' }}">
                        <div style="flex:1; min-width:0">
                            <div style="font-weight:600">{{ $meta['title'] }}</div>
                            <div class="hint" style="margin-top:3px">{{ $meta['desc'] }}</div>
                            <div class="hint" style="font-size:11.5px; margin-top:2px">{{ $meta['hint'] }}</div>
                        </div>
                        <label class="switch" style="flex-shrink:0; margin-top:2px">
                            <input type="checkbox" name="{{ $key }}" value="1" @checked($channels[$key])>
                            <span class="switch-track" aria-hidden="true"><span class="switch-thumb"></span></span>
                            <span class="sr-only">Toggle {{ $meta['title'] }}</span>
                        </label>
                    </div>
                @endforeach

                <div style="display:flex; gap:8px; align-items:center; margin-top:8px">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                    <span class="hint">Changes apply to new events immediately — nothing is sent retroactively.</span>
                </div>
            </div>
        </div>
    </form>

    <div class="card card-pad" style="margin-top:18px">
        <div class="overline" style="margin-bottom:8px">Current status</div>
        <div style="display:flex; gap:10px; flex-wrap:wrap">
            <span class="badge {{ $channels['email'] ? 'badge-green' : 'badge-gray' }}">Email {{ $channels['email'] ? 'ON' : 'OFF' }}</span>
            <span class="badge {{ $channels['sms'] ? 'badge-green' : 'badge-gray' }}">SMS {{ $channels['sms'] ? 'ON' : 'OFF' }}</span>
            <span class="badge badge-indigo">In-system inbox always ON</span>
        </div>
    </div>
@endsection
