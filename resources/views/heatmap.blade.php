<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sport Vlaanderen Hofstade - Event Heatmap</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

    <style>
        :root {
            --accent: #c83a2e;
            --accent-dark: #9f0000;
            --midnight: #1f4867;
            --midnight-dark: #15344d;
            --mist: #f3f2ef;
            --steel: #d4d0cb;
            --ash: #8d847d;
            --void: #111111;
            --white: #ffffff;
            --success: #00a651;
            --warning: #f7c600;
            --orange: #f28c28;
            --danger: #e11d1d;
            --shadow: 0 18px 50px rgba(17, 17, 17, 0.14);
            --radius: 22px;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--mist); color: var(--void); }
        body { overflow: hidden; }
        button, input, textarea { font: inherit; }

        .app { min-height: 100dvh; display: grid; grid-template-rows: auto 1fr; }
        .topbar { position: sticky; top: 0; z-index: 800; display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: calc(12px + env(safe-area-inset-top)) 14px 12px; background: rgba(255,255,255,0.92); border-bottom: 1px solid rgba(212,208,203,0.9); backdrop-filter: blur(14px); }
        .brand { min-width: 0; }
        .eyebrow { margin: 0 0 3px; color: var(--accent); font-weight: 800; text-transform: uppercase; letter-spacing: 0.1em; font-size: 11px; }
        h1 { margin: 0; color: var(--midnight); font-size: clamp(22px, 6vw, 38px); line-height: 1.02; letter-spacing: -0.04em; }
        h2 { margin: 0 0 14px; color: var(--midnight); font-size: 18px; letter-spacing: -0.02em; }
        h3 { margin: 0; color: var(--void); font-size: 15px; }
        p { line-height: 1.45; }
        .intro { margin: 10px 0 0; color: #5d5853; font-size: 14px; }

        .actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }
        .button, button { min-height: 44px; border: 0; border-radius: 999px; padding: 0 16px; background: var(--accent); color: var(--white); font-weight: 800; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer; box-shadow: 0 10px 20px rgba(200, 58, 46, 0.18); transition: transform .16s ease, box-shadow .16s ease, background .16s ease; }
        .button:hover, button:hover { transform: translateY(-1px); background: var(--accent-dark); box-shadow: 0 14px 26px rgba(200, 58, 46, 0.26); }
        .button.secondary, button.secondary { background: var(--midnight); box-shadow: 0 10px 20px rgba(31,72,103,0.16); }
        .button.secondary:hover, button.secondary:hover { background: var(--midnight-dark); }
        .button.ghost, button.ghost { background: rgba(31,72,103,0.08); color: var(--midnight); box-shadow: none; }
        .button.danger, button.danger { background: var(--ash); box-shadow: none; }
        .button.full, button.full { width: 100%; }

        .layout { min-height: 0; display: grid; grid-template-rows: minmax(42dvh, 1fr) auto; }
        .map-panel { position: relative; min-height: 42dvh; background: #dfe8e2; }
        #map { position: absolute; inset: 0; width: 100%; height: 100%; z-index: 1; }
        .map-overlay { position: absolute; left: 12px; right: 12px; bottom: 12px; z-index: 500; display: grid; gap: 10px; pointer-events: none; }
        .map-tip, .stats-strip { pointer-events: auto; border: 1px solid rgba(212,208,203,0.9); background: rgba(255,255,255,0.94); box-shadow: 0 12px 32px rgba(17,17,17,0.12); backdrop-filter: blur(12px); border-radius: 18px; }
        .map-tip { padding: 10px 12px; color: var(--midnight); font-weight: 800; font-size: 13px; }
        .stats-strip { display: grid; grid-template-columns: repeat(3, 1fr); overflow: hidden; }
        .stat { padding: 10px 12px; border-right: 1px solid rgba(212,208,203,0.8); }
        .stat:last-child { border-right: 0; }
        .stat span { display: block; color: #6f6761; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .07em; }
        .stat strong { display: block; margin-top: 2px; color: var(--midnight); font-size: 18px; }

        .sheet { position: relative; z-index: 20; max-height: 58dvh; overflow: auto; padding: 14px; background: linear-gradient(180deg, rgba(243,242,239,0.98), var(--mist)); border-top: 1px solid var(--steel); box-shadow: 0 -18px 50px rgba(17,17,17,0.12); }
        .sheet-grid { display: grid; gap: 14px; }
        .card { border: 1px solid rgba(212,208,203,0.95); border-radius: var(--radius); padding: 16px; background: rgba(255,255,255,0.92); box-shadow: 0 10px 28px rgba(17,17,17,0.05); }
        .card.compact { padding: 14px; }
        .card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; margin-bottom: 12px; }
        .small-text, .selected-hint, .event-count { color: #5d5853; font-size: 13px; margin: 4px 0 0; }

        .control-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .checkbox-card { min-height: 48px; display: flex; align-items: center; gap: 10px; border: 1px solid var(--steel); border-radius: 16px; padding: 10px 12px; font-weight: 800; color: var(--midnight); background: #fff; cursor: pointer; }
        .checkbox-card input { width: 18px; height: 18px; accent-color: var(--accent); }

        label { display: block; margin-bottom: 12px; font-weight: 800; color: #2c2a28; font-size: 13px; }
        input, textarea { width: 100%; margin-top: 7px; border: 1px solid #c9c5c0; border-radius: 14px; padding: 12px 13px; font-size: 16px; background: #fff; color: var(--void); outline: none; }
        input:focus, textarea:focus { border-color: var(--midnight); box-shadow: 0 0 0 4px rgba(31,72,103,0.12); }
        textarea { min-height: 76px; resize: vertical; }
        .two-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }

        .legend { display: grid; gap: 10px; }
        .legend-scale { height: 14px; border-radius: 999px; background: linear-gradient(90deg, var(--success), var(--warning), var(--orange), var(--danger)); box-shadow: inset 0 0 0 1px rgba(0,0,0,.08); }
        .legend-labels { display: flex; justify-content: space-between; gap: 10px; color: #5d5853; font-size: 12px; font-weight: 800; }
        .density-note { margin: 10px 0 0; color: #6f6761; font-size: 13px; }

        .event-list { list-style: none; padding: 0; margin: 0; display: grid; gap: 10px; }
        .event-list li { border: 1px solid rgba(212,208,203,0.9); border-radius: 18px; padding: 12px; background: #fff; }
        .event-row { display: flex; justify-content: space-between; gap: 12px; align-items: flex-start; }
        .event-list strong { display: block; color: var(--void); font-size: 15px; }
        .event-list span { display: block; color: #666; font-size: 12px; margin-top: 3px; }
        .event-actions { display: flex; gap: 8px; margin-top: 10px; }
        .event-actions button { min-height: 34px; padding: 0 11px; font-size: 12px; box-shadow: none; }
        .density-pill { white-space: nowrap; display: inline-flex; align-items: center; justify-content: center; min-height: 28px; border-radius: 999px; padding: 0 10px; color: #fff; font-size: 12px; font-weight: 900; }
        .density-low { background: var(--success); }
        .density-medium { background: var(--warning); color: var(--void); }
        .density-high { background: var(--orange); }
        .density-critical { background: var(--danger); }

        .error { display: none; margin: 10px 0 0; color: var(--accent-dark); font-weight: 800; }
        .selected-coordinate-icon { width: 22px; height: 22px; margin-left: -11px; margin-top: -11px; border-radius: 50%; background: var(--accent); border: 3px solid white; box-shadow: 0 0 0 3px rgba(200,58,46,.35); }
        .leaflet-popup-content strong { color: var(--midnight); }
        .print-title { display: none; }

        @media (min-width: 900px) {
            body { overflow: hidden; }
            .app { grid-template-rows: auto 1fr; }
            .topbar { padding: 18px 24px; }
            .layout { grid-template-columns: minmax(390px, 440px) 1fr; grid-template-rows: 1fr; min-height: 0; }
            .sheet { order: 0; max-height: none; height: calc(100dvh - 86px); border-top: 0; border-right: 1px solid var(--steel); padding: 18px; box-shadow: none; }
            .map-panel { order: 1; min-height: 0; height: calc(100dvh - 86px); }
            .sheet-grid { gap: 16px; }
            .map-overlay { left: 20px; right: auto; bottom: 20px; width: min(460px, calc(100% - 40px)); }
        }

        @media (min-width: 1280px) {
            .layout { grid-template-columns: minmax(420px, 480px) 1fr; }
            .sheet { padding: 22px; }
        }

        @media print {
            @page { size: landscape; margin: 10mm; }
            html, body { height: auto; background: white; overflow: visible; }
            .topbar, .sheet, .map-tip, .leaflet-control-zoom, .leaflet-control-attribution { display: none !important; }
            .app, .layout, .map-panel { display: block; height: auto; min-height: auto; }
            #map { position: relative; height: 180mm; width: 100%; page-break-inside: avoid; }
            .print-title { display: block; position: fixed; left: 16px; top: 16px; z-index: 9999; background: rgba(255,255,255,.94); border: 1px solid var(--steel); border-radius: 10px; padding: 10px 12px; color: var(--midnight); font-size: 14px; }
        }
    </style>
</head>
<body>
<div class="app">
    <header class="topbar">
        <div class="brand">
            <p class="eyebrow">Sport Vlaanderen Hofstade</p>
            <h1>Event Heatmap</h1>
        </div>
        <nav class="actions" aria-label="Main actions">
            <a class="button ghost" href="/nova">Nova</a>
            <button id="printMapButton" type="button" class="secondary">Print</button>
        </nav>
    </header>

    <div class="layout">
        <aside class="sheet">
            <div class="sheet-grid">
                <section class="card">
                    <h2>Incident density map</h2>
                    <p class="intro">Tap or click the map to select a location. The heatmap color is based on nearby event density, so green means low density and red means high density.</p>
                    <div class="legend" aria-label="Heatmap legend">
                        <div class="legend-scale"></div>
                        <div class="legend-labels"><span>Low</span><span>Medium</span><span>High</span><span>Critical</span></div>
                    </div>
                    <p class="density-note">Density uses a 50 meter radius. Four or more nearby events are treated as high density.</p>
                </section>

                <section class="card compact">
                    <div class="card-header">
                        <div>
                            <h2>Map layers</h2>
                            <p class="small-text">Control what is visible on the map.</p>
                        </div>
                    </div>
                    <div class="control-grid">
                        <label class="checkbox-card"><input id="toggleHeatmap" type="checkbox" checked> Heatmap</label>
                        <label class="checkbox-card"><input id="toggleDots" type="checkbox" checked> Event dots</label>
                    </div>
                </section>

                <form id="eventForm" class="card">
                    <div class="card-header">
                        <div>
                            <h2>Add event</h2>
                            <p id="selectedHint" class="selected-hint">Tap the map to select a precise location.</p>
                        </div>
                    </div>
                    <label>Event name <input id="eventName" type="text" placeholder="e.g. verdrinking" required></label>
                    <div class="two-cols">
                        <label>Latitude <input id="eventLat" type="number" step="0.000001" value="50.986290" required></label>
                        <label>Longitude <input id="eventLng" type="number" step="0.000001" value="4.514818" required></label>
                    </div>
                    <label>Weight <input id="eventWeight" type="number" min="1" max="10" value="1"></label>
                    <label>Notes <textarea id="eventNotes" placeholder="Optional notes"></textarea></label>
                    <button type="submit" class="full">Add to heatmap</button>
                    <p id="errorMessage" class="error"></p>
                </form>

                <section class="card">
                    <div class="card-header">
                        <div>
                            <h2>Events</h2>
                            <p id="eventCount" class="event-count"></p>
                        </div>
                    </div>
                    <ul id="eventList" class="event-list"></ul>
                </section>
            </div>
        </aside>

        <main class="map-panel">
            <div id="map"></div>
            <div class="map-overlay">
                <div class="stats-strip" aria-label="Heatmap stats">
                    <div class="stat"><span>Events</span><strong id="statEvents">0</strong></div>
                    <div class="stat"><span>Radius</span><strong>50m</strong></div>
                    <div class="stat"><span>High</span><strong>4+</strong></div>
                </div>
                <div class="map-tip">Tap/click the map to select coordinates</div>
            </div>
            <div class="print-title"><strong>Sport Vlaanderen Hofstade</strong><br>Event Heatmap</div>
        </main>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<script>
const hofstadeCenter = [50.986290, 4.514818];
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
const densityRadiusMeters = 50;
const highDensityCount = 4;
const map = L.map('map', { zoomControl: true }).setView(hofstadeCenter, 17);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 20,
    attribution: '&copy; OpenStreetMap contributors',
}).addTo(map);

let events = [];
let markersLayer = L.layerGroup().addTo(map);
let heatLayer = null;
let selectedMarker = null;
let showHeatmap = true;
let showDots = true;

const eventForm = document.getElementById('eventForm');
const eventName = document.getElementById('eventName');
const eventLat = document.getElementById('eventLat');
const eventLng = document.getElementById('eventLng');
const eventWeight = document.getElementById('eventWeight');
const eventNotes = document.getElementById('eventNotes');
const eventList = document.getElementById('eventList');
const eventCount = document.getElementById('eventCount');
const selectedHint = document.getElementById('selectedHint');
const errorMessage = document.getElementById('errorMessage');
const toggleHeatmap = document.getElementById('toggleHeatmap');
const toggleDots = document.getElementById('toggleDots');
const printMapButton = document.getElementById('printMapButton');
const statEvents = document.getElementById('statEvents');
const selectedIcon = L.divIcon({ className: 'selected-coordinate-icon', iconSize: [22, 22], iconAnchor: [11, 11] });

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function distanceMeters(a, b) {
    const earthRadius = 6371000;
    const dLat = (Number(b.latitude) - Number(a.latitude)) * Math.PI / 180;
    const dLng = (Number(b.longitude) - Number(a.longitude)) * Math.PI / 180;
    const lat1 = Number(a.latitude) * Math.PI / 180;
    const lat2 = Number(b.latitude) * Math.PI / 180;
    const haversine = Math.sin(dLat / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin(dLng / 2) ** 2;
    return 2 * earthRadius * Math.atan2(Math.sqrt(haversine), Math.sqrt(1 - haversine));
}

function densityCount(event) {
    return events.filter((candidate) => distanceMeters(event, candidate) <= densityRadiusMeters).length;
}

function densityClass(count) {
    if (count >= highDensityCount) return 'density-critical';
    if (count >= 3) return 'density-high';
    if (count >= 2) return 'density-medium';
    return 'density-low';
}

function densityLabel(count) {
    if (count >= highDensityCount) return 'Critical';
    if (count >= 3) return 'High';
    if (count >= 2) return 'Medium';
    return 'Low';
}

function eventToHeatPoint(event) {
    const density = densityCount(event);
    const intensity = Math.max(0.25, Math.min(1, density / highDensityCount));
    return [Number(event.latitude), Number(event.longitude), intensity];
}

function showError(message) {
    errorMessage.textContent = message;
    errorMessage.style.display = message ? 'block' : 'none';
}

function renderHeatmap() {
    if (heatLayer) {
        map.removeLayer(heatLayer);
        heatLayer = null;
    }

    if (!showHeatmap || events.length === 0) return;

    heatLayer = L.heatLayer(events.map(eventToHeatPoint), {
        radius: 44,
        blur: 22,
        maxZoom: 20,
        minOpacity: 0.35,
        gradient: { 0.25: '#00a651', 0.50: '#f7c600', 0.75: '#f28c28', 1.0: '#e11d1d' },
    }).addTo(map);
}

function renderMarkers() {
    markersLayer.clearLayers();
    if (!showDots) return;

    events.forEach((event) => {
        const density = densityCount(event);
        const marker = L.circleMarker([Number(event.latitude), Number(event.longitude)], {
            radius: 8,
            weight: 2,
            color: '#1f4867',
            fillColor: '#c83a2e',
            fillOpacity: 0.92,
        });

        marker.bindPopup(`<strong>${escapeHtml(event.name)}</strong><br>Latitude: ${Number(event.latitude).toFixed(6)}<br>Longitude: ${Number(event.longitude).toFixed(6)}<br>Density: ${density} event${density === 1 ? '' : 's'} within ${densityRadiusMeters}m${event.notes ? `<br>${escapeHtml(event.notes)}` : ''}`);
        marker.addTo(markersLayer);
    });
}

function renderEventList() {
    eventCount.textContent = `${events.length} event${events.length === 1 ? '' : 's'} saved`;
    statEvents.textContent = events.length;

    eventList.innerHTML = events.map((event) => {
        const density = densityCount(event);
        return `
            <li>
                <div class="event-row">
                    <div>
                        <strong>${escapeHtml(event.name)}</strong>
                        <span>${Number(event.latitude).toFixed(6)}, ${Number(event.longitude).toFixed(6)}</span>
                        <span>${density} event${density === 1 ? '' : 's'} within ${densityRadiusMeters}m</span>
                    </div>
                    <div class="density-pill ${densityClass(density)}">${densityLabel(density)}</div>
                </div>
                <div class="event-actions">
                    <button type="button" class="secondary" data-focus="${event.id}">Focus</button>
                    <button type="button" class="danger" data-delete="${event.id}">Delete</button>
                </div>
            </li>
        `;
    }).join('');
}

function renderAll() {
    renderHeatmap();
    renderMarkers();
    renderEventList();
}

async function loadEvents() {
    const response = await fetch('/api/events', { headers: { Accept: 'application/json' }, cache: 'no-store' });
    events = await response.json();
    renderAll();

    if (events.length > 0) {
        const bounds = L.latLngBounds(events.map((event) => [Number(event.latitude), Number(event.longitude)]));
        map.fitBounds(bounds.pad(0.25), { maxZoom: 18 });
    }
}

function selectCoordinates(latlng) {
    const latitude = Number(latlng.lat.toFixed(6));
    const longitude = Number(latlng.lng.toFixed(6));
    eventLat.value = latitude;
    eventLng.value = longitude;
    selectedHint.textContent = `Selected: ${latitude}, ${longitude}`;

    if (selectedMarker) {
        selectedMarker.setLatLng([latitude, longitude]);
    } else {
        selectedMarker = L.marker([latitude, longitude], { icon: selectedIcon, draggable: true, zIndexOffset: 1000 }).addTo(map);
        selectedMarker.on('dragend', () => selectCoordinates(selectedMarker.getLatLng()));
    }

    selectedMarker.bindPopup(`<strong>Selected location</strong><br>Latitude: ${latitude.toFixed(6)}<br>Longitude: ${longitude.toFixed(6)}<br>Fill in a name and press Add to heatmap.`).openPopup();
}

function clearSelectedMarker() {
    if (selectedMarker) {
        map.removeLayer(selectedMarker);
        selectedMarker = null;
    }

    selectedHint.textContent = 'Tap the map to select a precise location.';
}

map.on('click', (event) => selectCoordinates(event.latlng));
toggleHeatmap.addEventListener('change', () => { showHeatmap = toggleHeatmap.checked; renderHeatmap(); });
toggleDots.addEventListener('change', () => { showDots = toggleDots.checked; renderMarkers(); });
printMapButton.addEventListener('click', () => { map.invalidateSize(); setTimeout(() => window.print(), 150); });
window.addEventListener('resize', () => setTimeout(() => map.invalidateSize(), 150));
window.addEventListener('beforeprint', () => map.invalidateSize());
window.addEventListener('afterprint', () => setTimeout(() => map.invalidateSize(), 150));

eventForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    showError('');

    const payload = {
        name: eventName.value.trim(),
        latitude: Number(eventLat.value),
        longitude: Number(eventLng.value),
        weight: Number(eventWeight.value || 1),
        notes: eventNotes.value.trim() || null,
    };

    const response = await fetch('/api/events', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        showError('Could not save event. Check the name and coordinates.');
        return;
    }

    const saved = await response.json();
    events.unshift(saved);
    renderAll();
    map.flyTo([payload.latitude, payload.longitude], Math.max(map.getZoom(), 18), { duration: 0.7 });
    eventName.value = '';
    eventNotes.value = '';
    eventWeight.value = 1;
    clearSelectedMarker();
});

eventList.addEventListener('click', async (event) => {
    const focusId = event.target.dataset.focus;
    const deleteId = event.target.dataset.delete;

    if (focusId) {
        const selected = events.find((item) => String(item.id) === String(focusId));
        if (selected) map.flyTo([Number(selected.latitude), Number(selected.longitude)], 18, { duration: 0.7 });
    }

    if (deleteId && confirm('Delete this event?')) {
        const response = await fetch(`/api/events/${deleteId}`, {
            method: 'DELETE',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
        });

        if (response.ok) {
            events = events.filter((item) => String(item.id) !== String(deleteId));
            renderAll();
        }
    }
});

loadEvents();
</script>
</body>
</html>
