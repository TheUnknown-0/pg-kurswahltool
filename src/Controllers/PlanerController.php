<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\Deadline;
use App\Services\PlanerPage;

/**
 * Planer für Schüler: liefert die Seite mit den Startdaten aus, die eigene Schul-PDF und
 * speichert die aktuelle Wahl.
 */
final class PlanerController extends Controller
{
    private const MAX_STATE_BYTES = 128 * 1024;

    public function index(): string
    {
        $user = $this->ctx->auth->require();
        if ($user['role'] === 'admin') {
            return $this->redirect('/admin');
        }
        if ((new Deadline($this->ctx->db))->loginBlocked()) {
            $this->ctx->auth->logout();
            throw new HttpException(403, 'Die Abgabefrist ist abgelaufen. Die Anmeldung ist geschlossen.');
        }

        return PlanerPage::render($this->ctx, $user);
    }

    /** Die eigene, aktive Schul-PDF (Vorlage für das Formular mit Kreuzen). */
    public function pdf(): string
    {
        $user = $this->ctx->auth->require();
        $row = $this->ctx->db->fetchOne(
            'SELECT pdf, filename, sha256 FROM school_pdfs WHERE user_id = ? AND active = 1',
            [$user['id']],
        );
        if ($row === null) {
            throw new HttpException(404, 'Für dein Konto liegt keine Kurswahl-PDF vor.');
        }
        header('Content-Type: application/pdf');
        header('Cache-Control: private, no-store');
        header('X-Content-SHA256: ' . $row['sha256']);

        return (string) $row['pdf'];
    }

    /**
     * Speichert die Wahl. Mit „version“ wird erkannt, ob in der Zwischenzeit ein anderes
     * Gerät gespeichert hat; dann antwortet der Server mit 409 und dem aktuellen Stand.
     */
    public function saveState(): array
    {
        $user = $this->ctx->auth->require();
        $this->verifyCsrf();
        if ($user['role'] !== 'student') {
            throw new HttpException(403);
        }
        $raw = (string) file_get_contents('php://input');
        if (strlen($raw) > self::MAX_STATE_BYTES) {
            throw new HttpException(400, 'Die Wahl ist zu groß.');
        }
        $body = json_decode($raw, true);
        if (!is_array($body) || !is_array($body['state'] ?? null) || !is_int($body['version'] ?? null)) {
            throw new HttpException(400);
        }
        $this->assertUnlocked((int) $user['id']);
        $state = json_encode($body['state'], JSON_UNESCAPED_UNICODE);
        $summary = is_array($body['summary'] ?? null) ? json_encode($body['summary'], JSON_UNESCAPED_UNICODE) : null;
        $force = ($body['force'] ?? false) === true;

        return $this->ctx->db->transaction(function () use ($user, $state, $summary, $body, $force): array {
            $cur = $this->ctx->db->fetchOne('SELECT state, version, updated_at FROM selections WHERE user_id = ? FOR UPDATE', [$user['id']]);
            $curVersion = $cur === null ? 0 : (int) $cur['version'];
            if (!$force && $curVersion !== $body['version']) {
                http_response_code(409);

                return ['success' => false, 'conflict' => true, 'version' => $curVersion,
                    'state' => $cur === null ? null : json_decode((string) $cur['state'], true), 'savedAt' => $cur['updated_at'] ?? null];
            }
            $next = $curVersion + 1;
            $this->ctx->db->run(
                'INSERT INTO selections (user_id, state, version, summary) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE state = VALUES(state), version = VALUES(version), summary = VALUES(summary), updated_at = CURRENT_TIMESTAMP',
                [$user['id'], $state, $next, $summary],
            );

            return ['success' => true, 'version' => $next,
                'savedAt' => $this->ctx->db->fetchValue('SELECT updated_at FROM selections WHERE user_id = ?', [$user['id']])];
        });
    }

    /** Wahl verbindlich abgeben – danach gesperrt, bis der Admin freischaltet. */
    public function submit(): array
    {
        $user = $this->ctx->auth->require();
        $this->verifyCsrf();
        if ($user['role'] !== 'student') {
            throw new HttpException(403);
        }
        $this->assertUnlocked((int) $user['id']);
        $body = json_decode((string) file_get_contents('php://input'), true);
        $cur = $this->ctx->db->fetchOne('SELECT version FROM selections WHERE user_id = ?', [$user['id']]);
        if ($cur === null) {
            throw new HttpException(400, 'Es ist noch keine Wahl gespeichert.');
        }
        if ((int) $cur['version'] !== (int) ($body['version'] ?? -1)) {
            throw new HttpException(409, 'Deine Wahl wurde inzwischen geändert. Bitte lade die Seite neu und gib dann ab.');
        }
        $this->ctx->db->run('UPDATE selections SET submitted_at = NOW() WHERE user_id = ?', [$user['id']]);

        return ['success' => true, 'submittedAt' => $this->ctx->db->fetchValue('SELECT submitted_at FROM selections WHERE user_id = ?', [$user['id']])];
    }

    private function assertUnlocked(int $userId): void
    {
        $lock = (new Deadline($this->ctx->db))->stateFor($userId);
        if ($lock['locked']) {
            throw new HttpException(423, $lock['submittedAt'] !== null
                ? 'Deine Wahl ist abgegeben. Änderungen sind erst nach Freischaltung durch die Schule möglich.'
                : 'Die Abgabefrist ist abgelaufen. Änderungen sind nicht mehr möglich.');
        }
    }
}
