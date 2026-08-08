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
        const map = L.map('map').setView([17.6132, 121.7272], 13);
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

        let marker = L.marker([initialLat, initialLng], { draggable: true }).addTo(map);
        let circle = L.circle([initialLat, initialLng], { radius: initialRadius }).addTo(map);

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
    });
</script>
