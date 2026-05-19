import { createServer } from 'node:http';
import { spawn } from 'node:child_process';
import { createReadStream, existsSync, statSync } from 'node:fs';
import { extname, join, normalize, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const appDir = resolve(fileURLToPath(new URL('..', import.meta.url)), '..');
const publicDir = join(appDir, 'public');
const indexPhp = join(publicDir, 'index.php');
const host = process.env.HOST || '127.0.0.1';
const port = Number(process.env.PORT || 8000);
const tmpDir = process.env.TMPDIR || join(process.env.HOME || appDir, 'tmp');

const mimeTypes = new Map([
    ['.css', 'text/css; charset=utf-8'],
    ['.js', 'text/javascript; charset=utf-8'],
    ['.mjs', 'text/javascript; charset=utf-8'],
    ['.json', 'application/json; charset=utf-8'],
    ['.html', 'text/html; charset=utf-8'],
    ['.svg', 'image/svg+xml'],
    ['.png', 'image/png'],
    ['.jpg', 'image/jpeg'],
    ['.jpeg', 'image/jpeg'],
    ['.gif', 'image/gif'],
    ['.webp', 'image/webp'],
    ['.ico', 'image/x-icon'],
    ['.woff', 'font/woff'],
    ['.woff2', 'font/woff2'],
]);

function readRequestBody(request) {
    return new Promise((resolveBody, rejectBody) => {
        const chunks = [];
        request.on('data', (chunk) => chunks.push(chunk));
        request.on('end', () => resolveBody(Buffer.concat(chunks)));
        request.on('error', rejectBody);
    });
}

function safePublicPath(pathname) {
    const decoded = decodeURIComponent(pathname);
    const normalized = normalize(decoded).replace(/^\.\.(\/|\\|$)/, '');
    const target = resolve(publicDir, `.${normalized}`);

    if (!target.startsWith(publicDir)) {
        return null;
    }

    return target;
}

function serveStaticFile(response, filePath) {
    const extension = extname(filePath).toLowerCase();
    const contentType = mimeTypes.get(extension) || 'application/octet-stream';

    response.writeHead(200, { 'Content-Type': contentType });
    createReadStream(filePath).pipe(response);
}

function parseCgiResponse(buffer) {
    const raw = buffer.toString('binary');
    let splitIndex = raw.indexOf('\r\n\r\n');
    let separatorLength = 4;

    if (splitIndex === -1) {
        splitIndex = raw.indexOf('\n\n');
        separatorLength = 2;
    }

    if (splitIndex === -1) {
        return {
            statusCode: 200,
            headers: {},
            body: buffer,
        };
    }

    const headerText = raw.slice(0, splitIndex);
    const body = buffer.subarray(splitIndex + separatorLength);
    const headers = {};
    let statusCode = 200;

    for (const line of headerText.split(/\r?\n/)) {
        const colon = line.indexOf(':');

        if (colon === -1) {
            continue;
        }

        const name = line.slice(0, colon).trim();
        const value = line.slice(colon + 1).trim();

        if (name.toLowerCase() === 'status') {
            const match = value.match(/^(\d{3})/);
            if (match) statusCode = Number(match[1]);
            continue;
        }

        const key = name.toLowerCase();

        if (headers[key]) {
            headers[key] = Array.isArray(headers[key])
                ? [...headers[key], value]
                : [headers[key], value];
        } else {
            headers[key] = value;
        }
    }

    return { statusCode, headers, body };
}

async function serveLaravel(request, response, url) {
    const body = await readRequestBody(request);
    const php = spawn('php-cgi', ['-d', `sys_temp_dir=${tmpDir}`], {
        cwd: appDir,
        env: {
            ...process.env,
            REDIRECT_STATUS: '200',
            GATEWAY_INTERFACE: 'CGI/1.1',
            SERVER_SOFTWARE: 'termux-node-php-cgi',
            SERVER_PROTOCOL: 'HTTP/1.1',
            SERVER_NAME: host,
            SERVER_PORT: String(port),
            REQUEST_METHOD: request.method || 'GET',
            REQUEST_URI: url.pathname + url.search,
            QUERY_STRING: url.searchParams.toString(),
            SCRIPT_FILENAME: indexPhp,
            SCRIPT_NAME: '/index.php',
            DOCUMENT_ROOT: publicDir,
            CONTENT_TYPE: request.headers['content-type'] || '',
            CONTENT_LENGTH: String(body.length),
            HTTP_HOST: request.headers.host || `${host}:${port}`,
            HTTP_USER_AGENT: request.headers['user-agent'] || '',
            HTTP_ACCEPT: request.headers.accept || '',
            HTTP_ACCEPT_LANGUAGE: request.headers['accept-language'] || '',
            HTTP_ACCEPT_ENCODING: request.headers['accept-encoding'] || '',
            HTTP_COOKIE: request.headers.cookie || '',
            HTTPS: 'off',
            TMPDIR: tmpDir,
        },
        stdio: ['pipe', 'pipe', 'pipe'],
    });

    const stdoutChunks = [];
    const stderrChunks = [];

    php.stdout.on('data', (chunk) => stdoutChunks.push(chunk));
    php.stderr.on('data', (chunk) => stderrChunks.push(chunk));
    php.stdin.end(body);

    php.on('error', (error) => {
        response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        response.end(`Could not start php-cgi. Install it with: pkg install php-cgi\n\n${error.message}`);
    });

    php.on('close', (code) => {
        const stderr = Buffer.concat(stderrChunks).toString('utf8');

        if (code !== 0) {
            response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
            response.end(`php-cgi exited with code ${code}\n\n${stderr}`);
            return;
        }

        const parsed = parseCgiResponse(Buffer.concat(stdoutChunks));
        response.writeHead(parsed.statusCode, parsed.headers);
        response.end(parsed.body);
    });
}

const server = createServer(async (request, response) => {
    try {
        const url = new URL(request.url || '/', `http://${request.headers.host || `${host}:${port}`}`);
        const filePath = safePublicPath(url.pathname);

        if (filePath && existsSync(filePath) && statSync(filePath).isFile() && !filePath.endsWith('.php')) {
            serveStaticFile(response, filePath);
            return;
        }

        await serveLaravel(request, response, url);
    } catch (error) {
        response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
        response.end(error instanceof Error ? error.stack : String(error));
    }
});

server.listen(port, host, () => {
    console.log(`Laravel is running at http://${host}:${port}`);
    console.log('Press Ctrl+C to stop the server.');
});
