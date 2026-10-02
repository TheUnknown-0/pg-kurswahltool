<?php
/** @var list<string> $jahrgaenge @var string $jg @var bool $nurAbgegeben @var array $stat @var int $ohnePdf */
$q = static fn (string $j, bool $nur): string => '/admin/statistik?jg=' . rawurlencode($j) . ($nur ? '&nur=abgegeben' : '');
$max = static fn (array $rows): int => max(1, ...array_values(array_map(static fn ($v): int => is_array($v) ? max($v) : (int) $v, $rows ?: [1])));
$semTable = static function (string $title, array $rows) use ($max): string {
    if ($rows === []) {
        return '';
    }
    $m = $max($rows);
    $h = '<h3>' . e($title) . '</h3><div class="tablewrap"><table><tr><th>Kurs</th><th class="num">Q1</th><th class="num">Q2</th><th class="num">Q3</th><th class="num">Q4</th><th style="width:28%"></th></tr>';
    foreach ($rows as $name => $n) {
        $h .= '<tr><td>' . e($name) . '</td>';
        foreach ($n as $v) {
            $h .= '<td class="num">' . ($v ?: '<span class="muted">–</span>') . '</td>';
        }
        $h .= '<td><span class="bar"><i style="width:' . round(100 * max($n) / $m) . '%"></i></span></td></tr>';
    }

    return $h . '</table></div>';
};
$countTable = static function (string $title, array $rows) use ($max): string {
    if ($rows === []) {
        return '';
    }
    $m = $max($rows);
    $h = '<h3>' . e($title) . '</h3><table>';
    foreach ($rows as $name => $n) {
        $h .= '<tr><td>' . e($name) . '</td><td class="num">' . $n . '</td><td style="width:40%"><span class="bar"><i style="width:' . round(100 * $n / $m) . '%"></i></span></td></tr>';
    }

    return $h . '</table>';
};
?>
<main class="wrap">
  <?= $view->renderPartial('admin/_nav', ['nav' => 'statistik', 'flashes' => []]) ?>

  <section class="card">
    <h2>Statistik</h2>
    <p class="muted small">Grundlage sind die gespeicherten Wahlen (Stand beim letzten Speichern), zugeordnet über den Jahrgang der Schul-PDF.
      Zählt je Halbjahr, wie viele Schüler einen Kurs belegt haben – auch Wahlen, die laut Planer noch Fehler haben.</p>
    <nav class="tabs">
      <?php foreach ($jahrgaenge as $j): ?>
        <a class="tab <?= $jg === $j ? 'on' : '' ?>" href="<?= e($ctx->url($q($j, $nurAbgegeben))) ?>"><?= e($j) ?></a>
      <?php endforeach; ?>
      <?php if ($ohnePdf > 0): ?>
        <a class="tab <?= $jg === '' ? 'on' : '' ?>" href="<?= e($ctx->url($q('', $nurAbgegeben))) ?>">ohne Schul-PDF</a>
      <?php endif; ?>
    </nav>
    <p class="small">
      <a href="<?= e($ctx->url($q($jg, false))) ?>" class="<?= $nurAbgegeben ? '' : 'tag importiert' ?>">alle gespeicherten Wahlen</a> ·
      <a href="<?= e($ctx->url($q($jg, true))) ?>" class="<?= $nurAbgegeben ? 'tag importiert' : '' ?>">nur abgegebene</a>
    </p>
    <div class="stats">
      <div class="stat"><b><?= $stat['students'] ?></b>Schüler</div>
      <div class="stat"><b><?= $stat['saved'] ?></b>Wahl gespeichert</div>
      <div class="stat"><b><?= $stat['submitted'] ?></b>abgegeben</div>
      <div class="stat"><b><?= $stat['counted'] ?></b>ausgewertet</div>
      <div class="stat"><b><?= $stat['errors'] ?></b>davon mit Fehlern</div>
    </div>
  </section>

  <?php if ($stat['counted'] === 0): ?>
    <section class="card"><p class="muted">Noch keine Wahlen zum Auswerten.</p></section>
  <?php else: ?>
  <section class="card">
    <?= $semTable('Kurse je Halbjahr', $stat['kurse']) ?>
  </section>
  <div class="grid2">
    <section class="card">
      <?= $countTable('Leistungskurse', $stat['lk']) ?>
      <?= $countTable('LK-Kombinationen (1. + 2. LK)', $stat['lkPaare']) ?>
    </section>
    <section class="card">
      <?= $countTable('3. Prüfungsfach', $stat['pf3']) ?>
      <?= $countTable('4. Prüfungsfach', $stat['pf4']) ?>
      <?= $countTable('Referenzfach 5. PK', $stat['pk5']) ?>
      <?= $countTable('Form der 5. PK', $stat['pkForm']) ?>
    </section>
  </div>
  <div class="grid2">
    <section class="card"><?= $semTable('Sportkurse', $stat['sport']) ?: '<h3>Sportkurse</h3><p class="muted">keine</p>' ?></section>
    <section class="card"><?= $semTable('Zusatzkurse', $stat['zusatz']) ?: '<h3>Zusatzkurse</h3><p class="muted">keine</p>' ?></section>
  </div>
  <?php endif; ?>
</main>
