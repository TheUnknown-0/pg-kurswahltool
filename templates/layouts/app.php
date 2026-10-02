<?php /** @var string $content @var string $title */ ?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Kurswahl') ?> – Kurswahl-Planer</title>
<link rel="stylesheet" href="<?= e($ctx->url('/assets/app.css')) ?>">
</head>
<body>
<header class="wrap top">
  <div>
    <h1>Kurswahl-Planer</h1>
    <p class="subtitle"><?= e($subtitle ?? 'Qualifikationsphase am Paulsen-Gymnasium Berlin') ?></p>
  </div>
  <img class="logo" src="https://5pk.pg-hub.de/assets/logo-frei.png" alt="Paulsen-Gymnasium" data-hide-on-error>
</header>
<?= $content ?>
<footer class="wrap footer"><a href="<?= e($ctx->url('/datenschutz')) ?>">Datenschutz</a></footer>
<script src="<?= e($ctx->url('/assets/app.js')) ?>"></script>
</body>
</html>
