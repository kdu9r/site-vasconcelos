<?php /** @var array $posts */ ?>
<div class="p-cabeca">
  <h1>Blog</h1>
  <a class="p-botao p-botao--primario" href="/painel/blog/novo">+ Novo post</a>
</div>
<p class="p-nota">Textos sobre Bertioga, bairros, morar na praia e o mercado de imóveis. Um post bem feito por semana ajuda mais no Google do que vários curtos.</p>
<?php if (!$posts): ?>
  <div class="p-vazio"><p>Nenhum post ainda.</p><a class="p-botao p-botao--primario" href="/painel/blog/novo">Escrever o primeiro</a></div>
<?php else: ?>
<ul class="p-lista">
  <?php foreach ($posts as $p): ?>
    <li class="p-item">
      <a class="p-item__foto" href="/painel/blog/<?= (int)$p['id'] ?>">
        <img src="<?= e(capa_post_url($p, 'p') ?? asset('img/sem-foto.svg')) ?>" alt="" loading="lazy" width="120" height="90">
      </a>
      <div class="p-item__info">
        <a class="p-item__titulo" href="/painel/blog/<?= (int)$p['id'] ?>"><?= e($p['titulo']) ?></a>
        <span><?= e($p['autor_nome'] ?? '—') ?> · <?= $p['publicado_em'] ? e(date('d/m/Y', strtotime($p['publicado_em']))) : 'sem data' ?></span>
        <span class="p-tags">
          <?php if ($p['status'] === 'publicado' && strtotime((string)$p['publicado_em']) > time()): ?>
            <span class="p-tag p-tag--ouro">Agendado</span>
          <?php elseif ($p['status'] === 'publicado'): ?>
            <span class="p-tag p-tag--ativo">Publicado</span>
          <?php else: ?>
            <span class="p-tag p-tag--pausado">Rascunho</span>
          <?php endif; ?>
          <?php if ($p['bairro']): ?><span class="p-tag"><?= e($p['bairro']) ?></span><?php endif; ?>
        </span>
      </div>
      <div class="p-item__acoes">
        <a class="p-botao p-botao--pequeno" href="/painel/blog/<?= (int)$p['id'] ?>">Editar</a>
        <?php if ($p['status'] === 'publicado'): ?><a class="p-botao p-botao--pequeno p-botao--fantasma" href="<?= e(post_url($p)) ?>" target="_blank" rel="noopener">Ver no site</a><?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
