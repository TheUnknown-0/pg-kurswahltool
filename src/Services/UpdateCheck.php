<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Prüft, ob auf GitHub (Branch main) eine neuere Version liegt: liest dort VERSION und – nur wenn
 * neuer – CHANGELOG.md. Das Ergebnis steht in settings (update_check), damit jede Seite es ohne
 * Netzzugriff anzeigen kann. Geprüft wird bei jeder Admin-Anmeldung und einmal täglich im Worker.
 */
final class UpdateCheck
{
    private const KEY = 'update_check';
    public const INTERVAL = 86400;
    /** Bei der Anmeldung und per Knopf höchstens einmal pro Minute fragen */
    public const LOGIN_MIN_AGE = 60;
    public const MANUAL_MIN_AGE = 60;

    /** @param array{enabled: bool, url: string} $config */
    public function __construct(private readonly Database $db, private readonly array $config)
    {
    }

    public function enabled(): bool
    {
        return $this->config['enabled'];
    }

    /**
     * Gespeicherter Stand, bezogen auf die installierte Version.
     *
     * @return array{enabled: bool, current: string, checked_at: ?int, ok: bool, error: string,
     *               latest: ?string, available: bool, entries: list<array{version: string, date: string, items: list<string>}>,
     *               blocked_until: int}
     */
    public function status(): array
    {
        $current = AppVersion::current();
        $s = $this->stored();
        $latest = isset($s['latest']) && is_string($s['latest']) && AppVersion::valid($s['latest']) ? $s['latest'] : null;
        $available = $latest !== null && version_compare($latest, $current, '>');
        $entries = [];
        if ($available && is_array($s['entries'] ?? null)) {
            foreach ($s['entries'] as $en) {
                if (is_array($en) && is_string($en['version'] ?? null) && version_compare($en['version'], $current, '>')) {
                    $entries[] = [
                        'version' => $en['version'],
                        'date' => (string) ($en['date'] ?? ''),
                        'items' => array_values(array_map('strval', array_filter((array) ($en['items'] ?? []), 'is_scalar'))),
                    ];
                }
            }
        }

        return [
            'enabled' => $this->enabled(),
            'current' => $current,
            'checked_at' => isset($s['checked_at']) ? (int) $s['checked_at'] : null,
            'ok' => (bool) ($s['ok'] ?? false),
            'error' => (string) ($s['error'] ?? ''),
            'latest' => $latest,
            'available' => $available,
            'entries' => $entries,
            'blocked_until' => (int) ($s['blocked_until'] ?? 0),
        ];
    }

    /** Letzte Prüfung älter als ein Tag (oder noch nie geprüft) und GitHub sperrt nicht gerade? */
    public function due(): bool
    {
        $s = $this->status();

        return $this->enabled() && $s['blocked_until'] <= time() && ($s['checked_at'] === null || $s['checked_at'] < time() - self::INTERVAL);
    }

    /**
     * Fragt GitHub ab und speichert das Ergebnis. Wirft nie; Fehler stehen im Ergebnis.
     *
     * Schonend für die Limits von GitHub:
     * - $minAge: liegt die letzte Prüfung weniger Sekunden zurück, wird nicht erneut gefragt;
     * - VERSION mit ETag (If-None-Match), CHANGELOG.md nur bei einer neuen höheren Version;
     * - nach HTTP 429/403 wird bis Retry-After (sonst eine Stunde) gar nicht gefragt.
     *
     * @return array<string, mixed> status() plus 'skipped' (null, 'kuerzlich' oder 'gesperrt')
     */
    public function run(int $minAge = 0): array
    {
        if (!$this->enabled()) {
            return $this->status() + ['skipped' => null];
        }
        $old = $this->stored();
        $now = time();
        if ((int) ($old['blocked_until'] ?? 0) > $now) {
            return $this->status() + ['skipped' => 'gesperrt'];
        }
        if ($minAge > 0 && (int) ($old['checked_at'] ?? 0) > $now - $minAge) {
            return $this->status() + ['skipped' => 'kuerzlich'];
        }

        $base = rtrim($this->config['url'], '/');
        $oldLatest = is_string($old['latest'] ?? null) ? $old['latest'] : null;
        $oldEntries = is_array($old['entries'] ?? null) ? $old['entries'] : [];
        $data = ['checked_at' => $now, 'ok' => false, 'error' => '', 'latest' => $oldLatest, 'entries' => $oldEntries,
            'etag' => (string) ($old['etag'] ?? ''), 'blocked_until' => 0];
        try {
            $r = $this->fetch($base . '/VERSION', $oldLatest !== null ? $data['etag'] : '');
            if ($r['status'] === 304) {
                $latest = (string) $oldLatest;
            } else {
                $latest = trim($r['body']);
                if (!AppVersion::valid($latest)) {
                    throw new \RuntimeException('Ungültige Versionsnummer auf dem Server.');
                }
                $data['etag'] = $r['etag'];
            }
            $current = AppVersion::current();
            if (!version_compare($latest, $current, '>')) {
                $data['entries'] = [];
            } elseif ($latest !== $oldLatest || $oldEntries === []) {
                // Changelog nur laden, wenn sich die neueste Version geändert hat
                $data['entries'] = array_values(array_filter(
                    AppVersion::parse($this->fetch($base . '/CHANGELOG.md')['body']),
                    static fn (array $en): bool => version_compare($en['version'], $current, '>'),
                ));
            }
            $data['latest'] = $latest;
            $data['ok'] = true;
        } catch (RateLimited $e) {
            $data['error'] = 'GitHub begrenzt gerade die Abfragen; nächster Versuch ab ' . date('d.m.Y H:i', $now + $e->retryAfter) . '.';
            $data['blocked_until'] = $now + $e->retryAfter;
        } catch (\Throwable $e) {
            // Letzte bekannte Version bleibt stehen, damit ein kurzer Netzausfall den Hinweis nicht verschluckt
            $data['error'] = mb_substr($e->getMessage(), 0, 300);
        }
        $this->db->run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::KEY, json_encode($data, JSON_UNESCAPED_UNICODE)],
        );

        return $this->status() + ['skipped' => null];
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        $raw = $this->db->fetchValue('SELECT value FROM settings WHERE name = ?', [self::KEY]);
        $s = is_string($raw) ? json_decode($raw, true) : null;

        return is_array($s) ? $s : [];
    }

    /** @return array{status: int, body: string, etag: string} */
    private function fetch(string $url, string $etag = ''): array
    {
        $http = [
            'method' => 'GET',
            'timeout' => 4,
            'ignore_errors' => true,
            'user_agent' => 'Kurswahl-Planer/' . AppVersion::current() . ' (update check)',
            'header' => "Accept: text/plain\r\n" . ($etag !== '' ? "If-None-Match: {$etag}\r\n" : ''),
        ];
        // Proxy der Schule, falls über HTTPS_PROXY gesetzt
        $proxy = str_starts_with($url, 'https://') ? (getenv('HTTPS_PROXY') ?: getenv('https_proxy') ?: '') : '';
        if ($proxy !== '' && ($p = parse_url($proxy)) !== false && isset($p['host'])) {
            $http['proxy'] = 'tcp://' . $p['host'] . ':' . ($p['port'] ?? 80);
            if (isset($p['user'])) {
                $http['header'] .= 'Proxy-Authorization: Basic ' . base64_encode(urldecode($p['user']) . ':' . urldecode($p['pass'] ?? '')) . "\r\n";
            }
        }
        $body = @file_get_contents($url, false, stream_context_create(['http' => $http]));
        $headers = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        // Bei einem Proxy steht zuerst dessen Antwort auf CONNECT; es zählt die letzte Antwort
        $status = 0;
        $h = [];
        foreach ($headers as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                $status = (int) $m[1];
                $h = [];
            } elseif (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $h[strtolower(trim($k))] = trim($v);
            }
        }
        if ($body === false || $status === 0) {
            throw new \RuntimeException('GitHub nicht erreichbar.');
        }
        if ($status === 429 || ($status === 403 && (($h['x-ratelimit-remaining'] ?? '') === '0' || isset($h['retry-after'])))) {
            $retry = isset($h['retry-after']) && ctype_digit($h['retry-after']) ? (int) $h['retry-after'] : 3600;
            throw new RateLimited(max(60, min(86400, $retry)));
        }
        if ($status === 304) {
            return ['status' => 304, 'body' => '', 'etag' => $etag];
        }
        if ($status !== 200) {
            throw new \RuntimeException("GitHub antwortet mit HTTP {$status}.");
        }
        if (strlen($body) > 512 * 1024) {
            throw new \RuntimeException('Antwort zu groß.');
        }

        return ['status' => 200, 'body' => $body, 'etag' => $h['etag'] ?? ''];
    }
}
