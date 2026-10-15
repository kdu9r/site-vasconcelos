<?php
/** @var string $conteudo */
$tituloSite = (string)config('site_nome');
$titulo = isset($titulo_pagina) ? $titulo_pagina . ' | ' . $tituloSite : $tituloSite;
$descricao = $descricao_pagina ?? 'Imóveis à venda e para alugar em Bertioga, SP. Atendimento direto pelo WhatsApp.';
$canonicalUrl = url($canonical ?? request_path());
$ogImg = $og_imagem ?? asset('img/og-padrao.jpg');
if (str_starts_with($ogImg, '/')) $ogImg = url($ogImg);
$whatsImovel = $whats_imovel ?? null;
$corretores = (array)config('contato.corretores', []);
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titulo) ?></title>
<meta name="description" content="<?= e($descricao) ?>">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex, follow"><?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="<?= e($tituloSite) ?>">
<meta property="og:title" content="<?= e($titulo_pagina ?? $tituloSite) ?>">
<meta property="og:description" content="<?= e($descricao) ?>">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:image" content="<?= e($ogImg) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="theme-color" content="#082C86">
<?= integracoes_meta_tags() ?>
<link rel="icon" href="<?= e(asset('img/favicon.svg')) ?>" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&family=Sora:wght@600;700&display=swap">
<link rel="stylesheet" href="<?= e(asset('css/site.css')) ?>">
<?php if (!empty($json_ld)): ?>
<script type="application/ld+json" nonce="<?= e(csp_nonce()) ?>"><?= json_encode($json_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
<?php endif; ?>
<?= integracoes_config_json($whatsImovel) ?>
<script src="<?= e(asset('js/site.js')) ?>" nonce="<?= e(csp_nonce()) ?>" defer></script>
</head>
<body<?= !empty($pagina_home) ? ' class="home"' : '' ?>>
<a class="pular" href="#conteudo">Pular para o conteúdo</a>

<header class="topo">
  <div class="topo__in">
    <a class="marca" href="/" aria-label="<?= e($tituloSite) ?> — página inicial">
      <img src="<?= e(asset('img/logo.svg')) ?>" alt="<?= e($tituloSite) ?>" width="227" height="40">
    </a>
    <nav class="menu" id="menu" aria-label="Principal">
      <a href="/comprar">Comprar</a>
      <a href="/alugar">Alugar</a>
      <a href="/quem-somos">Quem somos</a>
      <a href="/blog">Blog</a>
      <a href="/anuncie">Anuncie seu imóvel</a>
      <a class="menu__whats" href="<?= e(whatsapp_link(null, 'menu')) ?>" rel="nofollow">
        <?php partial('icone_whats', ['tam' => 18]); ?>
        <?= e(whatsapp_rotulo_menu()) ?>
      </a>
    </nav>
    <button class="menu-botao" type="button" aria-controls="menu" aria-expanded="false" aria-label="Abrir menu">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
    </button>
  </div>
</header>

<?php foreach (take_flashes() as $f): ?>
  <div class="aviso aviso--<?= e($f['tipo']) ?>" role="status"><?= e($f['mensagem']) ?></div>
<?php endforeach; ?>

<main id="conteudo">
<?= $conteudo ?>
</main>

<section class="contato" id="contato" aria-labelledby="contato-titulo">
  <div class="contato__in">
    <div class="contato__texto">
      <h2 id="contato-titulo">Fale direto com os corretores</h2>
      <p>Sem formulário, sem espera. Escolha quem vai te atender e tire suas dúvidas pelo WhatsApp.</p>
    </div>
    <div class="contato__lista">
      <?php foreach ($corretores as $c): ?>
        <div class="corretor">
          <?php if ($fotoC = foto_corretor($c)): ?>
            <img class="corretor__avatar" src="<?= e($fotoC) ?>" alt="" width="48" height="48" loading="lazy">
          <?php else: ?>
            <span class="corretor__avatar" aria-hidden="true"><?= e($c['iniciais'] ?? '') ?></span>
          <?php endif; ?>
          <div class="corretor__info">
            <strong><?= e($c['nome']) ?></strong>
            <span><?= e($c['creci']) ?></span>
          </div>
          <a class="botao botao--whats botao--pequeno" href="<?= e(whatsapp_link($whatsImovel, 'corretor', $c['whatsapp'])) ?>" rel="nofollow">
            <?php partial('icone_whats', ['tam' => 16]); ?> <?= e(formata_telefone($c['whatsapp'])) ?>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<footer class="rodape">
  <div class="rodape__in">
    <div class="rodape__marca">
      <img src="<?= e(asset('img/logo-branco.svg')) ?>" alt="<?= e($tituloSite) ?>" width="204" height="36" loading="lazy">
      <span><?= e(config('contato.endereco')) ?></span>
      <a href="mailto:<?= e(config('contato.email')) ?>"><?= e(config('contato.email')) ?></a>
    </div>
    <nav class="rodape__links" aria-label="Rodapé">
      <a href="/comprar">Comprar</a>
      <a href="/alugar">Alugar</a>
      <a href="/quem-somos">Quem somos</a>
      <a href="/blog">Blog</a>
      <a href="/anuncie">Anuncie seu imóvel</a>
      <a href="/privacidade">Política de privacidade</a>
      <?php if (usa_rastreamento() && config_site('pedir_consentimento') !== '0'): ?><button type="button" class="rodape__cookies" data-cookies-abrir>Preferências de cookies</button><?php endif; ?>
    </nav>
  </div>
  <p class="rodape__legal">© <?= date('Y') ?> <?= e($tituloSite) ?>. Valores e disponibilidade sujeitos a alteração sem aviso prévio.</p>
</footer>

<?php if (empty($sem_flutuante)): ?>
<a class="whats-flutuante" href="<?= e(whatsapp_link($whatsImovel, 'flutuante')) ?>" rel="nofollow" aria-label="Falar no WhatsApp">
  <?php partial('icone_whats', ['tam' => 26]); ?>
  <span>Fale com a gente</span>
</a>
<?php endif; ?>
<?php if (usa_rastreamento() && config_site('pedir_consentimento') !== '0'): ?>
<div class="cookies" id="cookies" role="dialog" aria-labelledby="cookies-titulo" hidden>
  <p id="cookies-titulo">Usamos cookies do Google e da Meta para medir visitas e mostrar nossos imóveis em anúncios. <a href="/privacidade">Saiba mais</a></p>
  <div class="cookies__botoes">
    <button type="button" class="botao botao--contorno botao--pequeno" data-cookies="0">Recusar</button>
    <button type="button" class="botao botao--primario botao--pequeno" data-cookies="1">Aceitar</button>
  </div>
</div>
<?php endif; ?>
</body>
</html>
