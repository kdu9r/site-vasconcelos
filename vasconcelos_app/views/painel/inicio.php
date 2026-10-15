<?php
/** @var array $imoveis @var array $stats @var string $busca @var string $status @var array $portal_ids @var int $total @var int $pagina @var int $paginas @var int $por_pagina @var string $tipo @var string $transacao @var array $tipos */
$rotStatus = ['ativo' => 'No ar', 'pausado' => 'Pausado', 'vendido' => 'Vendido', 'alugado' => 'Alugado'];
?>
<div class="p-cabeca">
  <h1>Imóveis</h1>
  <a class="p-botao p-botao--primario" href="/painel/imovel/novo">+ Novo imóvel</a>
</div>

<div class="p-stats">
  <div><strong><?= $stats['ativos'] ?></strong><span>no ar no site</span></div>
  <div><strong><?= $stats['portal'] ?></strong><span>indo pro CanalPro</span></div>
  <div><strong><?= $stats['cliques7'] ?></strong><span>cliques no WhatsApp (7 dias)</span></div>
  <div><strong><?= $stats['cliques30'] ?></strong><span>cliques no WhatsApp (30 dias)</span></div>
</div>

<form class="p-filtro" method="get" action="/painel">
  <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por código, título ou bairro" aria-label="Buscar">
  <select name="status" aria-label="Situação">
    <option value="">Todas as situações</option>
    <?php foreach ($rotStatus as $k => $r): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $r ?></option><?php endforeach; ?>
  </select>
  <select name="transacao" aria-label="Venda ou locação">
    <option value="">Venda e locação</option>
    <option value="venda" <?= $transacao === 'venda' ? 'selected' : '' ?>>Venda</option>
    <option value="locacao" <?= $transacao === 'locacao' ? 'selected' : '' ?>>Locação</option>
  </select>
  <select name="tipo" aria-label="Tipo de imóvel">
    <option value="">Todos os tipos</option>
    <?php foreach ($tipos as $k => $t): ?><option value="<?= e($k) ?>" <?= $tipo === $k ? 'selected' : '' ?>><?= e($t['rotulo']) ?></option><?php endforeach; ?>
  </select>
  <select name="por" aria-label="Imóveis por página" class="p-filtro__por">
    <?php foreach ([30, 50, 100] as $n): ?><option value="<?= $n ?>" <?= $por_pagina === $n ? 'selected' : '' ?>><?= $n ?> por página</option><?php endforeach; ?>
  </select>
  <button class="p-botao" type="submit">Filtrar</button>
  <?php if ($busca !== '' || $status || $tipo || $transacao): ?><a class="p-botao p-botao--fantasma" href="/painel<?= $por_pagina !== 30 ? '?por=' . $por_pagina : '' ?>">Limpar</a><?php endif; ?>
</form>

<?php if (!$imoveis): ?>
  <div class="p-vazio">
    <p>Nenhum imóvel encontrado.</p>
    <a class="p-botao p-botao--primario" href="/painel/imovel/novo">Cadastrar o primeiro</a>
  </div>
<?php else: ?>
<form method="post" action="/painel/imoveis/status" class="p-massa" data-massa>
  <?= csrf_field() ?>
  <input type="hidden" name="q" value="<?= e($busca) ?>">
  <input type="hidden" name="status" value="<?= e($status) ?>">
  <input type="hidden" name="tipo" value="<?= e($tipo) ?>">
  <input type="hidden" name="transacao" value="<?= e($transacao) ?>">
  <input type="hidden" name="por" value="<?= $por_pagina ?>">
  <input type="hidden" name="pagina" value="<?= $pagina ?>">
  <div class="p-massa__barra">
    <label class="p-massa__todos"><input type="checkbox" data-marcar-todos> Marcar todos da página</label>
    <span class="p-massa__conta" data-massa-conta>Nenhum marcado</span>
    <select name="novo_status" aria-label="Nova situação" required>
      <option value="">Mudar para…</option>
      <?php foreach ($rotStatus as $k => $r): ?><option value="<?= $k ?>"><?= $r ?></option><?php endforeach; ?>
    </select>
    <button class="p-botao p-botao--primario" type="submit" name="todos" value="0" data-massa-aplicar disabled>Aplicar aos marcados</button>
    <?php if ($total > count($imoveis)): ?>
    <button class="p-botao p-botao--fantasma" type="submit" name="todos" value="1" data-massa-tudo="<?= $total ?>">Aplicar a todos os <?= $total ?> do filtro</button>
    <?php endif; ?>
  </div>
<p class="p-massa__info"><?= $total ?> imóve<?= $total === 1 ? 'l' : 'is' ?> · ordem por código, do maior para o menor</p>
<ul class="p-lista">
  <?php foreach ($imoveis as $im): $preco = preco_principal($im); ?>
    <li class="p-item">
      <input type="checkbox" class="p-item__marca" name="ids[]" value="<?= (int)$im['id'] ?>" aria-label="Marcar Cód. <?= e($im['codigo']) ?>" data-marca>
      <a class="p-item__foto" href="/painel/imovel/<?= (int)$im['id'] ?>">
        <img src="<?= e(foto_url($im['capa'], 'p')) ?>" alt="" loading="lazy" width="120" height="90">
      </a>
      <div class="p-item__info">
        <a class="p-item__titulo" href="/painel/imovel/<?= (int)$im['id'] ?>"><strong>Cód. <?= e($im['codigo']) ?></strong> · <?= e($im['titulo']) ?></a>
        <span><?= e($preco['valor'] . ($preco['sufixo'] ? ' ' . $preco['sufixo'] : '')) ?> · <?= e($im['bairro']) ?> · <?= (int)$im['qtd_fotos'] ?> fotos</span>
        <span class="p-tags">
          <span class="p-tag p-tag--<?= e($im['status']) ?>"><?= e($rotStatus[$im['status']]) ?></span>
          <?php if ($im['destaque_site']): ?><span class="p-tag p-tag--ouro">Destaque no site</span><?php endif; ?>
          <?php if (isset($portal_ids[(int)$im['id']])): ?>
            <span class="p-tag p-tag--portal">CanalPro<?= $im['destaque_portal'] !== 'padrao' ? ' · ' . ($im['destaque_portal'] === 'super_destaque' ? 'super destaque' : 'destaque') : '' ?></span>
          <?php elseif ($im['status'] === 'ativo' && $im['publicar_portais']): ?>
            <span class="p-tag p-tag--alerta">Fora do CanalPro: falta informação</span>
          <?php endif; ?>
          <?php if ((int)$im['cliques30'] > 0): ?><span class="p-tag"><?= (int)$im['cliques30'] ?> cliques/30d</span><?php endif; ?>
        </span>
      </div>
      <div class="p-item__acoes">
        <a class="p-botao p-botao--pequeno" href="/painel/imovel/<?= (int)$im['id'] ?>">Editar</a>
        <?php if ($im['status'] === 'ativo'): ?>
          <a class="p-botao p-botao--pequeno p-botao--fantasma" href="<?= e(url_imovel($im)) ?>" target="_blank" rel="noopener">Ver no site</a>
        <?php endif; ?>
      </div>
    </li>
  <?php endforeach; ?>
</ul>
</form>
<?php if ($paginas > 1):
  $link = fn(int $n) => '/painel?' . http_build_query(array_filter(['q' => $busca, 'status' => $status, 'tipo' => $tipo, 'transacao' => $transacao, 'por' => $por_pagina !== 30 ? $por_pagina : null, 'pagina' => $n > 1 ? $n : null]));
?>
<nav class="p-paginas" aria-label="Páginas">
  <?php if ($pagina > 1): ?><a class="p-botao p-botao--pequeno" href="<?= e($link($pagina - 1)) ?>">← Anterior</a><?php endif; ?>
  <span>Página <?= $pagina ?> de <?= $paginas ?></span>
  <?php if ($pagina < $paginas): ?><a class="p-botao p-botao--pequeno" href="<?= e($link($pagina + 1)) ?>">Próxima →</a><?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
