<?php
/** Formulário de busca. @var array $bairros @var array $tipos @var array $f */
$f = $f ?? [];
$trans = ($f['transacao'] ?? '') ?: 'venda';
?>
<form class="busca" action="/imoveis" method="get" role="search">
  <fieldset class="busca__trans">
    <legend class="sr">Tipo de negócio</legend>
    <label><input type="radio" name="transacao" value="venda" <?= $trans === 'venda' ? 'checked' : '' ?>><span>Comprar</span></label>
    <label><input type="radio" name="transacao" value="locacao" <?= $trans === 'locacao' ? 'checked' : '' ?>><span>Alugar</span></label>
  </fieldset>
  <label class="busca__campo busca__campo--largo">
    <span>Onde</span>
    <select name="bairro">
      <option value="">Todos os bairros</option>
      <?php foreach ($bairros as $b): ?>
        <option value="<?= e($b['bairro']) ?>" <?= ($f['bairro'] ?? '') === $b['bairro'] ? 'selected' : '' ?>><?= e($b['bairro']) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <details class="busca__mais" data-busca-mais>
    <summary>Mais filtros</summary>
    <div class="busca__mais-in">
      <label class="busca__campo">
        <span>Tipo</span>
        <select name="tipo">
          <option value="">Qualquer</option>
          <?php foreach ($tipos as $chave => $t): ?>
            <option value="<?= e($chave) ?>" <?= ($f['tipo'] ?? '') === $chave ? 'selected' : '' ?>><?= e($t['rotulo']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="busca__campo">
        <span>Quartos</span>
        <select name="quartos">
          <option value="">Qualquer</option>
          <?php foreach ([1, 2, 3, 4] as $n): ?>
            <option value="<?= $n ?>" <?= (int)($f['quartos'] ?? 0) === $n ? 'selected' : '' ?>><?= $n ?>+</option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
  </details>
  <button class="botao botao--escuro busca__enviar" type="submit">Buscar</button>
</form>
