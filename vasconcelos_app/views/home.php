<?php
/** @var array $destaques @var array $locacao @var array $bairros @var array $tipos */
$heroFoto = is_file(rtrim((string)config('public_dir'), '/') . '/assets/img/hero.jpg') ? asset('img/hero.jpg') : asset('img/hero-placeholder.svg');
?>
<section class="hero">
  <img class="hero__fundo" src="<?= e($heroFoto) ?>" alt="" fetchpriority="high" width="1600" height="900">
  <div class="hero__in">
    <p class="hero__local">Bertioga · SP</p>
    <h1 class="hero__titulo">Seu imóvel na praia</h1>
    <?php partial('busca', ['bairros' => $bairros, 'tipos' => $tipos]); ?>
    <form class="busca-codigo" action="/busca" method="get">
      <label for="codigo">Já tem o código?</label>
      <input id="codigo" name="codigo" inputmode="numeric" placeholder="Ex.: 384" maxlength="20" autocomplete="off">
      <button type="submit">Ver imóvel</button>
    </form>
  </div>
</section>

<?php if ($bairros): ?>
<section class="secao secao--estreita" aria-labelledby="bairros-titulo">
  <h2 id="bairros-titulo" class="secao__titulo-pequeno">Explore por bairro</h2>
  <div class="chips">
    <?php foreach (array_slice($bairros, 0, 8) as $b): ?>
      <a class="chip" href="/imoveis?bairro=<?= e(rawurlencode($b['bairro'])) ?>"><?= e($b['bairro']) ?> <span><?= (int)$b['total'] ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="secao" aria-labelledby="venda-titulo">
  <div class="secao__cabeca">
    <div>
      <p class="sobretitulo">À venda</p>
      <h2 id="venda-titulo">Destaques</h2>
    </div>
    <a class="link-forte" href="/comprar">Ver todos à venda</a>
  </div>
  <?php if ($destaques): ?>
    <div class="grade">
      <?php foreach ($destaques as $im) partial('card', ['im' => $im, 'contexto' => 'venda']); ?>
    </div>
  <?php else: ?>
    <p class="vazio">Nenhum imóvel à venda no momento.</p>
  <?php endif; ?>
</section>

<?php if ($locacao): ?>
<section class="secao-faixa" aria-labelledby="locacao-titulo">
  <div class="secao">
    <div class="secao__cabeca">
      <div>
        <p class="sobretitulo">Para alugar</p>
        <h2 id="locacao-titulo">Locação anual</h2>
      </div>
      <a class="link-forte" href="/alugar">Ver todos para alugar</a>
    </div>
    <div class="grade">
      <?php foreach ($locacao as $im) partial('card', ['im' => $im, 'contexto' => 'locacao']); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if (!empty($posts)): ?>
<section class="secao" aria-labelledby="blog-titulo">
  <div class="secao__cabeca">
    <div>
      <p class="sobretitulo">Blog</p>
      <h2 id="blog-titulo">Morar e investir em Bertioga</h2>
    </div>
    <div class="carrossel__acoes">
      <a class="link-forte" href="/blog">Ver todos os textos</a>
      <?php if (count($posts) > 1): ?>
      <button type="button" class="carrossel__seta" data-carrossel-ant aria-label="Post anterior">‹</button>
      <button type="button" class="carrossel__seta" data-carrossel-prox aria-label="Próximo post">›</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="carrossel" data-carrossel aria-roledescription="carrossel">
    <div class="carrossel__trilho" data-carrossel-trilho tabindex="0">
    <?php foreach ($posts as $p): ?>
      <article class="card card--post carrossel__item">
        <a class="card__link" href="<?= e(post_url($p)) ?>">
          <div class="card__foto"><img src="<?= e(capa_post_url($p, 'p') ?? asset('img/hero-placeholder.svg')) ?>" alt="" loading="lazy" width="640" height="427"></div>
          <div class="card__corpo">
            <p class="card__data"><?= e(data_extenso($p['publicado_em'])) ?></p>
            <h3 class="card__titulo card__titulo--post"><?= e($p['titulo']) ?></h3>
          </div>
        </a>
      </article>
    <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>
