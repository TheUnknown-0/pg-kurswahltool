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
     *               latest: ?string, available: bool, entries: list<array{version: string, date: string, items: list<string>}>}
     */
    public function status(): array
    {
        $current = AppVersion::current();
        $raw = $this->db->fetchValue('SELECT value FROM settings WHERE name = ?', [self::KEY]);
        $s = is_string($raw) ? json_decode($raw, true) : null;
        $s = is_array($s) ? $s : [];
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
        ];
    }

    /** Letzte Prüfung älter als ein Tag (oder noch nie geprüft)? */
    public function due(): bool
    {
        $at = $this->status()['checked_at'];

        return $this->enabled() && ($at === null || $at < time() - self::INTERVAL);
    }

    /** Fragt GitHub ab und speichert das Ergebnis. Wirft nie; Fehler stehen im Ergebnis. */
    public function run(): array
    {
        if (!$this->enabled()) {
            return $this->status();
        }
        $base = rtrim($this->config['url'], '/');
        $data = ['checked_at' => time(), 'ok' => false, 'error' => '', 'latest' => null, 'entries' => []];
        try {
            $latest = trim($this->fetch($base . '/VERSION'));
            if (!AppVersion::valid($latest)) {
                throw new \RuntimeException('Ungültige Versionsnummer auf dem Server.');
            }
            $data['latest'] = $latest;
            if (version_compare($latest, AppVersion::current(), '>')) {
                // Nur die Einträge, die neuer sind als die installierte Version
                $data['entries'] = array_values(array_filter(
                    AppVersion::parse($this->fetch($base . '/CHANGELOG.md')),
                    static fn (array $en): bool => version_compare($en['version'], AppVersion::current(), '>'),
                ));
            }
            $data['ok'] = true;
        } catch (\Throwable $e) {
            $data['error'] = mb_substr($e->getMessage(), 0, 300);
            // Letzte bekannte Version behalten, damit ein kurzer Netzausfall den Hinweis nicht verschluckt
            $old = $this->status();
            if ($old['latest'] !== null) {
                $data['latest'] = $old['latest'];
                $data['entries'] = $old['entries'];
            }
        }
        $this->db->run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [self::KEY, json_encode($data, JSON_UNESCAPED_UNICODE)],
        );

        return $this->status();
    }

    private function fetch(string $url): string
    {
        $http = [
            'method' => 'GET',
            'timeout' => 4,
            'ignore_errors' => true,
            'user_agent' => 'Kurswahl-Planer/' . AppVersion::current() . ' (update check)',
            'header' => "Accept: text/plain\r\nCache-Control: no-cache\r\n",
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
        // Bei einem Proxy steht zuerst dessen Antwort auf CONNECT; die letzte Statuszeile zählt
        $status = 0;
        foreach ($headers as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($body === false || $status === 0) {
            throw new \RuntimeException('GitHub nicht erreichbar.');
        }
        if ($status !== 200) {
            throw new \RuntimeException("GitHub antwortet mit HTTP {$status}.");
        }
        if (strlen($body) > 512 * 1024) {
            throw new \RuntimeException('Antwort zu groß.');
        }

        return $body;
    }
}
