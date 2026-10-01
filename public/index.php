<?php

declare(strict_types=1);

/**
 * Front-Controller: einziger Einstiegspunkt der Anwendung.
 */

use App\Core\Context;
use App\Core\HttpException;
use App\Core\Router;

/** @var Context $ctx */
$ctx = require dirname(__DIR__) . '/src/bootstrap.php';

// ---------- Security-Header ----------
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
// 'wasm-unsafe-eval': der Planer komprimiert die PDF mit zlib-ng als WebAssembly.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://5pk.pg-hub.de; style-src 'self' 'unsafe-inline'; script-src 'self' 'wasm-unsafe-eval'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

// ---------- Pfad ermitteln (BASE_URL-Präfix abstreifen) ----------
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$base = $ctx->config['app']['base_url'];
if ($base !== '' && str_starts_with($path, $base)) {
    $path = substr($path, strlen($base));
}
$path = '/' . trim($path, '/');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$isApi = str_contains($path, '/api/');

try {
    $router = new Router();
    (require dirname(__DIR__) . '/src/routes.php')($router);

    $match = $router->match($method, $path);
    if ($match === null) {
        throw new HttpException($router->allowsOtherMethod($method, $path) ? 405 : 404);
    }

    [$class, $action] = $match['handler'];
    $result = (new $class($ctx))->$action($match['params']);

    if (is_array($result)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } elseif (is_string($result)) {
        echo $result;
    }
} catch (HttpException $e) {
    if ($e->status === 401 && !$isApi) {
        header('Location: ' . $ctx->url('/login'), true, 303);
        exit;
    }
    if ($e->status === 419) {
        header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 419 Page Expired', true, 419);
    } else {
        http_response_code($e->status);
    }
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    } else {
        echo $ctx->view->render('error', ['status' => $e->status, 'message' => $e->getMessage(), 'title' => 'Fehler ' . $e->status]);
    }
} catch (Throwable $e) {
    error_log(sprintf('[Kurswahl] %s: %s in %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    http_response_code(500);
    $dev = ($ctx->config['app']['env'] ?? '') === 'development';
    $msg = $dev ? $e->getMessage() : 'Interner Serverfehler.';
    if ($isApi) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    } else {
        echo $ctx->view->render('error', ['status' => 500, 'message' => $msg, 'title' => 'Fehler 500']);
    }
}
