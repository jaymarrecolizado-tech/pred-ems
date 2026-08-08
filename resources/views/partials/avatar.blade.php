@php
    $size = $size ?? 34;
    $employee = $employee ?? null;
    $photo = $photo ?? ($employee ? $employee->photo_url : null);
    $initials = $initials ?? ($employee ? $employee->initials : '?');
    $name = $name ?? ($employee ? $employee->full_name : '');

    // Deterministic hue from the name so the same person always gets the same color.
    $palette = ['#2563eb', '#7c3aed', '#0d9488', '#db2777', '#d97706', '#4f46e5', '#0891b2', '#be185d'];
    $hash = 0;
    foreach (str_split($name ?: $initials) as $ch) {
        $hash = (($hash << 5) - $hash) + ord($ch);
        $hash = $hash & 0x7fffffff;
    }
    $avatarColor = $palette[$hash % count($palette)];
@endphp
@if ($photo)
    <img src="{{ $photo }}" alt="{{ $name }}" class="avatar avatar-img" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(11, $size * 0.36) }}px" loading="lazy">
@else
    <div class="avatar" style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(11, $size * 0.36) }}px;background:{{ $avatarColor }}">{{ $initials }}</div>
@endif
