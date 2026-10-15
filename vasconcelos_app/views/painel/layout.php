<?php
/** @var string $conteudo @var array $usuario */
$path = request_path();
$ativo = fn(string $p) => ($p === '/painel' ? $path === '/painel' || str_starts_with($path, '/painel/imovel') : str_starts_with($path, $p)) ? ' aria-current="page"' : '';
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf" content="<?= e(csrf_token()) ?>">
<title><?= e(($titulo_pagina ?? 'Painel') . ' · Painel') ?></title>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/painel.css')) ?>">
<script src="<?= e(asset('js/painel.js')) ?>" defer></script>
</head>
<body>
<header class="p-topo">
  <div class="p-topo__in">
    <a class="p-marca" href="/painel"><img src="<?= e(asset('img/logo.svg')) ?>" alt="<?= e(config('site_nome')) ?>" width="182" height="32"><span>Painel</span></a>
    <nav class="p-nav" aria-label="Painel">
      <a href="/painel"<?= $ativo('/painel') ?>>Imóveis</a>
      <a href="/painel/condominios"<?= $ativo('/painel/condominios') ?>>Condomínios</a>
      <a href="/painel/blog"<?= $ativo('/painel/blog') ?>>Blog</a>
      <a href="/painel/conta"<?= $ativo('/painel/conta') ?>>Minha conta</a>
      <?php if ($usuario['papel'] === 'admin'): ?>
        <a href="/painel/usuarios"<?= $ativo('/painel/usuarios') ?>>Acessos</a>
        <a href="/painel/integracoes"<?= $ativo('/painel/integracoes') ?>>Integrações</a>
        <a href="/painel/importar"<?= $ativo('/painel/importar') ?>>Migração</a>
        <a href="/painel/configuracoes"<?= $ativo('/painel/configuracoes') ?>>Configurações</a>
      <?php endif; ?>
      <a href="/" target="_blank" rel="noopener">Ver site ↗</a>
    </nav>
    <form method="post" action="/painel/sair" class="p-sair">
      <?= csrf_field() ?>
      <span><?= e($usuario['nome']) ?></span>
      <button type="submit">Sair</button>
    </form>
  </div>
</header>
<main class="p-main">
  <?php foreach (take_flashes() as $f): ?>
    <div class="p-aviso p-aviso--<?= e($f['tipo']) ?>" role="status"><?= e($f['mensagem']) ?></div>
  <?php endforeach; ?>
  <?= $conteudo ?>
</main>
</body>
</html>
