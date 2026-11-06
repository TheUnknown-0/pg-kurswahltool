<?php
/**
 * @var array $stats @var list<array> $students @var list<array> $unassigned @var list<array> $studentOptions
 * @var list<array> $log @var list<array> $flashes @var string $q @var string $filter
 */
$fmt = static fn (?string $d): string => $d ? date('d.m.Y H:i', strtotime($d)) : '–';
?>
<main class="wrap">
  <?= $view->renderPartial('admin/_nav', ['nav' => 'uebersicht', 'flashes' => $flashes]) ?>

  <section class="card hero">
    <span class="eyebrow">Stand</span>
    <h2 style="margin-top:6px">Übersicht</h2>
    <div class="stats" id="stats" data-url="<?= e($ctx->url('/admin/api/status')) ?>" data-last-log="<?= (int) $stats['last_log_id'] ?>">
      <div class="stat"><b data-k="students"><?= $stats['students'] ?></b>Schülerkonten</div>
      <div class="stat"><b data-k="with_pdf"><?= $stats['with_pdf'] ?></b>mit Schul-PDF</div>
      <div class="stat"><b data-k="unassigned"><?= $stats['unassigned'] ?></b>PDFs ohne Konto</div>
      <div class="stat"><b data-k="saved"><?= $stats['saved'] ?></b>Wahlen gespeichert</div>
      <div class="stat"><b data-k="submitted"><?= $stats['submitted'] ?></b>abgegeben</div>
      <div class="stat"><b data-k="queue"><?= $stats['queue'] + $stats['folder'] ?></b>Dateien warten auf Import</div>
    </div>
    <p class="msg <?= $stats['worker_ok'] ? 'ok' : 'warn' ?> small" id="worker" style="margin-top:14px">
      <?= $stats['worker_ok']
        ? 'Import-Worker läuft (zuletzt aktiv ' . e($fmt($stats['worker_seen'])) . ').'
        : 'Import-Worker meldet sich nicht' . ($stats['worker_seen'] ? ' (zuletzt ' . e($fmt($stats['worker_seen'])) . ')' : '') . ' – hochgeladene Dateien werden erst eingelesen, wenn er läuft.' ?>
    </p>
  </section>

  <div class="grid2">
    <section class="card">
      <h2>Kurswahl-PDFs einlesen</h2>
      <p class="muted small">PDFs des Schulservers einzeln oder als Archiv (ZIP oder 7z). Die Verarbeitung läuft im Hintergrund; jede PDF wird
        über den Namen dem Konto <code>vorname.nachname</code> zugeordnet. Alternativ Dateien in den Import-Ordner
        des Servers legen.</p>
      <form method="post" action="<?= e($ctx->url('/admin/upload')) ?>" enctype="multipart/form-data">
        <?= $csrf->field() ?>
        <input type="file" name="files[]" accept=".pdf,.zip,.7z,application/pdf,application/zip,application/x-7z-compressed" multiple required>
        <p><button class="btn primary" type="submit">Hochladen</button></p>
      </form>
    </section>

    <section class="card">
      <h2>Schülerkonten importieren</h2>
      <p class="muted small">Vorläufiges Format: CSV mit Kopfzeile <code>login;passwort;name</code>
        (Spalte <code>name</code> optional, Trennzeichen <code>;</code> <code>,</code> oder Tab). Vorhandene Konten erhalten das
        neue Passwort. Danach werden passende PDFs automatisch zugeordnet.</p>
      <form method="post" action="<?= e($ctx->url('/admin/users/import')) ?>" enctype="multipart/form-data">
        <?= $csrf->field() ?>
        <input type="file" name="csv" accept=".csv,.txt,text/csv" required>
        <p><button class="btn primary" type="submit">Importieren</button></p>
      </form>
    </section>
  </div>

  <section class="card" id="export">
    <h2>Export</h2>
    <p class="muted small">Formulare: jede Kurswahl-PDF des Schulservers mit den Kreuzen der gespeicherten Wahl, byte-gleich
      zum Original und unter dem Original-Dateinamen. Ohne gespeicherte Wahl bleibt die PDF unausgefüllt (siehe <code>Hinweise.txt</code> im Archiv).
      Die Formulare kommen als 7z-Archiv; zum Öffnen unter Windows z.&nbsp;B. 7-Zip verwenden.
      Einzelne Schüler exportierst du unten in der Schülerliste (Spalte „Export“).</p>
    <p>
      <a class="btn primary" href="<?= e($ctx->url('/admin/export/formulare')) ?>">Alle Formulare (7z)</a>
      <a class="btn" href="<?= e($ctx->url('/admin/export/formulare?nur=abgegeben')) ?>">Nur abgegebene (7z)</a>
      <a class="btn" href="<?= e($ctx->url('/admin/export/wahlen')) ?>">Alle Wahlen (CSV)</a>
    </p>
  </section>

  <section class="card">
    <h2>Schüler hinzufügen</h2>
    <form method="post" action="<?= e($ctx->url('/admin/users/create')) ?>" class="addform">
      <?= $csrf->field() ?>
      <div><label for="add-name">Vorname Nachname</label>
        <input type="text" id="add-name" name="name" placeholder="z. B. Anna Schmidt" autocomplete="off"></div>
      <div><label for="add-login">Login <span class="muted small">(leer = aus dem Namen)</span></label>
        <input type="text" id="add-login" name="login" placeholder="vorname.nachname" autocapitalize="none" spellcheck="false" autocomplete="off"></div>
      <div><label for="add-pw">Passwort</label>
        <input type="text" id="add-pw" name="password" minlength="4" required autocomplete="new-password"></div>
      <div><button class="btn primary" type="submit">Anlegen</button></div>
    </form>
  </section>

  <?php if ($unassigned): ?>
  <section class="card">
    <h2>PDFs ohne Konto (<?= count($unassigned) ?>)</h2>
    <p class="muted small">Für diese PDFs gibt es kein Konto mit dem abgeleiteten Login. Lege das Konto an (Import) oder ordne von Hand zu.</p>
    <div class="tablewrap"><table>
      <tr><th>Name in der PDF</th><th>Klasse</th><th>erwarteter Login</th><th>Datei</th><th>Zuordnen</th><th></th></tr>
      <?php foreach ($unassigned as $p): ?>
      <tr>
        <td><?= e($p['name']) ?></td><td><?= e($p['klasse']) ?></td><td><code><?= e($p['login_key']) ?></code></td>
        <td><a href="<?= e($ctx->url('/admin/pdfs/' . $p['id'])) ?>"><?= e($p['filename']) ?></a></td>
        <td>
          <form method="post" action="<?= e($ctx->url('/admin/pdfs/' . $p['id'] . '/assign')) ?>" class="inline">
            <?= $csrf->field() ?>
            <select name="user_id" required>
              <option value="">Konto wählen …</option>
              <?php foreach ($studentOptions as $o): ?><option value="<?= (int) $o['id'] ?>"><?= e($o['login']) ?></option><?php endforeach; ?>
            </select>
            <button class="btn sm" type="submit">Zuordnen</button>
          </form>
        </td>
        <td>
          <form method="post" action="<?= e($ctx->url('/admin/pdfs/' . $p['id'] . '/delete')) ?>" class="inline" data-confirm="PDF von <?= e($p['name']) ?> löschen?">
            <?= $csrf->field() ?><button class="btn sm danger" type="submit">Löschen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </table></div>
  </section>
  <?php endif; ?>

  <section class="card">
    <h2>Schüler</h2>
    <form method="get" action="<?= e($ctx->url('/admin')) ?>" class="filters">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Login oder Name suchen">
      <select name="filter">
        <option value="">alle</option>
        <option value="ohne-pdf" <?= $filter === 'ohne-pdf' ? 'selected' : '' ?>>ohne Schul-PDF</option>
        <option value="ohne-wahl" <?= $filter === 'ohne-wahl' ? 'selected' : '' ?>>ohne gespeicherte Wahl</option>
        <option value="abgegeben" <?= $filter === 'abgegeben' ? 'selected' : '' ?>>abgegeben</option>
        <option value="nicht-abgegeben" <?= $filter === 'nicht-abgegeben' ? 'selected' : '' ?>>nicht abgegeben</option>
        <option value="fehler" <?= $filter === 'fehler' ? 'selected' : '' ?>>Wahl mit Fehlern</option>
      </select>
      <button class="btn sm" type="submit">Filtern</button>
    </form>
    <div class="tablewrap"><table>
      <tr><th>Login</th><th>Name (PDF)</th><th>Klasse</th><th>Schul-PDF</th><th>Wahl</th><th>Export</th><th>Letzte Anmeldung</th><th>Aktionen</th></tr>
      <?php foreach ($students as $s): ?>
      <tr>
        <td><code><?= e($s['login']) ?></code></td>
        <td><?= e($s['pdf_name'] ?? $s['display_name']) ?></td>
        <td><?= e($s['klasse'] ?? '') ?></td>
        <td><?php if ($s['pdf_id']): ?>
          <a href="<?= e($ctx->url('/admin/pdfs/' . $s['pdf_id'])) ?>">PDF</a>
          <form method="post" action="<?= e($ctx->url('/admin/pdfs/' . $s['pdf_id'] . '/assign')) ?>" class="inline" data-confirm="Zuordnung der PDF lösen?">
            <?= $csrf->field() ?><input type="hidden" name="user_id" value="0"><button class="btn sm" type="submit">lösen</button>
          </form>
        <?php else: ?><span class="muted">fehlt</span><?php endif; ?></td>
        <td>
          <?php if ($s['submitted_at']): ?><span class="tag importiert">abgegeben</span> <?= e($fmt($s['submitted_at'])) ?>
          <?php elseif ($s['saved_at']): ?>gespeichert <?= e($fmt($s['saved_at'])) ?>
          <?php else: ?><span class="muted">keine</span><?php endif; ?>
          <?php if ($s['errors'] !== null && (int) $s['errors'] > 0): ?><br><span class="tag fehler"><?= (int) $s['errors'] ?> Fehler</span>
          <?php elseif ($s['errors'] !== null): ?><br><span class="tag importiert">zulässig</span><?php endif; ?>
          <?php if ($s['saved_at']): ?><br><a href="<?= e($ctx->url('/admin/students/' . $s['id'])) ?>">ansehen</a><?php endif; ?>
          <?php if ($s['submitted_at']): ?>
            <form method="post" action="<?= e($ctx->url('/admin/students/' . $s['id'] . '/unlock')) ?>" class="inline" data-confirm="Wahl von <?= e($s['login']) ?> wieder freischalten?">
              <?= $csrf->field() ?><button class="btn sm" type="submit">freischalten</button>
            </form>
          <?php endif; ?>
        </td>
        <td class="nowrap">
          <?php if ($s['pdf_id']): ?><a class="btn sm" href="<?= e($ctx->url('/admin/students/' . $s['id'] . '/form')) ?>"
            title="<?= $s['saved_at'] ? 'Kurswahl-PDF mit den Kreuzen der gespeicherten Wahl' : 'Noch keine Wahl gespeichert – PDF unausgefüllt' ?>">Formular (PDF)</a><?php endif; ?>
          <a class="btn sm" href="<?= e($ctx->url('/admin/students/' . $s['id'] . '/csv')) ?>" title="Wahl dieses Schülers als CSV für Excel">Wahl (CSV)</a>
        </td>
        <td><?= e($fmt($s['last_login_at'])) ?></td>
        <td>
          <form method="post" action="<?= e($ctx->url('/admin/users/' . $s['id'] . '/password')) ?>" class="inline">
            <?= $csrf->field() ?>
            <input type="password" name="password" placeholder="neues Passwort" autocomplete="new-password" minlength="4" required>
            <button class="btn sm" type="submit">setzen</button>
          </form>
          <form method="post" action="<?= e($ctx->url('/admin/users/' . $s['id'] . '/delete')) ?>" class="inline" data-confirm="Konto <?= e($s['login']) ?> und gespeicherte Wahl löschen?">
            <?= $csrf->field() ?><button class="btn sm danger" type="submit">löschen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$students): ?><tr><td colspan="8" class="muted">Keine Konten gefunden.</td></tr><?php endif; ?>
    </table></div>
    <?php if (count($students) === 500): ?><p class="muted small">Es werden höchstens 500 Konten angezeigt – nutze die Suche.</p><?php endif; ?>
  </section>

  <section class="card">
    <h2>Import-Protokoll</h2>
    <div class="tablewrap"><table>
      <tr><th>Zeit</th><th>Quelle</th><th>Datei</th><th>Status</th><th>Hinweis</th></tr>
      <?php foreach ($log as $l): ?>
      <tr>
        <td><?= e($fmt($l['created_at'])) ?></td><td><?= e($l['source']) ?></td><td><?= e($l['filename']) ?></td>
        <td><span class="tag <?= e($l['status']) ?>"><?= e($l['status']) ?></span></td><td><?= e($l['message']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$log): ?><tr><td colspan="5" class="muted">Noch nichts eingelesen.</td></tr><?php endif; ?>
    </table></div>
  </section>
</main>
