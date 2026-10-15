<?php
/** @var array $im */
$contexto = $contexto ?? null;
$preco = preco_principal($im, $contexto);
$capa = $im['capa'] ?? null;
?>
<article class="card">
  <a class="card__link" href="<?= e(url_imovel($im)) ?>">
    <div class="card__foto">
      <img src="<?= e(foto_url($capa, 'p')) ?>" alt="" loading="lazy" decoding="async" width="640" height="480">
      <span class="selo selo--claro"><?= e(tipo_rotulo($im['tipo'])) ?></span>
      <span class="selo selo--escuro">Cód. <?= e($im['codigo']) ?></span>
    </div>
    <div class="card__corpo">
      <p class="card__preco"><?= e($preco['valor']) ?><?php if ($preco['sufixo']): ?> <small><?= e($preco['sufixo']) ?></small><?php endif; ?></p>
      <?php if ($preco['nota']): ?><p class="card__nota"><?= e($preco['nota']) ?></p><?php endif; ?>
      <h3 class="card__titulo"><?= e($im['titulo']) ?></h3>
      <p class="card__local"><?= e($im['bairro']) ?>, <?= e($im['cidade']) ?></p>
      <?php if ($specs = specs_curtas($im)): ?><p class="card__specs"><?= e($specs) ?></p><?php endif; ?>
    </div>
  </a>
</article>
