<main class="wrap">
  <div class="card hero login">
    <span class="eyebrow">Anmeldung</span>
    <h2 style="margin-top:8px">Plane deine Kurse bis zum Abitur</h2>
    <?php if ($error): ?><p class="msg error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e($ctx->url('/login')) ?>">
      <?= $csrf->field() ?>
      <label for="login">Login</label>
      <input type="text" id="login" name="login" value="<?= e($login) ?>" placeholder="vorname.nachname" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
      <label for="password">Passwort</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
      <button class="btn primary" type="submit">Anmelden</button>
    </form>
    <?php if (!empty($hilfeOeffentlich)): ?>
    <p class="help-hint"><a href="<?= e($ctx->url('/hilfe')) ?>">Hilfe und Erklärvideo zur Kurswahl</a></p>
    <?php endif; ?>
  </div>
</main>
