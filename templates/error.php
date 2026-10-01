<main class="wrap">
  <div class="card hero login">
    <h2><?= e($title) ?></h2>
    <p><?= nl2br(e($message)) ?></p>
    <p><a class="btn" href="<?= e($ctx->url('/')) ?>">Zur Startseite</a></p>
  </div>
</main>
