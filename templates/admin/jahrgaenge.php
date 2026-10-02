<?php
/**
 * @var list<string> $jahrgaenge @var string $selected @var array<string, list<int>> $pflichtGrid @var bool $pflichtStored
 * @var array $angebot @var bool $angebotStored @var array $catalog @var ?array{students: int, pdfs: int, saved: int} $counts
 */
$isPreset = $selected === '';
$followHint = static fn (bool $stored): string => $stored || $isPreset ? '' :
    '<p class="msg warn small"><b>Noch nicht festgelegt:</b> angezeigt wird die Vorlage. Der Jahrgang folgt ihr, bis du hier speicherst oder seine ersten PDFs importiert werden.</p>';
$box = static fn (string $name, int $q, bool $on, string $pair, string $label): string => sprintf(
    '<input type="checkbox" class="pbox" name="%s[]" value="%d" data-pair="%s" aria-label="%s" %s>',
    e($name), $q, e($pair), e($label), $on ? 'checked' : '');
?>
<main class="wrap">
  <?= $view->renderPartial('admin/_nav', ['nav' => 'jahrgaenge', 'flashes' => $flashes]) ?>

  <section class="card">
    <h2>Jahrgänge</h2>
    <p class="muted small">Pflichtkurse und Kursangebot gelten je Abiturjahrgang (aus der Schul-PDF des Schülers). Einstellbar sind der
      aktuelle und die zwei folgenden Jahrgänge sowie jeder Jahrgang, für den schon PDFs vorliegen. Ein Jahrgang folgt der Vorlage,
      bis er gespeichert wird oder seine ersten PDFs importiert werden; danach ändert die Vorlage ihn nicht mehr.
      Schüler ohne Schul-PDF bekommen die Vorlage.</p>
    <nav class="tabs">
      <?php foreach ($jahrgaenge as $jg): ?>
        <a class="tab <?= $selected === $jg ? 'on' : '' ?>" href="<?= e($ctx->url('/admin/jahrgaenge?jg=' . rawurlencode($jg))) ?>"><?= e($jg) ?></a>
      <?php endforeach; ?>
      <a class="tab <?= $isPreset ? 'on' : '' ?>" href="<?= e($ctx->url('/admin/jahrgaenge?jg=')) ?>">Vorlage für neue Jahrgänge</a>
    </nav>
    <?php if ($counts !== null): ?>
      <p class="small"><b><?= e($selected) ?>:</b> <?= $counts['students'] ?> Schülerkonten, <?= $counts['pdfs'] ?> PDFs, <?= $counts['saved'] ?> gespeicherte Wahlen.</p>
    <?php endif; ?>
  </section>

  <section class="card" id="pflicht">
    <h2>Pflichtkurse <?= $isPreset ? '– Vorlage' : '– ' . e($selected) ?></h2>
    <p class="muted small">Angekreuzte Kurse setzt der Planer bei jedem Schüler fest. Ein Kreuz gilt immer für den Halbjahresblock
      (Q1+Q2 bzw. Q3+Q4). Für Geschichte und Politikwissenschaft darf stattdessen der bilinguale Kurs gewählt werden; regulär und
      bilingual schließen sich aus. Deutsch und Mathematik in allen vier Halbjahren prüft der Planer unabhängig davon (VO-GO).</p>
    <?= $followHint($pflichtStored) ?>
    <form method="post" action="<?= e($ctx->url('/admin/jahrgaenge/pflicht')) ?>">
      <?= $csrf->field() ?><input type="hidden" name="jg" value="<?= e($selected) ?>">
      <div class="tablewrap"><table class="pgrid">
        <tr><th>Fach</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th></tr>
        <?php foreach (\App\Services\Pflicht::SUBJECTS as $id => [$name, , $bili]):
          $offered = $angebot['faecher'][$id] ?? []; ?>
          <tr class="<?= $offered === [] ? 'muted' : '' ?>">
            <td><?= e($name) ?><?= $bili ? ' <small class="muted">(oder bilingual)</small>' : '' ?><?= $offered === [] ? ' <small>– nicht angeboten</small>' : '' ?></td>
            <?php for ($q = 1; $q <= 4; $q++): ?>
              <td><?= in_array($q, $offered, true) ? $box("pflicht[{$id}]", $q, in_array($q, $pflichtGrid[$id], true), "p-{$id}-" . ($q <= 2 ? 1 : 2), "{$name} Q{$q}") : '' ?></td>
            <?php endfor; ?>
          </tr>
        <?php endforeach; ?>
      </table></div>
      <p><button class="btn primary" type="submit"><?= $isPreset ? 'Vorlage speichern' : 'Pflichtkurse für ' . e($selected) . ' speichern' ?></button></p>
    </form>
  </section>

  <section class="card" id="angebot">
    <h2>Kursangebot <?= $isPreset ? '– Vorlage' : '– ' . e($selected) ?></h2>
    <p class="muted small">Was angeboten wird und in welchen Halbjahren. Nicht angebotene Fächer sind im Planer gesperrt, nicht angebotene
      Zusatz- und Sportkurse erscheinen dort nicht. Ein Kreuz gilt für den Halbjahresblock. Zusatzkurse lassen sich nur in den Halbjahren
      anbieten, die im Kurswahlformular der Schule stehen. Sportkurse stehen nicht im Formular und können frei ergänzt werden.</p>
    <?= $followHint($angebotStored) ?>
    <form method="post" action="<?= e($ctx->url('/admin/jahrgaenge/angebot')) ?>">
      <?= $csrf->field() ?><input type="hidden" name="jg" value="<?= e($selected) ?>">
      <div class="grid2">
        <div>
          <h3>Fächer</h3>
          <div class="tablewrap"><table class="pgrid">
            <tr><th>Fach</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th></tr>
            <?php foreach ($catalog['faecher'] as $f):
              $on = $angebot['faecher'][$f['id']] ?? []; ?>
              <tr>
                <td><?= e($f['name']) ?><?= !empty($f['immer']) ? ' <small class="muted">(immer)</small>' : '' ?></td>
                <?php for ($q = 1; $q <= 4; $q++): ?>
                  <td><?= !empty($f['immer'])
                    ? '<input type="checkbox" checked disabled aria-label="' . e($f['name']) . ' Q' . $q . ' (immer)">'
                    : $box("fach[{$f['id']}]", $q, in_array($q, $on, true), "f-{$f['id']}-" . ($q <= 2 ? 1 : 2), "{$f['name']} Q{$q}") ?></td>
                <?php endfor; ?>
              </tr>
            <?php endforeach; ?>
          </table></div>
        </div>
        <div>
          <h3>Zusatzkurse</h3>
          <div class="tablewrap"><table class="pgrid">
            <tr><th>Zusatzkurs</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th></tr>
            <?php foreach ($catalog['zusatz'] as $z):
              $on = $angebot['zusatz'][$z['id']] ?? []; ?>
              <tr>
                <td><?= e($z['name']) ?></td>
                <?php for ($q = 1; $q <= 4; $q++): ?>
                  <td><?= in_array($q, $z['sems'], true) ? $box("zusatz[{$z['id']}]", $q, in_array($q, $on, true), "z-{$z['id']}-" . ($q <= 2 ? 1 : 2), "{$z['name']} Q{$q}") : '' ?></td>
                <?php endfor; ?>
              </tr>
            <?php endforeach; ?>
          </table></div>

        </div>
      </div>
      <h3 style="margin-top:22px">Sportkurse</h3>
      <p class="muted small">Je Halbjahr einzeln. Neue Kurse in die leeren Zeilen eintragen; einen Kurs entfernen = Kürzel leeren.</p>
      <div class="tablewrap"><table class="sportgrid">
        <tr><th>Kürzel</th><th>Name</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th></tr>
        <?php
        $rows = [];
        foreach ($angebot['sport']['katalog'] as $code => $name) {
            $rows[] = [$code, $name];
        }
        for ($i = 0; $i < 3; $i++) {
            $rows[] = ['', ''];
        }
        foreach ($rows as $n => [$code, $name]): ?>
          <tr>
            <td><input type="text" name="sport[<?= $n ?>][code]" value="<?= e($code) ?>" maxlength="5" size="4" aria-label="Kürzel"></td>
            <td><input type="text" name="sport[<?= $n ?>][name]" value="<?= e($name) ?>" maxlength="60" aria-label="Name"></td>
            <?php for ($q = 1; $q <= 4; $q++): ?>
              <td><input type="checkbox" class="pbox" name="sport[<?= $n ?>][q][]" value="<?= $q ?>" aria-label="<?= e($name ?: 'neuer Kurs') ?> Q<?= $q ?>"
                <?= $code !== '' && in_array($code, $angebot['sport']['sems'][$q - 1], true) ? 'checked' : '' ?>></td>
            <?php endfor; ?>
          </tr>
        <?php endforeach; ?>
      </table></div>
      <p><button class="btn primary" type="submit"><?= $isPreset ? 'Vorlage speichern' : 'Kursangebot für ' . e($selected) . ' speichern' ?></button></p>
    </form>
  </section>

  <?php if (!$isPreset): ?>
  <section class="card danger-zone" id="loeschen">
    <h2>Jahrgang löschen</h2>
    <p class="small">Löscht endgültig alle Daten von <b><?= e($selected) ?></b>: die Konten der Schüler, deren Schul-PDF diesen Jahrgang trägt,
      mit ihren gespeicherten Wahlen, alle PDFs des Jahrgangs und seine Pflichtkurse und sein Kursangebot.
      Schüler ohne Schul-PDF sind keinem Jahrgang zugeordnet und bleiben erhalten. Vorher am besten exportieren.</p>
    <?php if ($counts !== null): ?>
      <p class="small">Betroffen: <?= $counts['students'] ?> Konten, <?= $counts['pdfs'] ?> PDFs, <?= $counts['saved'] ?> gespeicherte Wahlen.</p>
    <?php endif; ?>
    <form method="post" action="<?= e($ctx->url('/admin/jahrgaenge/loeschen')) ?>" class="narrow" data-confirm="<?= e($selected) ?> wirklich endgültig löschen?">
      <?= $csrf->field() ?><input type="hidden" name="jg" value="<?= e($selected) ?>">
      <label for="bestaetigung">Zur Bestätigung „<?= e($selected) ?>“ eintippen</label>
      <input type="text" id="bestaetigung" name="bestaetigung" autocomplete="off" required>
      <p><button class="btn danger" type="submit">Jahrgang endgültig löschen</button></p>
    </form>
  </section>
  <?php endif; ?>
</main>
