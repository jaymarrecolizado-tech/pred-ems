@extends('layouts.app')

@section('title', 'Attendance Checkpoints')

@section('breadcrumbs')
    @include('partials.breadcrumbs', ['crumbs' => [['Dashboard', route('dashboard')], ['Checkpoints']]])
@endsection

@section('content')
    <div class="cp-layout">
        {{-- Map + form --}}
        <div class="card cp-map-card">
            <div class="card-header">
                <h2>{{ isset($editing) ? 'Edit Checkpoint' : 'Add Checkpoint' }}</h2>
                @if (isset($editing))
                    <a href="{{ route('attendance.checkpoints') }}" class="btn btn-outline btn-sm">＋ Add New</a>
                @endif
            </div>
            <div class="card-pad">
                <div id="map" style="height:360px; border-radius:var(--radius-control); border:1px solid var(--line-strong); z-index:1"></div>
                <div class="cp-coords" style="display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin:10px 0 2px">
                    <span class="badge badge-blue" style="font-variant-numeric:tabular-nums" id="cp-coord-readout">—</span>
                    <span class="hint" style="font-size:12px">Click anywhere on the map or drag the marker — coordinates are captured automatically.</span>
                </div>
                <div class="hint" style="margin:8px 0 14px; font-size:12px">Drag the marker to plot the zone center; the circle shows the punch radius. Employees must be inside this radius to time log. Click a checkpoint in the list to zoom to it on the map.</div>

                <form method="POST" action="{{ isset($editing) ? route('attendance.checkpoints.update', $editing) : route('attendance.checkpoints.store') }}" class="form-grid">
                    @csrf
                    @if (isset($editing)) @method('PUT') @endif
                    <div class="field" style="grid-column:1 / -1">
                        <label for="name">Checkpoint Name <span class="req">*</span></label>
                        <input type="text" id="name" name="name" value="{{ old('name', isset($editing) ? $editing->name : '') }}" placeholder="e.g. DICT RO2 HQ — Tuguegarao City">
                    </div>
                    <div class="field" style="grid-column:1 / -1">
                        <label>Coordinates <span class="req">*</span> <span class="hint" style="font-weight:400">(auto-filled when you click the map)</span></label>
                        <div class="form-grid" style="grid-template-columns:1fr 1fr; gap:8px">
                            <div class="field" style="margin:0">
                                <label for="latitude" style="font-size:11.5px; color:var(--ink-400)">Latitude</label>
                                <input type="text" id="latitude" name="latitude" value="{{ old('latitude', isset($editing) ? $editing->latitude : '') }}" readonly placeholder="Click the map…" class="cp-coord-input">
                            </div>
                            <div class="field" style="margin:0">
                                <label for="longitude" style="font-size:11.5px; color:var(--ink-400)">Longitude</label>
                                <input type="text" id="longitude" name="longitude" value="{{ old('longitude', isset($editing) ? $editing->longitude : '') }}" readonly placeholder="Click the map…" class="cp-coord-input">
                            </div>
                        </div>
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
        <div class="card cp-list-card">
            <div class="card-header">
                <h2>Checkpoints <span class="hint">({{ $checkpoints->count() }})</span></h2>
                <a href="{{ route('attendance.checkpoints') }}" class="btn btn-primary btn-sm">＋ Add Checkpoint</a>
            </div>
            <div class="cp-list-scroll">
                <ul class="grow-list">
                    @forelse ($checkpoints as $checkpoint)
                        <li class="cp-row {{ isset($editing) && $editing->id === $checkpoint->id ? 'is-selected' : '' }}"
                            data-id="{{ $checkpoint->id }}"
                            data-lat="{{ $checkpoint->latitude }}"
                            data-lng="{{ $checkpoint->longitude }}"
                            role="button" tabindex="0"
                            aria-label="Show {{ $checkpoint->name }} on the map">
                            <div class="cp-row-main">
                                <div style="font-weight:600">
                                    {{ $checkpoint->name }}
                                    <span class="badge {{ $checkpoint->is_active ? 'badge-green' : 'badge-gray' }}">{{ $checkpoint->is_active ? 'Active' : 'Off' }}</span>
                                </div>
                                <div class="num" style="font-size:11.5px; color:var(--ink-400)">
                                    {{ $checkpoint->latitude }}, {{ $checkpoint->longitude }} · {{ $checkpoint->radius_meters }} m
                                    @if ($checkpoint->address) · {{ $checkpoint->address }} @endif
                                </div>
                            </div>
                            <div class="cp-row-actions">
                                <button type="button" class="btn btn-outline btn-sm cp-locate" title="Zoom to this checkpoint on the map">
                                    <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                                    </svg>
                                    Locate
                                </button>
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
                </ul>
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
        const coordReadout = document.getElementById('cp-coord-readout');

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

                // When NOT editing, settle into the full Region 2 frame now that
                // the province overlay has loaded. (When editing, the synchronous
                // homeView() below already parked us on the checkpoint.)
                if (!editingCp) {
                    map.flyToBounds(bounds, { padding: [36, 36], maxZoom: 12, duration: 0.7 });
                }
            })
            .catch(() => {
                // GeoJSON unavailable — fall back to the Region 2 viewport (or,
                // when editing, stay parked on the checkpoint from homeView()).
                if (!editingCp) {
                    map.setView(REGION2_CENTER, 8);
                }
            });

        // --- All existing checkpoints (marker + radius circle + popup) ---
        const checkpointData = @json($checkpointData);

        const activeCheckpoint = '{{ isset($editing) ? $editing->id : '' }}';

        // Resolve the checkpoint being edited up front so homeView() and the
        // geojson handler can both rely on it without re-searching.
        const editingCp = checkpointData.find((cp) => String(cp.id) === activeCheckpoint) || null;

        const checkpointMarkers = {};

        function escHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

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

            // bubblingMouseEvents:false so clicking a dot on the map locates that
            // checkpoint instead of moving the form's draggable marker.
            const dot = L.marker(pos, {
                icon: L.divIcon({
                    className: 'checkpoint-div-icon',
                    html: `<div class="cp-dot ${cp.active ? 'is-active' : 'is-off'}" title="${escHtml(cp.name)}"></div>`,
                    iconSize: [14, 14],
                    iconAnchor: [7, 7]
                }),
                bubblingMouseEvents: false
            }).addTo(map).bindTooltip(cp.name, { sticky: true });

            dot.bindPopup(
                `<strong>${escHtml(cp.name)}</strong><br>` +
                `Radius: ${cp.radius} m · ${cp.active ? 'Active' : 'Disabled'}`
            );
            dot.on('click', () => locateCheckpoint(cp.id));

            checkpointMarkers[cp.id] = dot;
        });

        // --- Click a checkpoint (row, Locate button, or map dot) →
        //     fly to + zoom + highlight + popup ---
        function locateCheckpoint(id) {
            const cp = checkpointData.find((c) => String(c.id) === String(id));
            if (!cp) return;

            map.flyTo([cp.lat, cp.lng], Math.max(map.getZoom(), 15), { duration: 0.7 });
            const marker = checkpointMarkers[cp.id];
            if (marker) marker.openPopup();

            document.querySelectorAll('.cp-row').forEach((row) => {
                row.classList.toggle('is-selected', row.dataset.id === String(id));
            });
        }

        document.querySelectorAll('.cp-row').forEach((row) => {
            const id = row.dataset.id;

            row.addEventListener('click', (e) => {
                // Ignore clicks on the action buttons / delete form.
                if (e.target.closest('a, button, form')) return;
                locateCheckpoint(id);
            });

            row.querySelector('.cp-locate')?.addEventListener('click', (e) => {
                e.preventDefault();
                locateCheckpoint(id);
            });

            row.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    locateCheckpoint(id);
                }
            });
        });

        // --- Draggable marker for the form (new / editing checkpoint) ---
        const marker = L.marker([initialLat, initialLng], { draggable: true, bubblingMouseEvents: false }).addTo(map);
        const circle = L.circle([initialLat, initialLng], { radius: initialRadius }).addTo(map);

        function syncCircle() {
            const pos = marker.getLatLng();
            circle.setLatLng(pos);
            circle.setRadius(parseInt(radiusInput.value || '200', 10));
            latInput.value = pos.lat.toFixed(7);
            lngInput.value = pos.lng.toFixed(7);
            if (coordReadout) coordReadout.textContent = pos.lat.toFixed(6) + ', ' + pos.lng.toFixed(6);
        }

        marker.on('dragend', syncCircle);

        // Radius-only updates must NOT capture coordinates — changing the radius
        // alone would otherwise fill the coordinate fields with the marker's
        // current (possibly default) position before the user has picked a spot.
        radiusInput.addEventListener('input', () => {
            circle.setRadius(parseInt(radiusInput.value || '200', 10));
        });

        map.on('click', (e) => {
            marker.setLatLng(e.latlng);
            syncCircle();
        });

        if (latInput.value) {
            // Editing: surface the checkpoint's stored coordinates immediately.
            if (coordReadout) coordReadout.textContent = initialLat.toFixed(6) + ', ' + initialLng.toFixed(6);
        } else {
            // Adding: keep the fields blank until the user clicks/drags on the
            // map — the capture is visible, not silently pre-filled.
            latInput.value = '';
            lngInput.value = '';
            if (coordReadout) coordReadout.textContent = '—';
        }

        // Park the map on the checkpoint being edited (deterministic — no
        // dependence on the async province-overlay fetch), otherwise show the
        // full Region 2 frame.
        function homeView() {
            if (editingCp) {
                map.setView([editingCp.lat, editingCp.lng], 15, { animate: false });
            } else if (bounds.length) {
                map.fitBounds(bounds, { padding: [36, 36], maxZoom: 12 });
            } else {
                map.setView(REGION2_CENTER, 8);
            }
        }

        homeView();
    });
</script>
<style>
    /* ---------- Checkpoints page layout ---------- */
    .cp-layout {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
        align-items: stretch;
        margin-bottom: 18px;
    }
    .cp-map-card { display: flex; flex-direction: column; min-width: 0; }
    .cp-map-card .card-pad { flex: 1; }
    .cp-list-card { display: flex; flex-direction: column; min-width: 0; }
    .cp-list-scroll {
        flex: 1;
        overflow-y: auto;
        padding: 2px 18px 12px;
        max-height: 640px;
    }
    .cp-list-scroll .grow-list li { border-bottom: 1px solid var(--line); }

    .cp-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 8px;
        border-radius: var(--radius-control);
        cursor: pointer;
        transition: background .12s, box-shadow .12s;
    }
    .cp-row:hover { background: var(--paper-2); }
    .cp-row:focus-visible { outline: 2px solid var(--brand-600); outline-offset: -2px; }
    .cp-row.is-selected {
        background: var(--brand-50);
        box-shadow: inset 3px 0 0 var(--brand-600);
    }
    .cp-row.is-selected:hover { background: var(--brand-50); }
    .cp-row-main { min-width: 0; }
    .cp-row-actions { display: flex; gap: 6px; flex-shrink: 0; }

    .cp-coord-input {
        font-variant-numeric: tabular-nums;
        background: var(--paper-2, #f4f6f9);
        color: var(--ink-700, #1e293b);
        font-weight: 600;
    }
    .cp-coord-input:read-only { cursor: default; }

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

    @media (max-width: 1023px) {
        .cp-layout { grid-template-columns: 1fr; }
        .cp-list-scroll { max-height: none; }
    }
</style>
