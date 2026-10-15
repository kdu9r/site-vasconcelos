<?php /** @var string $conteudo */ ?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($titulo_pagina ?? 'Painel') ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/painel.css')) ?>">
</head>
<body class="p-login-body">
<main class="p-login">
  <img src="<?= e(asset('img/logo.svg')) ?>" alt="<?= e(config('site_nome')) ?>" width="227" height="40">
  <?php foreach (take_flashes() as $f): ?>
    <div class="p-aviso p-aviso--<?= e($f['tipo']) ?>" role="status"><?= e($f['mensagem']) ?></div>
  <?php endforeach; ?>
  <?= $conteudo ?>
</main>
</body>
</html>
