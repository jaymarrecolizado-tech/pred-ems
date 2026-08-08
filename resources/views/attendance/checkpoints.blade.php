@extends('layouts.app')

@section('title', 'Attendance Checkpoints')

@section('content')
    <div class="info-grid" style="grid-template-columns:1fr 1fr; align-items:start; margin-bottom:18px">
        {{-- Map + form --}}
        <div class="card">
            <div class="card-header">
                <h2>{{ isset($editing) ? 'Edit Checkpoint' : 'Add Checkpoint' }}</h2>
            </div>
            <div class="card-pad">
                <div id="map" style="height:320px; border-radius:var(--radius-control); border:1px solid var(--line-strong); z-index:1"></div>
                <div class="hint" style="margin:8px 0 14px; font-size:12px">Drag the marker to plot the zone center; the circle shows the punch radius. Employees must be inside this radius to time log.</div>

                <form method="POST" action="{{ isset($editing) ? route('attendance.checkpoints.update', $editing) : route('attendance.checkpoints.store') }}" class="form-grid">
                    @csrf
                    @if (isset($editing)) @method('PUT') @endif
                    <input type="hidden" id="latitude" name="latitude" value="{{ old('latitude', isset($editing) ? $editing->latitude : '') }}">
                    <input type="hidden" id="longitude" name="longitude" value="{{ old('longitude', isset($editing) ? $editing->longitude : '') }}">
                    <div class="field" style="grid-column:1 / -1">
                        <label for="name">Checkpoint Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', isset($editing) ? $editing->name : '') }}" placeholder="e.g. DICT RO2 HQ — Tuguegarao City">
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address', isset($editing) ? $editing->address : '') }}">
                    </div>
                    <div class="field">
                        <label for="radius_meters">Radius (meters)</label>
                        <input type="number" id="radius_meters" name="radius_meters" min="10" max="5000" value="{{ old('radius_meters', isset($editing) ? $editing->radius_meters : $defaultRadius) }}">
                    </div>
                    <div class="field">
                        <label for="is_active">Active</label>
                        <select id="is_active" name="is_active">
                            <option value="1" @selected(!isset($editing) || $editing->is_active)>Active</option>
                            <option value="0" @selected(isset($editing) && !$editing->is_active)>Disabled</option>
                        </select>
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="2">{{ old('notes', isset($editing) ? $editing->notes : '') }}</textarea>
                    </div>
                    <div style="grid-column:1 / -1" class="form-actions">
                        <button type="submit" class="btn btn-primary">{{ isset($editing) ? 'Save Changes' : 'Save Checkpoint' }}</button>
                        @if (isset($editing))
                            <a href="{{ route('attendance.checkpoints') }}" class="btn btn-outline">Cancel</a>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        {{-- List --}}
        <div class="card">
            <div class="card-header">
                <h2>Checkpoints <span class="hint">({{ $checkpoints->count() }})</span></h2>
            </div>
            <div class="grow-list">
                @forelse ($checkpoints as $checkpoint)
                    <li>
                        <div>
                            <div style="font-weight:600">
                                {{ $checkpoint->name }}
                                <span class="badge {{ $checkpoint->is_active ? 'badge-green' : 'badge-gray' }}">{{ $checkpoint->is_active ? 'Active' : 'Off' }}</span>
                            </div>
                            <div class="num" style="font-size:11.5px; color:var(--ink-400)">
                                {{ $checkpoint->latitude }}, {{ $checkpoint->longitude }} · {{ $checkpoint->radius_meters }} m
                                @if ($checkpoint->address) · {{ $checkpoint->address }} @endif
                            </div>
                        </div>
                        <div style="display:flex; gap:6px">
                            <a href="{{ route('attendance.checkpoints.edit', $checkpoint) }}" class="btn btn-outline btn-sm">Edit</a>
                            <form method="POST" action="{{ route('attendance.checkpoints.destroy', $checkpoint) }}" class="inline"
                                  onsubmit="return confirm('Delete this checkpoint?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="background:#dc2626; color:#fff">Delete</button>
                            </form>
                        </div>
                    </li>
                @empty
                    <li class="text-muted">No checkpoints yet. Plot the first zone on the map.</li>
                @endforelse
            </div>
        </div>
    </div>
@endsection

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
    window.addEventListener('DOMContentLoaded', () => {
        @php
            $checkpointData = $checkpoints->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => $c->name,
                'lat' => (float) $c->latitude,
                'lng' => (float) $c->longitude,
                'radius' => (int) $c->radius_meters,
                'active' => (bool) $c->is_active,
            ])->values();
        @endphp
        // Province boundaries of Region 2 (Cagayan Valley) — served locally.
        const REGION2_GEOJSON = '{{ asset('geojson/region2_provinces.geojson') }}';
        const REGION2_CENTER = [17.25, 121.6];  // roughly the region centroid

        const map = L.map('map');
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const radiusInput = document.getElementById('radius_meters');

        const initialLat = latInput.value ? parseFloat(latInput.value) : 17.6132;
        const initialLng = lngInput.value ? parseFloat(lngInput.value) : 121.7272;
        const initialRadius = parseInt(radiusInput.value || '200', 10);

        const bounds = [];

        // --- Province boundary overlay (Region 2) ---
        fetch(REGION2_GEOJSON)
            .then((r) => r.json())
            .then((geo) => {
                const provStyle = {
                    color: '#4f46e5',
                    weight: 1.4,
                    fillColor: '#c7d2fe',
                    fillOpacity: 0.14
                };
                const provLayer = L.geoJSON(geo, {
                    style: provStyle,
                    onEachFeature: (feature, layer) => {
                        const name = feature.properties && feature.properties.name;
                        if (name) {
                            layer.bindTooltip(name, { sticky: true, direction: 'top' });
                        }
                        layer.on('mouseover', () => layer.setStyle({ fillOpacity: 0.28, weight: 2 }));
                        layer.on('mouseout', () => layer.setStyle(provStyle));
                    }
                }).addTo(map);

                if (provLayer.getBounds().isValid()) {
                    const b = provLayer.getBounds();
                    bounds.push(b.getSouthWest(), b.getNorthEast());
                }
                // Smoothly settle from the checkpoint-only view into the full
                // Region 2 frame once the province overlay has loaded.
                const editingCp = checkpointData.find((cp) => String(cp.id) === activeCheckpoint);
                if (editingCp) {
                    map.setView([editingCp.lat, editingCp.lng], 14, { animate: true });
                } else {
                    map.flyToBounds(bounds, { padding: [36, 36], maxZoom: 12, duration: 0.7 });
                }
            })
            .catch(() => {
                // GeoJSON unavailable — fall back to a Region 2 viewport.
                map.setView(REGION2_CENTER, 8);
            });

        // --- All existing checkpoints (marker + radius circle) ---
        const checkpointData = @json($checkpointData);

        const activeCheckpoint = '{{ isset($editing) ? $editing->id : '' }}';

        checkpointData.forEach((cp) => {
            const pos = [cp.lat, cp.lng];
            bounds.push(L.latLng(cp.lat, cp.lng));

            L.circle(pos, {
                radius: cp.radius,
                color: cp.active ? '#059669' : '#9ca3af',
                weight: 1,
                fillColor: cp.active ? '#10b981' : '#d1d5db',
                fillOpacity: 0.12
            }).addTo(map);

            L.marker(pos, {
                icon: L.divIcon({
                    className: 'checkpoint-div-icon',
                    html: `<div class="cp-dot ${cp.active ? 'is-active' : 'is-off'}" title="${cp.name}"></div>`,
                    iconSize: [14, 14],
                    iconAnchor: [7, 7]
                })
            }).addTo(map).bindTooltip(cp.name, { sticky: true });

        });

        // --- Draggable marker for the form (new / editing checkpoint) ---
        const marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
        const circle = L.circle([initialLat, initialLng], { radius: initialRadius }).addTo(map);

        function syncCircle() {
            const pos = marker.getLatLng();
            circle.setLatLng(pos);
            circle.setRadius(parseInt(radiusInput.value || '200', 10));
            latInput.value = pos.lat.toFixed(7);
            lngInput.value = pos.lng.toFixed(7);
        }

        marker.on('dragend', syncCircle);
        radiusInput.addEventListener('input', syncCircle);

        map.on('click', (e) => {
            marker.setLatLng(e.latlng);
            syncCircle();
        });

        if (!latInput.value) {
            latInput.value = initialLat.toFixed(7);
            lngInput.value = initialLng.toFixed(7);
        }

        function fitMap() {
            if (bounds.length) {
                map.fitBounds(bounds, { padding: [36, 36], maxZoom: 12 });
            } else {
                map.setView(REGION2_CENTER, 8);
            }
        }

        fitMap();
    });
</script>
<style>
    .checkpoint-div-icon { background: transparent; border: none; }
    .cp-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px rgba(15, 23, 42, .35);
    }
    .cp-dot.is-active { background: #059669; }
    .cp-dot.is-off { background: #9ca3af; }
</style>
