@php
    $size = $size ?? 34;
    $employee = $employee ?? null;
    $photo = $photo ?? ($employee ? $employee->photo_url : null);
    $initials = $initials ?? ($employee ? $employee->initials : '?');
    $name = $name ?? ($employee ? $employee->full_name : '');
@endphp
@if ($photo)
    <img src="{{ $photo }}" alt="{{ $name }}" class="avatar avatar-img" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(11, $size * 0.36) }}px" loading="lazy">
@else
    <div class="avatar" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(11, $size * 0.36) }}px">{{ $initials }}</div>
@endif
