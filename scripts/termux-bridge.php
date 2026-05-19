<?php

$inputPath = $argv[1] ?? null;

if (! $inputPath || ! is_file($inputPath)) {
    fwrite(STDERR, "Missing request payload.\n");
    exit(1);
}

$payload = json_decode(file_get_contents($inputPath), true);

if (! is_array($payload)) {
    fwrite(STDERR, "Invalid request payload.\n");
    exit(1);
}

$method = strtoupper($payload['method'] ?? 'GET');
$uri = $payload['uri'] ?? '/';
$path = $payload['path'] ?? '/';
$queryString = $payload['query'] ?? '';
$headers = $payload['headers'] ?? [];
$body = base64_decode($payload['body'] ?? '', true);

if ($body === false) {
    $body = '';
}

$publicPath = realpath(__DIR__ . '/../public');
$indexPath = $publicPath . '/index.php';
$bodyStream = fopen('php://temp', 'r+');
fwrite($bodyStream, $body);
rewind($bodyStream);

$GLOBALS['HTTP_RAW_POST_DATA'] = $body;

$_GET = [];
parse_str($queryString, $_GET);
$_POST = [];

$contentType = $headers['content-type'] ?? $headers['Content-Type'] ?? '';

if ($method === 'POST' && str_contains($contentType, 'application/x-www-form-urlencoded')) {
    parse_str($body, $_POST);
}

$_COOKIE = [];
$cookieHeader = $headers['cookie'] ?? $headers['Cookie'] ?? '';

if ($cookieHeader !== '') {
    foreach (explode(';', $cookieHeader) as $cookie) {
        $parts = explode('=', trim($cookie), 2);
        if (count($parts) === 2) {
            $_COOKIE[$parts[0]] = urldecode($parts[1]);
        }
    }
}

$_FILES = [];
$_REQUEST = array_merge($_GET, $_POST);

$_SERVER = [
    'DOCUMENT_ROOT' => $publicPath,
    'SCRIPT_FILENAME' => $indexPath,
    'SCRIPT_NAME' => '/index.php',
    'PHP_SELF' => '/index.php',
    'REQUEST_METHOD' => $method,
    'REQUEST_URI' => $uri,
    'QUERY_STRING' => $queryString,
    'PATH_INFO' => $path,
    'SERVER_NAME' => '127.0.0.1',
    'SERVER_PORT' => '8000',
    'SERVER_PROTOCOL' => 'HTTP/1.1',
    'SERVER_SOFTWARE' => 'termux-node-php-cli',
    'REMOTE_ADDR' => '127.0.0.1',
    'REMOTE_PORT' => '12345',
    'HTTPS' => 'off',
    'CONTENT_TYPE' => $contentType,
    'CONTENT_LENGTH' => strlen($body),
];

foreach ($headers as $name => $value) {
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));

    if (! in_array($key, ['HTTP_CONTENT_TYPE', 'HTTP_CONTENT_LENGTH'], true)) {
        $_SERVER[$key] = $value;
    }
}

chdir(dirname(__DIR__));

ob_start();
require $indexPath;
$output = ob_get_clean();

$status = http_response_code() ?: 200;
$responseHeaders = headers_list();

header_remove();

fwrite(STDOUT, json_encode([
    'status' => $status,
    'headers' => $responseHeaders,
    'body' => base64_encode($output),
], JSON_UNESCAPED_SLASHES));
