{{-- Breadcrumb trail. Pass $crumbs as [[label, url], ...]; the last item is the current page (url null/omitted renders plain). --}}
@if (! empty($crumbs) && count($crumbs) > 0)
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <ol>
            @foreach ($crumbs as $index => $crumb)
                @php
                    $label = is_array($crumb) ? $crumb[0] : $crumb;
                    $url = is_array($crumb) ? ($crumb[1] ?? null) : null;
                    $isLast = $index === count($crumbs) - 1;
                @endphp
                <li>
                    @if ($url && ! $isLast)
                        <a href="{{ $url }}">{{ $label }}</a>
                    @elseif ($isLast)
                        <span aria-current="page">{{ $label }}</span>
                    @else
                        <span>{{ $label }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
