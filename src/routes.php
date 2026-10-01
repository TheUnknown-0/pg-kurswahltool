<?php

declare(strict_types=1);

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\HealthController;
use App\Controllers\PlanerController;
use App\Core\Router;

return static function (Router $r): void {
    $r->get('/healthz', [HealthController::class, 'healthz']);
    $r->get('/readyz', [HealthController::class, 'readyz']);

    $r->get('/login', [AuthController::class, 'form']);
    $r->post('/login', [AuthController::class, 'login']);
    $r->post('/logout', [AuthController::class, 'logout']);

    // Schüler
    $r->get('/', [PlanerController::class, 'index']);
    $r->get('/api/pdf', [PlanerController::class, 'pdf']);
    $r->post('/api/state', [PlanerController::class, 'saveState']);
    $r->post('/api/submit', [PlanerController::class, 'submit']);

    // Admin
    $r->get('/admin', [AdminController::class, 'index']);
    $r->get('/admin/api/status', [AdminController::class, 'status']);
    $r->post('/admin/upload', [AdminController::class, 'upload']);
    $r->post('/admin/users/import', [AdminController::class, 'importUsers']);
    $r->post('/admin/users/create', [AdminController::class, 'createUser']);
    $r->post('/admin/users/{id}/password', [AdminController::class, 'setPassword']);
    $r->post('/admin/users/{id}/delete', [AdminController::class, 'deleteUser']);
    $r->post('/admin/pdfs/{id}/assign', [AdminController::class, 'assignPdf']);
    $r->post('/admin/pdfs/{id}/delete', [AdminController::class, 'deletePdf']);
    $r->get('/admin/pdfs/{id}', [AdminController::class, 'downloadPdf']);
    $r->post('/admin/settings', [AdminController::class, 'saveSettings']);
    $r->get('/admin/students/{id}', [AdminController::class, 'viewStudent']);
    $r->get('/admin/students/{id}/pdf', [AdminController::class, 'studentPdf']);
    $r->get('/admin/students/{id}/form', [AdminController::class, 'studentForm']);
    $r->post('/admin/students/{id}/unlock', [AdminController::class, 'unlock']);
    $r->get('/admin/export/formulare', [AdminController::class, 'exportForms']);
    $r->get('/admin/export/wahlen', [AdminController::class, 'exportCsv']);
};
