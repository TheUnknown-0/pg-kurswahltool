<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Einstellung je Abiturjahrgang mit Vorlage (Pflichtkurse, Kursangebot).
 *
 * Ein Jahrgang folgt der Vorlage, bis für ihn gespeichert wird oder seine ersten Schul-PDFs importiert
 * werden; dann bekommt er eine eigene Kopie. Spätere Änderungen an der Vorlage wirken nicht rückwirkend.
 * Schüler ohne Schul-PDF (also ohne Jahrgang) erhalten die Vorlage.
 */
abstract class JahrgangSetting
{
    /** Präfix der Einträge in settings: {name}_vorlage und {name}_jg:{Jahrgang} */
    abstract protected function name(): string;

    /** Wert, wenn noch keine Vorlage gespeichert ist */
    abstract protected function standard(): mixed;

    /** Bereinigt einen gespeicherten bzw. eingesandten Wert */
    abstract protected function clean(mixed $value): mixed;

    public function __construct(protected readonly Database $db)
    {
    }

    public function preset(): mixed
    {
        $raw = $this->get($this->name() . '_vorlage') ?? $this->legacyPreset();

        return $raw === null ? $this->standard() : $this->clean(json_decode($raw, true));
    }

    /** Wert eines Jahrgangs; beim ersten Zugriff wird die Vorlage kopiert. */
    public function forJahrgang(?string $jahrgang): mixed
    {
        $jahrgang = trim((string) $jahrgang);
        if ($jahrgang === '') {
            return $this->preset();
        }
        $raw = $this->get($this->key($jahrgang));
        if ($raw !== null) {
            return $this->clean(json_decode($raw, true));
        }
        $value = $this->preset();
        $this->set($this->key($jahrgang), $value);

        return $value;
    }

    /** Wert zur Anzeige, ohne einen noch nicht festgelegten Jahrgang festzuschreiben. */
    public function peek(string $jahrgang): mixed
    {
        $raw = $this->get($this->key($jahrgang));

        return $raw === null ? $this->preset() : $this->clean(json_decode($raw, true));
    }

    public function isStored(string $jahrgang): bool
    {
        return $this->get($this->key($jahrgang)) !== null;
    }

    /** @param string|null $jahrgang null = Vorlage */
    public function store(?string $jahrgang, mixed $value): void
    {
        $this->set($jahrgang === null ? $this->name() . '_vorlage' : $this->key($jahrgang), $this->clean($value));
    }

    public function forget(string $jahrgang): void
    {
        $this->db->run('DELETE FROM settings WHERE name = ?', [$this->key($jahrgang)]);
    }

    /** Ältere Speicherform der Vorlage (vor den Jahrgängen) */
    protected function legacyPreset(): ?string
    {
        return null;
    }

    protected function get(string $name): ?string
    {
        $v = $this->db->fetchValue('SELECT value FROM settings WHERE name = ?', [$name]);

        return $v === null ? null : (string) $v;
    }

    private function set(string $name, mixed $value): void
    {
        $this->db->run(
            'INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, json_encode($value, JSON_UNESCAPED_UNICODE)],
        );
    }

    private function key(string $jahrgang): string
    {
        return $this->name() . '_jg:' . $jahrgang;
    }
}
