<?php
/** @var array $im */
$contexto = $contexto ?? null;
$preco = preco_principal($im, $contexto);
$capa = $im['capa'] ?? null;
$area = $im['area_util'] ?? $im['area_total'] ?? null;
$ico = 'width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
?>
<article class="card">
  <a class="card__link" href="<?= e(url_imovel($im)) ?>">
    <div class="card__foto">
      <img src="<?= e(foto_url($capa, 'p')) ?>" alt="" loading="lazy" decoding="async" width="640" height="480">
      <span class="selo selo--claro"><?= e(tipo_rotulo($im['tipo'])) ?></span>
      <span class="selo selo--escuro">Cód. <?= e($im['codigo']) ?></span>
    </div>
    <div class="card__corpo">
      <h3 class="card__titulo"><?= e($im['titulo']) ?></h3>
      <div class="card__linha">
        <p class="card__local"><?= e($im['bairro']) ?>, <?= e($im['cidade']) ?></p>
        <p class="card__preco"><?= e($preco['valor']) ?><?php if ($preco['sufixo']): ?> <small><?= e($preco['sufixo']) ?></small><?php endif; ?></p>
      </div>
      <?php if ($preco['nota']): ?><p class="card__nota"><?= e($preco['nota']) ?></p><?php endif; ?>
      <ul class="card__itens">
        <?php if (!empty($im['quartos'])): ?>
          <li><svg <?= $ico ?>><path d="M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5"/><circle cx="7" cy="11" r="1.6"/></svg><?= (int)$im['quartos'] ?> <?= (int)$im['quartos'] === 1 ? 'dormitório' : 'dormitórios' ?></li>
        <?php endif; ?>
        <?php if (!empty($im['banheiros'])): ?>
          <li><svg <?= $ico ?>><path d="M4 12h16v2a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5v-2ZM6 12V6a2 2 0 0 1 2-2h1M7 19l-1 2M17 19l1 2"/></svg><?= (int)$im['banheiros'] ?> <?= (int)$im['banheiros'] === 1 ? 'banheiro' : 'banheiros' ?></li>
        <?php endif; ?>
        <?php if ($area !== null): ?>
          <li><svg <?= $ico ?>><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg><?= e(num($area)) ?> m²</li>
        <?php endif; ?>
      </ul>
    </div>
  </a>
</article>
