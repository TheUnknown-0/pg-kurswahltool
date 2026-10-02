<?php /** @var string $nav @var list<array{type: string, message: string}> $flashes */
$items = ['uebersicht' => ['/admin', 'Übersicht'], 'jahrgaenge' => ['/admin/jahrgaenge', 'Jahrgänge'], 'statistik' => ['/admin/statistik', 'Statistik'],
    'einstellungen' => ['/admin/einstellungen', 'Einstellungen'], 'protokoll' => ['/admin/protokoll', 'Protokoll']]; ?>
<nav class="card adminnav">
  <div class="tabs">
    <?php foreach ($items as $k => [$href, $label]): ?>
      <a class="tab <?= $nav === $k ? 'on' : '' ?>" href="<?= e($ctx->url($href)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </div>
  <form method="post" action="<?= e($ctx->url('/logout')) ?>" class="inline">
    <?= $csrf->field() ?>
    <span class="muted small"><?= e($auth->user()['login']) ?></span>
    <a class="btn sm" href="<?= e($ctx->url('/passwort')) ?>">Passwort ändern</a>
    <button class="btn sm" type="submit">Abmelden</button>
  </form>
</nav>
<?php foreach ($flashes ?? [] as $f): ?>
  <p class="msg <?= e($f['type']) ?>"><?= e($f['message']) ?></p>
<?php endforeach; ?>
