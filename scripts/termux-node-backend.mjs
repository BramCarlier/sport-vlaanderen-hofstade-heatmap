import { createServer } from 'node:http';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const appDir = resolve(fileURLToPath(new URL('..', import.meta.url)), '..');
const dataFile = join(appDir, 'storage', 'app', 'termux-events.json');
const host = process.env.HOST || '127.0.0.1';
const port = Number(process.env.PORT || 8000);

const defaultEvents = [
    {
        id: 1,
        name: 'verdrinking',
        latitude: 50.98629,
        longitude: 4.514818,
        created_at: new Date().toISOString(),
    },
];

function ensureDataFile() {
    mkdirSync(dirname(dataFile), { recursive: true });

    if (!existsSync(dataFile)) {
        writeFileSync(dataFile, JSON.stringify(defaultEvents, null, 2));
    }
}

function readEvents() {
    ensureDataFile();

    try {
        const parsed = JSON.parse(readFileSync(dataFile, 'utf8'));
        return Array.isArray(parsed) ? parsed : defaultEvents;
    } catch {
        return defaultEvents;
    }
}

function writeEvents(events) {
    ensureDataFile();
    writeFileSync(dataFile, JSON.stringify(events, null, 2));
}

function sendJson(response, statusCode, payload) {
    response.writeHead(statusCode, {
        'Content-Type': 'application/json; charset=utf-8',
        'Cache-Control': 'no-store',
    });
    response.end(JSON.stringify(payload));
}

function readBody(request) {
    return new Promise((resolveBody, rejectBody) => {
        const chunks = [];
        request.on('data', (chunk) => chunks.push(chunk));
        request.on('end', () => resolveBody(Buffer.concat(chunks).toString('utf8')));
        request.on('error', rejectBody);
    });
}

function page() {
    return String.raw`<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sport Vlaanderen Hofstade - Event Heatmap</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; font-family: Arial, Helvetica, sans-serif; background: #f3f2ef; color: #111; }
        .app { display: grid; grid-template-columns: 360px 1fr; height: 100vh; }
        .panel { background: #fff; border-right: 1px solid #d4d0cb; overflow-y: auto; padding: 20px; z-index: 10; }
        .eyebrow { margin: 0 0 4px; color: #c83a2e; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; font-size: 12px; }
        h1 { margin: 0; color: #1f4867; font-size: 28px; }
        h2 { margin: 0 0 14px; color: #1f4867; font-size: 18px; }
        .intro, .small-text, .selected-hint, .event-count { color: #5d5853; line-height: 1.45; }
        .card { border: 1px solid #d4d0cb; border-radius: 14px; padding: 16px; margin-top: 16px; background: #fafafa; }
        label { display: block; margin-bottom: 12px; font-weight: 700; color: #333; }
        input { width: 100%; margin-top: 6px; border: 1px solid #c9c5c0; border-radius: 10px; padding: 11px; font-size: 15px; }
        .checkbox-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; cursor: pointer; }
        .checkbox-row input { width: auto; margin: 0; transform: scale(1.15); }
        button { width: 100%; border: 0; border-radius: 10px; padding: 12px; background: #c83a2e; color: white; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 4px; }
        button:hover { background: #9f0000; }
        .secondary-button { background: #1f4867; }
        .secondary-button:hover { background: #16364f; }
        .danger-button { margin-top: 10px; background: #8d847d; }
        .danger-button:hover { background: #6f6761; }
        .map-wrap { position: relative; min-height: 0; }
        #map { height: 100vh; width: 100%; }
        .map-tip { position: absolute; left: 50%; top: 14px; transform: translateX(-50%); z-index: 500; background: rgba(255,255,255,.94); border: 1px solid #d4d0cb; border-radius: 999px; padding: 9px 14px; font-weight: 700; color: #1f4867; box-shadow: 0 4px 16px rgba(0,0,0,.12); pointer-events: none; }
        .selected-coordinate-icon { width: 22px; height: 22px; margin-left: -11px; margin-top: -11px; border-radius: 50%; background: #c83a2e; border: 3px solid white; box-shadow: 0 0 0 3px rgba(200,58,46,.35); }
        .legend-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .legend-color { width: 34px; height: 14px; border-radius: 999px; display: inline-block; }
        .legend-color.low { background: #37ff00; } .legend-color.medium { background: #ffff00; } .legend-color.high { background: #ff0000; }
        .event-list { list-style: none; padding: 0; margin: 0; }
        .event-list li { border-top: 1px solid #e1ded9; padding: 10px 0; font-size: 14px; }
        .event-list strong { display: block; color: #111; }
        .event-list span { color: #666; font-size: 12px; }
        .print-title { display: none; }
        @media (max-width: 800px) { .app { grid-template-columns: 1fr; grid-template-rows: auto 1fr; } .panel { max-height: 44vh; border-right: 0; border-bottom: 1px solid #d4d0cb; } #map { height: 56vh; } .map-tip { top: 10px; font-size: 12px; max-width: calc(100vw - 28px); text-align: center; } }
        @media print { @page { size: landscape; margin: 10mm; } .panel, .map-tip, .leaflet-control-zoom, .leaflet-control-attribution { display: none !important; } .app, .map-wrap { display: block; height: auto; } .print-title { display: block; position: fixed; left: 16px; top: 16px; z-index: 9999; background: rgba(255,255,255,.94); border: 1px solid #d4d0cb; border-radius: 8px; padding: 10px 12px; color: #1f4867; font-size: 14px; } #map { height: 180mm; width: 100%; page-break-inside: avoid; } }
    </style>
</head>
<body>
<div class="app">
    <aside class="panel">
        <header>
            <p class="eyebrow">Sport Vlaanderen Hofstade</p>
            <h1>Event Heatmap</h1>
            <p class="intro">Termux backend mode. Events are saved on this Android device in <code>storage/app/termux-events.json</code>.</p>
        </header>

        <section class="card">
            <h2>Map options</h2>
            <label class="checkbox-row"><input id="toggleHeatmap" type="checkbox" checked /> Show heatmap</label>
            <label class="checkbox-row"><input id="toggleDots" type="checkbox" checked /> Show event dots</label>
            <button id="printMapButton" type="button" class="secondary-button">Print map</button>
        </section>

        <form id="eventForm" class="card">
            <h2>Add event</h2>
            <label>Event name <input id="eventName" type="text" placeholder="e.g. verdrinking" required /></label>
            <label>Latitude <input id="eventLat" type="number" step="0.000001" value="50.986290" required /></label>
            <label>Longitude <input id="eventLng" type="number" step="0.000001" value="4.514818" required /></label>
            <p id="selectedHint" class="selected-hint">Tap the map to select a precise location.</p>
            <button type="submit">Add to heatmap</button>
        </form>

        <section class="card">
            <h2>Data</h2>
            <p class="small-text">Saved through a local Node backend. This is not browser-only localStorage.</p>
            <button id="clearEventsButton" type="button" class="danger-button">Clear all events</button>
        </section>

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
const map = L.map('map', { zoomControl: true }).setView(hofstadeCenter, 17);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 20, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
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
const eventList = document.getElementById('eventList');
const eventCount = document.getElementById('eventCount');
const selectedHint = document.getElementById('selectedHint');
const toggleHeatmap = document.getElementById('toggleHeatmap');
const toggleDots = document.getElementById('toggleDots');
const printMapButton = document.getElementById('printMapButton');
const clearEventsButton = document.getElementById('clearEventsButton');
const selectedIcon = L.divIcon({ className: 'selected-coordinate-icon', iconSize: [22, 22], iconAnchor: [11, 11] });
function escapeHtml(value) { return String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;').replaceAll("'", '&#039;'); }
function renderHeatmap() { if (heatLayer) { map.removeLayer(heatLayer); heatLayer = null; } if (!showHeatmap) return; heatLayer = L.heatLayer(events.map(e => [e.latitude, e.longitude, 0.65]), { radius: 34, blur: 24, maxZoom: 19, gradient: { 0.0: 'white', 0.25: 'lime', 0.55: 'yellow', 0.8: 'orange', 1.0: 'red' } }).addTo(map); }
function renderMarkers() { markersLayer.clearLayers(); if (!showDots) return; events.forEach((event) => { const marker = L.circleMarker([event.latitude, event.longitude], { radius: 7, weight: 2, color: '#1f4867', fillColor: '#c83a2e', fillOpacity: .9 }); marker.bindPopup(`<strong>${escapeHtml(event.name)}</strong><br>Latitude: ${Number(event.latitude).toFixed(6)}<br>Longitude: ${Number(event.longitude).toFixed(6)}`); marker.addTo(markersLayer); }); }
function renderEventList() { eventCount.textContent = `${events.length} event${events.length === 1 ? '' : 's'} saved`; eventList.innerHTML = events.slice().reverse().map((event) => `<li><strong>${escapeHtml(event.name)}</strong><span>${Number(event.latitude).toFixed(6)}, ${Number(event.longitude).toFixed(6)}</span></li>`).join(''); }
function renderAll() { renderHeatmap(); renderMarkers(); renderEventList(); }
async function loadEvents() { const response = await fetch('/api/events', { cache: 'no-store' }); events = await response.json(); renderAll(); if (events.length > 0) { const bounds = L.latLngBounds(events.map(e => [e.latitude, e.longitude])); map.fitBounds(bounds.pad(.25), { maxZoom: 18 }); } }
function selectCoordinates(latlng) { const latitude = Number(latlng.lat.toFixed(6)); const longitude = Number(latlng.lng.toFixed(6)); eventLat.value = latitude; eventLng.value = longitude; selectedHint.textContent = `Selected: ${latitude}, ${longitude}`; if (selectedMarker) { selectedMarker.setLatLng([latitude, longitude]); } else { selectedMarker = L.marker([latitude, longitude], { icon: selectedIcon, draggable: true, zIndexOffset: 1000 }).addTo(map); selectedMarker.on('dragend', () => selectCoordinates(selectedMarker.getLatLng())); } selectedMarker.bindPopup(`<strong>Selected location</strong><br>Latitude: ${latitude.toFixed(6)}<br>Longitude: ${longitude.toFixed(6)}<br>Fill in a name and press “Add to heatmap”.`).openPopup(); }
function clearSelectedMarker() { if (selectedMarker) { map.removeLayer(selectedMarker); selectedMarker = null; } selectedHint.textContent = 'Tap the map to select a precise location.'; }
map.on('click', event => selectCoordinates(event.latlng));
toggleHeatmap.addEventListener('change', () => { showHeatmap = toggleHeatmap.checked; renderHeatmap(); });
toggleDots.addEventListener('change', () => { showDots = toggleDots.checked; renderMarkers(); });
printMapButton.addEventListener('click', () => { map.invalidateSize(); setTimeout(() => window.print(), 150); });
eventForm.addEventListener('submit', async (event) => { event.preventDefault(); const payload = { name: eventName.value.trim(), latitude: Number(eventLat.value), longitude: Number(eventLng.value) }; if (!payload.name || Number.isNaN(payload.latitude) || Number.isNaN(payload.longitude)) return; const response = await fetch('/api/events', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) }); events = await response.json(); renderAll(); map.flyTo([payload.latitude, payload.longitude], Math.max(map.getZoom(), 18), { duration: .7 }); eventName.value = ''; clearSelectedMarker(); });
clearEventsButton.addEventListener('click', async () => { if (!confirm('Clear all saved events?')) return; const response = await fetch('/api/events', { method: 'DELETE' }); events = await response.json(); renderAll(); });
loadEvents();
</script>
</body>
</html>`;
}

const server = createServer(async (request, response) => {
    const url = new URL(request.url || '/', `http://${request.headers.host || `${host}:${port}`}`);

    if (request.method === 'GET' && url.pathname === '/') {
        response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        response.end(page());
        return;
    }

    if (request.method === 'GET' && url.pathname === '/api/events') {
        sendJson(response, 200, readEvents());
        return;
    }

    if (request.method === 'POST' && url.pathname === '/api/events') {
        const raw = await readBody(request);
        const payload = JSON.parse(raw || '{}');
        const name = String(payload.name || '').trim();
        const latitude = Number(payload.latitude);
        const longitude = Number(payload.longitude);

        if (!name || Number.isNaN(latitude) || Number.isNaN(longitude)) {
            sendJson(response, 422, { error: 'A valid name, latitude, and longitude are required.' });
            return;
        }

        const events = readEvents();
        const nextId = events.reduce((max, event) => Math.max(max, Number(event.id || 0)), 0) + 1;
        events.push({ id: nextId, name, latitude, longitude, created_at: new Date().toISOString() });
        writeEvents(events);
        sendJson(response, 201, events);
        return;
    }

    if (request.method === 'DELETE' && url.pathname === '/api/events') {
        writeEvents([]);
        sendJson(response, 200, []);
        return;
    }

    sendJson(response, 404, { error: 'Not found' });
});

server.listen(port, host, () => {
    ensureDataFile();
    console.log(`Sport Vlaanderen Hofstade Heatmap is running at http://${host}:${port}`);
    console.log(`Persistent data file: ${dataFile}`);
    console.log('Press Ctrl+C to stop.');
});
