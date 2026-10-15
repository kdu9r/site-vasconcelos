<?php /** @var array $posts @var int $pagina @var int $paginas */ ?>
<section class="sobre-topo">
  <div class="secao sobre-topo__in">
    <p class="sobretitulo">Blog</p>
    <h1>Morar e investir em Bertioga</h1>
    <p class="lead">Bairros, custos, documentação e o dia a dia de quem vive na praia.</p>
  </div>
</section>

<section class="secao">
  <?php if (!$posts): ?>
    <p class="vazio">Em breve, os primeiros textos.</p>
  <?php else: ?>
    <div class="grade grade--posts">
      <?php foreach ($posts as $p): ?>
        <article class="card card--post">
          <a class="card__link" href="<?= e(post_url($p)) ?>">
            <div class="card__foto">
              <?php if ($capa = capa_post_url($p, 'p')): ?>
                <img src="<?= e($capa) ?>" alt="" loading="lazy" width="640" height="427">
              <?php else: ?>
                <img src="<?= e(asset('img/hero-placeholder.svg')) ?>" alt="" loading="lazy" width="640" height="427">
              <?php endif; ?>
            </div>
            <div class="card__corpo">
              <p class="card__data"><?= e(data_extenso($p['publicado_em'])) ?> · <?= tempo_leitura((string)$p['conteudo']) ?> min de leitura</p>
              <h2 class="card__titulo card__titulo--post"><?= e($p['titulo']) ?></h2>
              <?php if ($p['resumo']): ?><p class="card__resumo"><?= e($p['resumo']) ?></p><?php endif; ?>
            </div>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
    <?php if ($paginas > 1): ?>
      <nav class="paginacao" aria-label="Páginas">
        <?php for ($i = 1; $i <= $paginas; $i++): ?>
          <?php if ($i === $pagina): ?><span aria-current="page"><?= $i ?></span><?php else: ?><a href="/blog?pagina=<?= $i ?>"><?= $i ?></a><?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</section>
