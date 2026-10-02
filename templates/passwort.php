<?php /** @var ?string $error @var int $minLength @var string $back @var ?string $nav */ ?>
<main class="wrap">
  <div class="card hero login">
    <span class="eyebrow">Konto</span>
    <h2 style="margin-top:8px">Passwort ändern</h2>
    <?php if ($error): ?><p class="msg error"><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="<?= e($ctx->url('/passwort')) ?>">
      <?= $csrf->field() ?>
      <input type="text" name="username" value="<?= e($auth->user()['login']) ?>" autocomplete="username" hidden>
      <label for="current">Bisheriges Passwort</label>
      <input type="password" id="current" name="current" autocomplete="current-password" required autofocus>
      <label for="new">Neues Passwort <span class="muted small">(mindestens <?= $minLength ?> Zeichen)</span></label>
      <input type="password" id="new" name="new" autocomplete="new-password" minlength="<?= $minLength ?>" required>
      <label for="confirm">Neues Passwort wiederholen</label>
      <input type="password" id="confirm" name="confirm" autocomplete="new-password" minlength="<?= $minLength ?>" required>
      <button class="btn primary" type="submit">Passwort ändern</button>
    </form>
    <p class="small" style="margin-top:16px"><a href="<?= e($ctx->url($back)) ?>">Zurück</a></p>
  </div>
</main>
