<?php /** @var array $condominio @var array $fotos @var array $imoveis */ $c = $condominio; ?>
<section class="sobre-topo">
  <div class="secao sobre-topo__in">
    <nav class="trilha trilha--clara" aria-label="Você está em"><a href="/">Início</a> / <span>Condomínio</span></nav>
    <h1>Condomínio <?= e($c['nome']) ?></h1>
    <?php if ($c['bairro']): ?><p class="lead"><?= e($c['bairro']) ?>, <?= e($c['cidade']) ?> · SP</p><?php endif; ?>
  </div>
</section>

<?php if ($fotos): ?>
<section class="secao">
  <div class="fotos-cond">
    <?php foreach ($fotos as $i => $f): ?>
      <figure class="<?= $i === 0 ? 'fotos-cond__grande' : '' ?>"><img src="<?= e(foto_url($f, $i === 0 ? 'g' : 'p')) ?>" alt="<?= e($f['legenda'] ?: 'Área comum do condomínio') ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"></figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="secao secao--sem-fundo">
  <div class="sobre">
    <div class="sobre__texto">
      <h2>Sobre o condomínio</h2>
      <?php if ($c['descricao']): ?><div class="texto"><?= nl2br(e($c['descricao'])) ?></div><?php endif; ?>
      <?php if ($lazer = lazer_lista($c)): ?>
        <ul class="lista-check"><?php foreach ($lazer as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="secao">
  <h2><?= count($imoveis) ? 'Imóveis disponíveis neste condomínio' : 'Nenhum imóvel disponível agora' ?></h2>
  <?php if ($imoveis): ?>
    <div class="grade"><?php foreach ($imoveis as $im) partial('card', ['im' => $im]); ?></div>
  <?php else: ?>
    <p>Quer ser avisado quando surgir uma unidade aqui?</p>
    <a class="botao botao--whats" href="<?= e(whatsapp_link(null, 'condominio')) ?>" rel="nofollow"><?php partial('icone_whats', ['tam' => 18]); ?> Avise-me pelo WhatsApp</a>
  <?php endif; ?>
</section>
