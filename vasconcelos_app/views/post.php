<?php
/** @var array $post @var array $imoveis @var array $outros */
$p = $post;
$capa = capa_post_url($p);
$json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'BlogPosting',
    'headline' => $p['titulo'],
    'description' => $p['resumo'] ?? '',
    'datePublished' => date('c', strtotime((string)$p['publicado_em'])),
    'dateModified' => date('c', strtotime((string)$p['atualizado_em'])),
    'author' => ['@type' => 'Person', 'name' => $p['autor_nome'] ?: config('site_nome')],
    'publisher' => ['@type' => 'Organization', 'name' => config('site_nome'), 'logo' => ['@type' => 'ImageObject', 'url' => url(asset('img/logo.svg'))]],
    'mainEntityOfPage' => url(post_url($p)),
    'image' => $capa ? url($capa) : url(asset('img/og-padrao.jpg')),
];
?>
<article class="post">
  <header class="post__cabeca">
    <nav class="trilha" aria-label="Você está em"><a href="/">Início</a> / <a href="/blog">Blog</a></nav>
    <h1><?= e($p['titulo']) ?></h1>
    <?php if ($p['resumo']): ?><p class="lead"><?= e($p['resumo']) ?></p><?php endif; ?>
    <p class="post__meta">
      <?= e($p['autor_nome'] ?: config('site_nome')) ?> · <time datetime="<?= e(date('Y-m-d', strtotime((string)$p['publicado_em']))) ?>"><?= e(data_extenso($p['publicado_em'])) ?></time> · <?= tempo_leitura((string)$p['conteudo']) ?> min de leitura
    </p>
  </header>
  <?php if ($capa): ?>
    <figure class="post__capa"><img src="<?= e($capa) ?>" alt="" fetchpriority="high"></figure>
  <?php endif; ?>
  <div class="post__texto">
    <?= render_texto((string)$p['conteudo']) ?>
  </div>
  <aside class="post__cta">
    <p><strong>Procurando imóvel em Bertioga?</strong> Fale direto com os corretores e diga o que você precisa.</p>
    <a class="botao botao--whats" href="<?= e(whatsapp_link(null, 'blog')) ?>" rel="nofollow"><?php partial('icone_whats', ['tam' => 18]); ?> Conversar no WhatsApp</a>
  </aside>
</article>

<?php if ($imoveis): ?>
<section class="secao-faixa" aria-labelledby="imoveis-post">
  <div class="secao">
    <h2 id="imoveis-post"><?= $p['bairro'] ? 'Imóveis em ' . e($p['bairro']) : 'Imóveis em destaque' ?></h2>
    <div class="grade">
      <?php foreach ($imoveis as $im) partial('card', ['im' => $im]); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($outros): ?>
<section class="secao" aria-labelledby="outros-posts">
  <h2 id="outros-posts">Leia também</h2>
  <ul class="outros-posts">
    <?php foreach (array_slice($outros, 0, 3) as $o): ?>
      <li><a href="<?= e(post_url($o)) ?>"><?= e($o['titulo']) ?></a><span><?= e(data_extenso($o['publicado_em'])) ?></span></li>
    <?php endforeach; ?>
  </ul>
</section>
<?php endif; ?>
