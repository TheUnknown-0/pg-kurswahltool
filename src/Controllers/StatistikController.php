<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Jahrgaenge;

/**
 * Kursstatistik für die Planung: belegte Kurse je Halbjahr, Leistungskurse, Prüfungsfächer.
 * Grundlage ist die Kurzfassung, die der Planer beim Speichern mitschickt.
 */
final class StatistikController extends Controller
{
    public function index(): string
    {
        $this->ctx->auth->requireAdmin();
        $db = $this->ctx->db;
        $jahrgaenge = array_values(array_intersect(
            Jahrgaenge::all($db),
            array_column($db->fetchAll("SELECT DISTINCT jahrgang FROM school_pdfs WHERE active = 1 AND user_id IS NOT NULL AND jahrgang <> ''"), 'jahrgang'),
        ));
        $jg = (string) ($_GET['jg'] ?? ($jahrgaenge[0] ?? ''));
        $nurAbgegeben = ($_GET['nur'] ?? '') === 'abgegeben';

        // Schüler des Jahrgangs (über ihre Schul-PDF); "" = Schüler ohne Schul-PDF
        [$where, $params] = $jg === ''
            ? ['p.id IS NULL', []]
            : ['p.jahrgang = ?', [$jg]];
        $rows = $db->fetchAll(
            "SELECT s.summary, s.submitted_at, s.user_id AS has_sel
             FROM users u
             LEFT JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1
             LEFT JOIN selections s ON s.user_id = u.id
             WHERE u.role = 'student' AND {$where}",
            $params,
        );

        $stat = ['students' => count($rows), 'saved' => 0, 'submitted' => 0, 'errors' => 0, 'counted' => 0,
            'kurse' => [], 'sport' => [], 'zusatz' => [], 'lk' => [], 'lkPaare' => [], 'pf3' => [], 'pf4' => [], 'pk5' => [], 'pkForm' => []];
        $inc = static function (array &$a, string $k, int $by = 1): void { $a[$k] = ($a[$k] ?? 0) + $by; };
        foreach ($rows as $r) {
            if ($r['has_sel'] !== null) {
                $stat['saved']++;
            }
            if ($r['submitted_at'] !== null) {
                $stat['submitted']++;
            }
            $sum = $r['summary'] ? json_decode((string) $r['summary'], true) : null;
            if (!is_array($sum) || ($nurAbgegeben && $r['submitted_at'] === null)) {
                continue;
            }
            $stat['counted']++;
            if (($sum['errors'] ?? 0) > 0) {
                $stat['errors']++;
            }
            foreach (($sum['sems'] ?? []) as $i => $names) {
                foreach ((array) $names as $name) {
                    $group = str_starts_with($name, 'Sport ') ? 'sport' : (str_starts_with($name, 'Zusatzkurs ') ? 'zusatz' : 'kurse');
                    $label = preg_replace('/^(Sport|Zusatzkurs) /', '', $name);
                    $stat[$group][$label] ??= [0, 0, 0, 0];
                    $stat[$group][$label][$i]++;
                }
            }
            $roles = $sum['roles'] ?? [];
            foreach (['lk1', 'lk2'] as $r2) {
                if (($roles[$r2] ?? '') !== '') {
                    $inc($stat['lk'], $roles[$r2]);
                }
            }
            if (($roles['lk1'] ?? '') !== '' && ($roles['lk2'] ?? '') !== '') {
                $inc($stat['lkPaare'], $roles['lk1'] . ' + ' . $roles['lk2']);
            }
            foreach (['pf3', 'pf4', 'pk5'] as $r2) {
                if (($roles[$r2] ?? '') !== '') {
                    $inc($stat[$r2], $roles[$r2]);
                }
            }
            if (($sum['pkForm'] ?? '') !== '') {
                $inc($stat['pkForm'], $sum['pkForm']);
            }
        }
        foreach (['kurse', 'sport', 'zusatz'] as $g) {
            uasort($stat[$g], static fn (array $a, array $b): int => array_sum($b) <=> array_sum($a));
        }
        foreach (['lk', 'lkPaare', 'pf3', 'pf4', 'pk5', 'pkForm'] as $g) {
            arsort($stat[$g]);
        }

        return $this->ctx->view->render('admin/statistik', [
            'title' => 'Statistik', 'subtitle' => 'Administration', 'nav' => 'statistik',
            'jahrgaenge' => $jahrgaenge, 'jg' => $jg, 'nurAbgegeben' => $nurAbgegeben, 'stat' => $stat,
            'ohnePdf' => (int) $db->fetchValue("SELECT COUNT(*) FROM users u LEFT JOIN school_pdfs p ON p.user_id = u.id AND p.active = 1 WHERE u.role = 'student' AND p.id IS NULL"),
        ]);
    }
}
