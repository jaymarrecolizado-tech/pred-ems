@php
    $typeColors = [
        'PERMANENT' => 'badge-green',
        'TEMPORARY' => 'badge-blue',
        'CASUAL' => 'badge-teal',
        'CO_TERMINUS' => 'badge-indigo',
        'CONTRACTUAL' => 'badge-purple',
        'JOB_ORDER' => 'badge-amber',
        'CONTRACT_OF_SERVICE' => 'badge-gray',
        'GIP' => 'badge-pink',
    ];
    $typeClass = $typeColors[$type->code] ?? 'badge-gray';
@endphp
<span class="badge {{ $typeClass }}">{{ $type->name }}</span>
