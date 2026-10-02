<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Services\AdminLog;
use App\Services\Jahrgaenge;
use App\Services\Kursangebot;
use App\Services\Pflicht;

/**
 * Einstellungen je Abiturjahrgang: Pflichtkurse, Kursangebot und Löschen eines Jahrgangs.
 */
final class JahrgaengeController extends Controller
{
    public function index(): string
    {
        $this->ctx->auth->requireAdmin();
        $db = $this->ctx->db;
        $jahrgaenge = Jahrgaenge::all($db);
        $selected = (string) ($_GET['jg'] ?? $jahrgaenge[0]);
        if ($selected !== '' && !in_array($selected, $jahrgaenge, true)) {
            $selected = $jahrgaenge[0];
        }
        $pflicht = new Pflicht($db);
        $angebot = new Kursangebot($db);
        // Anzeigen legt nichts fest: ein neuer Jahrgang folgt der Vorlage, bis er gespeichert oder importiert wird
        $p = $selected === '' ? $pflicht->preset() : $pflicht->peek($selected);
        $a = $selected === '' ? $angebot->preset() : $angebot->peek($selected);

        return $this->ctx->view->render('admin/jahrgaenge', [
            'title' => 'Jahrgänge', 'subtitle' => 'Administration', 'nav' => 'jahrgaenge',
            'flashes' => $this->ctx->session->pullFlashes(),
            'jahrgaenge' => $jahrgaenge,
            'selected' => $selected,
            'pflichtGrid' => Pflicht::grid($p),
            'pflichtStored' => $selected === '' || $pflicht->isStored($selected),
            'angebot' => $a,
            'angebotStored' => $selected === '' || $angebot->isStored($selected),
            'catalog' => Kursangebot::catalog(),
            'counts' => $selected === '' ? null : $this->counts($selected),
        ]);
    }

    public function savePflicht(): string
    {
        $jg = $this->postedJahrgang();
        $marks = is_array($_POST['pflicht'] ?? null) ? $_POST['pflicht'] : [];
        (new Pflicht($this->ctx->db))->save($jg === '' ? null : $jg, $marks);
        AdminLog::add($this->ctx, 'pflicht', $jg === '' ? 'Vorlage' : $jg);
        $this->ctx->session->flash('ok', $jg === ''
            ? 'Vorlage gespeichert. Sie gilt für Jahrgänge, die noch nicht festgelegt sind, und für Schüler ohne Schul-PDF.'
            : "Pflichtkurse für {$jg} gespeichert. Sie gelten ab dem nächsten Öffnen des Planers (abgegebene Wahlen bleiben unverändert).");

        return $this->back($jg, 'pflicht');
    }

    public function saveAngebot(): string
    {
        $jg = $this->postedJahrgang();
        $faecher = [];
        foreach ((array) ($_POST['fach'] ?? []) as $id => $marked) {
            $faecher[(string) $id] = Pflicht::blocks((array) $marked);
        }
        $zusatz = [];
        foreach ((array) ($_POST['zusatz'] ?? []) as $id => $marked) {
            $zusatz[(string) $id] = Pflicht::blocks((array) $marked);
        }
        $katalog = [];
        $sems = [[], [], [], []];
        foreach ((array) ($_POST['sport'] ?? []) as $row) {
            $code = strtoupper(trim((string) ($row['code'] ?? '')));
            $name = trim((string) ($row['name'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $katalog[$code] = $name;
            foreach ((array) ($row['q'] ?? []) as $q) {
                if (in_array((int) $q, [1, 2, 3, 4], true)) {
                    $sems[(int) $q - 1][] = $code;
                }
            }
        }
        (new Kursangebot($this->ctx->db))->store($jg === '' ? null : $jg, [
            'faecher' => $faecher, 'zusatz' => $zusatz, 'sport' => ['katalog' => $katalog, 'sems' => $sems],
        ]);
        AdminLog::add($this->ctx, 'angebot', $jg === '' ? 'Vorlage' : $jg);
        $this->ctx->session->flash('ok', $jg === ''
            ? 'Vorlage für das Kursangebot gespeichert.'
            : "Kursangebot für {$jg} gespeichert. Es gilt ab dem nächsten Öffnen des Planers.");

        return $this->back($jg, 'angebot');
    }

    /**
     * Löscht alle Daten eines Jahrgangs: Konten der Schüler, deren Schul-PDF diesen Jahrgang trägt,
     * mit ihren Wahlen, alle PDFs des Jahrgangs und seine Einstellungen.
     */
    public function delete(): string
    {
        $jg = $this->postedJahrgang();
        if ($jg === '') {
            throw new HttpException(400);
        }
        if (trim((string) ($_POST['bestaetigung'] ?? '')) !== $jg) {
            $this->ctx->session->flash('error', "Zum Löschen bitte „{$jg}“ genau so eintippen.");

            return $this->back($jg, 'loeschen');
        }
        $n = $this->counts($jg);
        $this->ctx->db->transaction(function () use ($jg): void {
            $db = $this->ctx->db;
            $db->run(
                "DELETE u FROM users u JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1
                 WHERE p.jahrgang = ? AND u.role = 'student'",
                [$jg],
            );
            $db->run('DELETE FROM school_pdfs WHERE jahrgang = ?', [$jg]);
            (new Pflicht($db))->forget($jg);
            (new Kursangebot($db))->forget($jg);
        });
        AdminLog::add($this->ctx, 'jahrgang_geloescht', "{$jg}: {$n['students']} Konten, {$n['pdfs']} PDFs");
        $this->ctx->session->flash('ok', "{$jg} gelöscht: {$n['students']} Konten mit ihren Wahlen und {$n['pdfs']} PDFs.");

        return $this->redirect('/admin/jahrgaenge');
    }

    /** @return array{students: int, pdfs: int, saved: int} */
    private function counts(string $jg): array
    {
        $db = $this->ctx->db;

        return [
            'students' => (int) $db->fetchValue("SELECT COUNT(*) FROM school_pdfs p JOIN users u ON u.id = p.user_id WHERE p.active = 1 AND p.jahrgang = ? AND u.role = 'student'", [$jg]),
            'pdfs' => (int) $db->fetchValue('SELECT COUNT(*) FROM school_pdfs WHERE jahrgang = ?', [$jg]),
            'saved' => (int) $db->fetchValue('SELECT COUNT(*) FROM school_pdfs p JOIN selections s ON s.user_id = p.user_id WHERE p.active = 1 AND p.jahrgang = ?', [$jg]),
        ];
    }

    private function postedJahrgang(): string
    {
        $this->ctx->auth->requireAdmin();
        $this->verifyCsrf();
        $jg = trim((string) ($_POST['jg'] ?? ''));
        if ($jg !== '' && !in_array($jg, Jahrgaenge::all($this->ctx->db), true)) {
            throw new HttpException(400, 'Unbekannter Jahrgang.');
        }

        return $jg;
    }

    private function back(string $jg, string $anchor): string
    {
        return $this->redirect('/admin/jahrgaenge?jg=' . rawurlencode($jg) . '#' . $anchor);
    }
}
