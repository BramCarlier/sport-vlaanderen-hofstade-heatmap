<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sport Vlaanderen Hofstade - Event Heatmap</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">

    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: Arial, Helvetica, sans-serif; background: #f3f2ef; color: #111111; }
        .app { display: grid; grid-template-columns: 360px 1fr; height: 100vh; }
        .panel { background: #ffffff; border-right: 1px solid #d4d0cb; overflow-y: auto; padding: 20px; z-index: 10; }
        .eyebrow { margin: 0 0 4px; color: #c83a2e; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; font-size: 12px; }
        h1 { margin: 0; color: #1f4867; font-size: 28px; }
        h2 { margin: 0 0 14px; color: #1f4867; font-size: 18px; }
        .intro, .small-text, .selected-hint, .event-count { color: #5d5853; line-height: 1.45; }
        .card { border: 1px solid #d4d0cb; border-radius: 14px; padding: 16px; margin-top: 16px; background: #fafafa; }
        label { display: block; margin-bottom: 12px; font-weight: 700; color: #333; }
        input, textarea { width: 100%; margin-top: 6px; border: 1px solid #c9c5c0; border-radius: 10px; padding: 11px; font-size: 15px; }
        textarea { min-height: 72px; resize: vertical; }
        code { background: #eeeae5; border-radius: 6px; padding: 2px 5px; }
        .checkbox-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; cursor: pointer; }
        .checkbox-row input { width: auto; margin: 0; transform: scale(1.15); }
        button { width: 100%; border: 0; border-radius: 10px; padding: 12px; background: #c83a2e; color: white; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 4px; }
        button:hover { background: #9f0000; }
        .secondary-button { background: #1f4867; }
        .secondary-button:hover { background: #16364f; }
        .danger-button { background: #8d847d; }
        .danger-button:hover { background: #6f6761; }
        .map-wrap { position: relative; min-height: 0; }
        #map { height: 100vh; width: 100%; }
        .map-tip { position: absolute; left: 50%; top: 14px; transform: translateX(-50%); z-index: 500; background: rgba(255,255,255,.94); border: 1px solid #d4d0cb; border-radius: 999px; padding: 9px 14px; font-weight: 700; color: #1f4867; box-shadow: 0 4px 16px rgba(0,0,0,.12); pointer-events: none; }
        .print-title { display: none; }
        .selected-coordinate-icon { width: 22px; height: 22px; margin-left: -11px; margin-top: -11px; border-radius: 50%; background: #c83a2e; border: 3px solid white; box-shadow: 0 0 0 3px rgba(200,58,46,.35); }
        .legend-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .legend-color { width: 34px; height: 14px; border-radius: 999px; display: inline-block; }
        .legend-color.low { background: #37ff00; }
        .legend-color.medium { background: #ffff00; }
        .legend-color.high { background: #ff0000; }
        .event-list { list-style: none; padding: 0; margin: 0; }
        .event-list li { border-top: 1px solid #e1ded9; padding: 10px 0; font-size: 14px; }
        .event-list strong { display: block; color: #111111; }
        .event-list span { color: #666; font-size: 12px; }
        .event-actions { display: flex; gap: 8px; margin-top: 8px; }
        .event-actions button { width: auto; padding: 7px 10px; font-size: 12px; }
        .error { display: none; margin-top: 10px; color: #9f0000; font-weight: 700; }
        .leaflet-popup-content strong { color: #1f4867; }
        @media (max-width: 800px) { .app { grid-template-columns: 1fr; grid-template-rows: auto 1fr; } .panel { max-height: 44vh; border-right: 0; border-bottom: 1px solid #d4d0cb; } #map { height: 56vh; } .map-tip { top: 10px; font-size: 12px; max-width: calc(100vw - 28px); text-align: center; } }
        @media print { @page { size: landscape; margin: 10mm; } html, body { height: auto; background: white; } .panel, .map-tip, .leaflet-control-zoom, .leaflet-control-attribution { display: none !important; } .app, .map-wrap { display: block; height: auto; } .print-title { display: block; position: fixed; left: 16px; top: 16px; z-index: 9999; background: rgba(255,255,255,.94); border: 1px solid #d4d0cb; border-radius: 8px; padding: 10px 12px; color: #1f4867; font-size: 14px; } #map { height: 180mm; width: 100%; page-break-inside: avoid; } }
    </style>
</head>
<body>
<div class="app">
    <aside class="panel">
        <header>
            <p class="eyebrow">Sport Vlaanderen Hofstade</p>
            <h1>Event Heatmap</h1>
            <p class="intro">Tap or click the map to select coordinates, then save an event to the Laravel backend.</p>
        </header>

        <section class="card">
            <h2>Map options</h2>
            <label class="checkbox-row"><input id="toggleHeatmap" type="checkbox" checked> Show heatmap</label>
            <label class="checkbox-row"><input id="toggleDots" type="checkbox" checked> Show event dots</label>
            <button id="printMapButton" type="button" class="secondary-button">Print map</button>
        </section>

        <form id="eventForm" class="card">
            <h2>Add event</h2>
            <label>Event name <input id="eventName" type="text" placeholder="e.g. verdrinking" required></label>
            <label>Latitude <input id="eventLat" type="number" step="0.000001" value="50.986290" required></label>
            <label>Longitude <input id="eventLng" type="number" step="0.000001" value="4.514818" required></label>
            <label>Weight <input id="eventWeight" type="number" min="1" max="10" value="1"></label>
            <label>Notes <textarea id="eventNotes" placeholder="Optional notes"></textarea></label>
            <p id="selectedHint" class="selected-hint">Tap the map to select a precise location.</p>
            <button type="submit">Add to heatmap</button>
            <p id="errorMessage" class="error"></p>
        </form>

        <section class="card">
            <h2>Legend</h2>
            <div class="legend-row"><span class="legend-color low"></span><span>Low density</span></div>
            <div class="legend-row"><span class="legend-color medium"></span><span>Medium density</span></div>
            <div class="legend-row"><span class="legend-color high"></span><span>High density</span></div>
        </section>

        <section class="card">
            <h2>Events</h2>
            <p id="eventCount" class="event-count"></p>
            <ul id="eventList" class="event-list"></ul>
        </section>
    </aside>

    <main class="map-wrap">
        <div id="map"></div>
        <div class="map-tip">Tap/click the map to select coordinates</div>
        <div class="print-title"><strong>Sport Vlaanderen Hofstade</strong><br>Event Heatmap</div>
    </main>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat/dist/leaflet-heat.js"></script>
<script>
const hofstadeCenter = [50.986290, 4.514818];
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
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

const selectedIcon = L.divIcon({ className: 'selected-coordinate-icon', iconSize: [22, 22], iconAnchor: [11, 11] });

function escapeHtml(value) {
    return String(value ?? '').replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;');
}

function eventToHeatPoint(event) {
    return [Number(event.latitude), Number(event.longitude), Math.max(0.2, Number(event.weight || 1) / 10)];
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
    if (!showHeatmap) return;
    heatLayer = L.heatLayer(events.map(eventToHeatPoint), {
        radius: 34,
        blur: 24,
        maxZoom: 19,
        gradient: { 0.0: 'white', 0.25: 'lime', 0.55: 'yellow', 0.8: 'orange', 1.0: 'red' },
    }).addTo(map);
}

function renderMarkers() {
    markersLayer.clearLayers();
    if (!showDots) return;
    events.forEach((event) => {
        const marker = L.circleMarker([Number(event.latitude), Number(event.longitude)], {
            radius: 7,
            weight: 2,
            color: '#1f4867',
            fillColor: '#c83a2e',
            fillOpacity: 0.9,
        });
        marker.bindPopup(`<strong>${escapeHtml(event.name)}</strong><br>Latitude: ${Number(event.latitude).toFixed(6)}<br>Longitude: ${Number(event.longitude).toFixed(6)}<br>Weight: ${Number(event.weight || 1)}${event.notes ? `<br>${escapeHtml(event.notes)}` : ''}`);
        marker.addTo(markersLayer);
    });
}

function renderEventList() {
    eventCount.textContent = `${events.length} event${events.length === 1 ? '' : 's'} saved`;
    eventList.innerHTML = events.map((event) => `
        <li>
            <strong>${escapeHtml(event.name)}</strong>
            <span>${Number(event.latitude).toFixed(6)}, ${Number(event.longitude).toFixed(6)} · weight ${Number(event.weight || 1)}</span>
            <div class="event-actions">
                <button type="button" class="secondary-button" data-focus="${event.id}">Focus</button>
                <button type="button" class="danger-button" data-delete="${event.id}">Delete</button>
            </div>
        </li>
    `).join('');
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
    selectedMarker.bindPopup(`<strong>Selected location</strong><br>Latitude: ${latitude.toFixed(6)}<br>Longitude: ${longitude.toFixed(6)}<br>Fill in a name and press “Add to heatmap”.`).openPopup();
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
