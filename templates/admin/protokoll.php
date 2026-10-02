<?php /** @var list<array> $rows */ $L = \App\Services\AdminLog::LABELS; ?>
<main class="wrap">
  <?= $view->renderPartial('admin/_nav', ['nav' => 'protokoll', 'flashes' => []]) ?>
  <section class="card">
    <h2>Protokoll der Admin-Aktionen</h2>
    <p class="muted small">Die letzten 500 Einträge. Passwörter werden nie protokolliert. Das Einlesen der PDFs steht im Import-Protokoll der Übersicht.</p>
    <div class="tablewrap"><table>
      <tr><th>Zeit</th><th>Admin</th><th>Aktion</th><th>Details</th><th>IP</th></tr>
      <?php foreach ($rows as $r): ?>
        <tr><td><?= e(date('d.m.Y H:i', (int) strtotime($r['created_at']))) ?></td><td><?= e($r['admin_login']) ?></td>
          <td><?= e($L[$r['action']] ?? $r['action']) ?></td><td><?= e($r['details']) ?></td><td class="muted small"><?= e($r['ip_address']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?><tr><td colspan="5" class="muted">Noch keine Einträge.</td></tr><?php endif; ?>
    </table></div>
  </section>
</main>
