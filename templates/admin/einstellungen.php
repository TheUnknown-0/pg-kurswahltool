<?php /** @var array{deadline: ?string, mode: string} $deadline @var array<string, string> $deadlineModes @var string $datenschutz */ ?>
<main class="wrap">
  <?= $view->renderPartial('admin/_nav', ['nav' => 'einstellungen', 'flashes' => $flashes]) ?>

  <section class="card" id="frist">
    <h2>Abgabefrist</h2>
    <?php $expired = $deadline['deadline'] !== null && strtotime($deadline['deadline']) <= time(); ?>
    <p class="muted small">
      <?php if ($deadline['deadline'] === null): ?>Keine Frist gesetzt – Schüler können jederzeit ändern und abgeben.
      <?php else: ?>Frist: <b><?= e(date('d.m.Y, H:i', (int) strtotime($deadline['deadline']))) ?> Uhr</b><?= $expired ? ' – <b>abgelaufen</b>' : '' ?>.
      <?php endif; ?>
      Abgegebene Wahlen sind immer gesperrt, bis du sie in der Schülerliste freischaltest.</p>
    <form method="post" action="<?= e($ctx->url('/admin/settings')) ?>" class="narrow">
      <?= $csrf->field() ?>
      <label for="deadline">Abgabe bis</label>
      <input type="datetime-local" id="deadline" name="deadline" value="<?= $deadline['deadline'] ? e(date('Y-m-d\TH:i', (int) strtotime($deadline['deadline']))) : '' ?>">
      <label for="mode">Nach Ablauf der Frist</label>
      <select id="mode" name="mode">
        <?php foreach ($deadlineModes as $k => $label): ?><option value="<?= e($k) ?>" <?= $deadline['mode'] === $k ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
      </select>
      <p><button class="btn primary" type="submit">Speichern</button> <span class="muted small">Datum leeren = keine Frist</span></p>
    </form>
  </section>

  <section class="card" id="datenschutz">
    <h2>Datenschutzhinweis</h2>
    <p class="muted small">Erscheint unter <a href="<?= e($ctx->url('/datenschutz')) ?>">/datenschutz</a> und ist auf der Anmeldeseite und im Planer verlinkt.
      Der voreingestellte Text ist nur ein Entwurf – bitte von der Schule (Datenschutzbeauftragte/r) prüfen und ergänzen lassen.
      Leerzeile = neuer Absatz, kurze erste Zeile eines Absatzes = Überschrift, Zeilen mit „- “ = Aufzählung.</p>
    <form method="post" action="<?= e($ctx->url('/admin/datenschutz')) ?>">
      <?= $csrf->field() ?>
      <textarea name="text" rows="22" class="bigtext"><?= e($datenschutz) ?></textarea>
      <p><button class="btn primary" type="submit">Speichern</button></p>
    </form>
  </section>
</main>
