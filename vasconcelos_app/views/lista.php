<?php
/** @var string $titulo @var array $filtros @var array $resultado @var array $bairros @var array $tipos @var string $path */
$f = $filtros;
$contexto = $f['transacao'] ?: null;
$qsBase = array_filter([
    'transacao' => $path === '/imoveis' ? $f['transacao'] : null,
    'bairro' => $f['bairro'], 'tipo' => $f['tipo'], 'quartos' => $f['quartos'],
    'preco_min' => $f['preco_min'], 'preco_max' => $f['preco_max'],
    'ordem' => $f['ordem'] !== 'recentes' ? $f['ordem'] : null,
], fn($v) => $v !== null && $v !== '');
?>
<div class="lista-topo">
  <div class="secao secao--sem-fundo">
    <nav class="trilha" aria-label="Você está em"><a href="/">Início</a> / <span><?= e($titulo) ?></span></nav>
    <h1><?= e($titulo) ?></h1>
    <p class="lista-total"><?= $resultado['total'] ?> <?= $resultado['total'] === 1 ? 'imóvel encontrado' : 'imóveis encontrados' ?></p>

    <form class="filtros" action="/imoveis" method="get">
      <label class="filtro">
        <span>Negócio</span>
        <select name="transacao">
          <option value="">Todos</option>
          <option value="venda" <?= $f['transacao'] === 'venda' ? 'selected' : '' ?>>Comprar</option>
          <option value="locacao" <?= $f['transacao'] === 'locacao' ? 'selected' : '' ?>>Alugar</option>
        </select>
      </label>
      <label class="filtro">
        <span>Bairro</span>
        <select name="bairro">
          <option value="">Todos</option>
          <?php foreach ($bairros as $b): ?>
            <option value="<?= e($b['bairro']) ?>" <?= $f['bairro'] === $b['bairro'] ? 'selected' : '' ?>><?= e($b['bairro']) ?> (<?= (int)$b['total'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="filtro">
        <span>Tipo</span>
        <select name="tipo">
          <option value="">Qualquer</option>
          <?php foreach ($tipos as $chave => $t): ?>
            <option value="<?= e($chave) ?>" <?= $f['tipo'] === $chave ? 'selected' : '' ?>><?= e($t['rotulo']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="filtro">
        <span>Quartos</span>
        <select name="quartos">
          <option value="">Qualquer</option>
          <?php foreach ([1, 2, 3, 4] as $n): ?>
            <option value="<?= $n ?>" <?= (int)$f['quartos'] === $n ? 'selected' : '' ?>><?= $n ?>+</option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="filtro">
        <span>Valor até</span>
        <input name="preco_max" inputmode="numeric" placeholder="Sem limite" value="<?= e($f['preco_max'] !== null ? number_format((float)$f['preco_max'], 0, ',', '.') : '') ?>">
      </label>
      <label class="filtro">
        <span>Ordenar</span>
        <select name="ordem">
          <option value="recentes">Mais recentes</option>
          <option value="menor_preco" <?= $f['ordem'] === 'menor_preco' ? 'selected' : '' ?>>Menor valor</option>
          <option value="maior_preco" <?= $f['ordem'] === 'maior_preco' ? 'selected' : '' ?>>Maior valor</option>
        </select>
      </label>
      <button class="botao botao--escuro" type="submit">Filtrar</button>
    </form>
  </div>
</div>

<section class="secao">
  <?php if ($resultado['itens']): ?>
    <div class="grade">
      <?php foreach ($resultado['itens'] as $im) partial('card', ['im' => $im, 'contexto' => $contexto]); ?>
    </div>
    <?php if ($resultado['paginas'] > 1): ?>
      <nav class="paginacao" aria-label="Páginas">
        <?php for ($p = 1; $p <= $resultado['paginas']; $p++): ?>
          <?php if ($p === $resultado['pagina']): ?>
            <span aria-current="page"><?= $p ?></span>
          <?php else: ?>
            <a href="<?= e($path . '?' . http_build_query($qsBase + ['pagina' => $p])) ?>"><?= $p ?></a>
          <?php endif; ?>
        <?php endfor; ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="vazio">
      <p><strong>Nenhum imóvel com esses filtros.</strong></p>
      <p>Tente outro bairro ou faixa de valor, ou conte pra gente o que você procura:</p>
      <a class="botao botao--whats" href="<?= e(whatsapp_link(null, 'busca_vazia')) ?>" rel="nofollow"><?php partial('icone_whats', ['tam' => 18]); ?> Encomendar um imóvel</a>
    </div>
  <?php endif; ?>
</section>
