import { createServer } from 'node:http';
import { createReadStream, existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, extname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const scriptFile = fileURLToPath(import.meta.url);
const appDir = dirname(dirname(scriptFile));
const publicDir = join(appDir, 'public', 'termux');
const dataFile = join(appDir, 'storage', 'app', 'termux-events.json');
const host = process.env.HOST || '127.0.0.1';
const port = Number(process.env.PORT || 8000);

const initialEvents = [
  { id: 1, name: 'verdrinking', latitude: 50.98629, longitude: 4.514818, weight: 1 }
];

const mimeTypes = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8'
};

function ensureDataFile() {
  mkdirSync(dirname(dataFile), { recursive: true });
  if (!existsSync(dataFile)) {
    writeFileSync(dataFile, JSON.stringify(initialEvents, null, 2));
  }
}

function readEvents() {
  ensureDataFile();
  try {
    const events = JSON.parse(readFileSync(dataFile, 'utf8'));
    return Array.isArray(events) ? events : initialEvents;
  } catch {
    return initialEvents;
  }
}

function writeEvents(events) {
  ensureDataFile();
  writeFileSync(dataFile, JSON.stringify(events, null, 2));
}

function json(response, status, payload) {
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store'
  });
  response.end(JSON.stringify(payload));
}

function body(request) {
  return new Promise((resolveBody) => {
    const chunks = [];
    request.on('data', chunk => chunks.push(chunk));
    request.on('end', () => resolveBody(Buffer.concat(chunks).toString('utf8')));
  });
}

function staticFile(pathname) {
  const cleanPath = pathname === '/' ? '/index.html' : pathname;
  const file = resolve(publicDir, '.' + cleanPath);
  if (!file.startsWith(publicDir) || !existsSync(file)) return null;
  return file;
}

const server = createServer(async (request, response) => {
  const url = new URL(request.url || '/', 'http://' + request.headers.host);

  if (url.pathname === '/api/events' && request.method === 'GET') {
    return json(response, 200, readEvents());
  }

  if (url.pathname === '/api/events' && request.method === 'DELETE') {
    writeEvents([]);
    return json(response, 200, []);
  }

  if (url.pathname === '/api/events' && request.method === 'POST') {
    const payload = JSON.parse((await body(request)) || '{}');
    const name = String(payload.name || '').trim();
    const latitude = Number(payload.latitude);
    const longitude = Number(payload.longitude);
    const weight = Number(payload.weight || 1);

    if (!name || Number.isNaN(latitude) || Number.isNaN(longitude)) {
      return json(response, 422, { error: 'Invalid event.' });
    }

    const events = readEvents();
    events.push({ id: Date.now(), name, latitude, longitude, weight });
    writeEvents(events);
    return json(response, 201, events);
  }

  const file = staticFile(url.pathname);
  if (!file) return json(response, 404, { error: 'Not found' });

  response.writeHead(200, { 'Content-Type': mimeTypes[extname(file)] || 'application/octet-stream' });
  createReadStream(file).pipe(response);
});

server.on('error', (error) => {
  if (error.code === 'EADDRINUSE') {
    console.error('Port ' + port + ' is already in use.');
    console.error('Stop the old server or run on another port, for example: PORT=8001 node scripts/termux-node-backend-v2.mjs');
    process.exit(1);
  }

  throw error;
});

server.listen(port, host, () => {
  ensureDataFile();
  console.log('Sport Vlaanderen Hofstade Heatmap is running at http://' + host + ':' + port);
  console.log('Project root: ' + appDir);
  console.log('Static files: ' + publicDir);
  console.log('Persistent data file: ' + dataFile);
  console.log('Press Ctrl+C to stop.');
});
