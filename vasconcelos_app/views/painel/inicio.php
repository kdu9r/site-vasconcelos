<?php
/** @var array $imoveis @var array $stats @var string $busca @var string $status @var array $portal_ids */
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
  <button class="p-botao" type="submit">Filtrar</button>
</form>

<?php if (!$imoveis): ?>
  <div class="p-vazio">
    <p>Nenhum imóvel encontrado.</p>
    <a class="p-botao p-botao--primario" href="/painel/imovel/novo">Cadastrar o primeiro</a>
  </div>
<?php else: ?>
<ul class="p-lista">
  <?php foreach ($imoveis as $im): $preco = preco_principal($im); ?>
    <li class="p-item">
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
<?php endif; ?>
