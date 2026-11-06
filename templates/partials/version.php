<?php
/**
 * Versionsnummer für alle; für Admins klickbar mit Changelog und Update-Hinweis.
 *
 * @var \App\Core\Context $ctx @var \App\Core\Auth $auth @var \App\Core\Csrf $csrf
 */
use App\Services\AppVersion;
use App\Services\UpdateCheck;

$version = AppVersion::current();
if (!$auth->isAdmin()): ?>
  <span class="version">Version <?= e($version) ?></span>
<?php return; endif;

$u = (new UpdateCheck($ctx->db, $ctx->config['update']))->status();
$renderEntries = static function (array $entries): void {
    foreach ($entries as $en): ?>
      <h4><?= e($en['version']) ?><?php if ($en['date'] !== ''): ?> <span class="muted small">– <?= e($en['date']) ?></span><?php endif; ?></h4>
      <ul><?php foreach ($en['items'] as $it): ?><li><?= AppVersion::itemHtml($it) ?></li><?php endforeach; ?></ul>
    <?php endforeach;
};
?>
<button type="button" class="version admin<?= $u['available'] ? ' has-update' : '' ?>" data-dialog="changelog" title="Changelog anzeigen">
  Version <?= e($version) ?><?php if ($u['available']): ?> · <b>Update <?= e($u['latest']) ?> verfügbar</b><?php endif; ?>
</button>
<dialog id="changelog" class="changelog" aria-labelledby="changelog-title">
  <div class="changelog-head">
    <h3 id="changelog-title">Kurswahl-Planer <?= e($version) ?></h3>
    <form method="dialog"><button class="btn sm" type="submit" aria-label="Schließen">Schließen</button></form>
  </div>
  <?php if (!$u['enabled']): ?>
    <p class="msg warn small">Die Update-Prüfung ist abgeschaltet (<code>UPDATE_CHECK=0</code>).</p>
  <?php else: ?>
    <form method="post" action="<?= e($ctx->url('/admin/update-check')) ?>" class="changelog-check">
      <?= $csrf->field() ?>
      <button class="btn primary sm" type="submit">Nach neuen Versionen suchen</button>
      <span class="muted small">
        <?php if ($u['checked_at'] === null): ?>
          Noch nicht nach Updates gesucht.
        <?php else: ?>
          Zuletzt geprüft <?= e(date('d.m.Y H:i', $u['checked_at'])) ?>:
          <?= $u['ok'] ? ($u['available'] ? 'Update verfügbar.' : 'aktuell.') : 'fehlgeschlagen – ' . e($u['error']) ?>
        <?php endif; ?>
      </span>
    </form>
    <?php if ($u['available']): ?>
      <div class="msg warn small">
        Version <?= e($u['latest']) ?> ist verfügbar. Aktualisieren auf dem Server:
        <code>git pull &amp;&amp; docker compose up -d --build</code>
      </div>
      <h3 class="changelog-sub">Neu gegenüber der installierten Version</h3>
      <?php $renderEntries($u['entries']); ?>
    <?php endif; ?>
  <?php endif; ?>
  <h3 class="changelog-sub">Changelog (installiert)</h3>
  <?php $renderEntries(AppVersion::changelog()); ?>
</dialog>
