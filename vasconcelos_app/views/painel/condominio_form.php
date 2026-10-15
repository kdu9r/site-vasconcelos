<?php
/** @var ?array $c @var array $v @var array $erros @var array $fotos @var array $imoveis @var array $bairros */
$erro = fn(string $k) => isset($erros[$k]) ? '<span class="p-erro">' . e($erros[$k]) . '</span>' : '';
$val = fn(string $k) => e($v[$k] ?? '');
?>
<div class="p-cabeca">
  <div>
    <a class="p-voltar" href="/painel/condominios">← Condomínios</a>
    <h1><?= $c ? e($c['nome']) : 'Novo condomínio' ?></h1>
  </div>
  <?php if ($c && $c['nome_publico']): ?><a class="p-botao p-botao--fantasma" href="<?= e(condominio_url($c)) ?>" target="_blank" rel="noopener">Ver no site ↗</a><?php endif; ?>
</div>
<?php if ($erros): ?><div class="p-aviso p-aviso--erro" role="alert">Confira os campos marcados em vermelho.</div><?php endif; ?>

<?php if ($c): ?>
<section class="p-cartao" id="fotos">
  <div class="p-cartao__cabeca">
    <h2>Fotos das áreas comuns <small>(<?= count($fotos) ?>)</small></h2>
    <p class="p-nota">Piscina, salão, fachada, academia… Aparecem na galeria de todos os imóveis deste condomínio. Em cada imóvel você escolhe a posição de cada uma (até como capa) e pode esconder as que não quiser. Evite fotos com o nome do prédio se ele for sigiloso.</p>
  </div>
  <div class="p-fotos" data-fotos data-base="/painel/condominios/<?= (int)$c['id'] ?>">
    <?php foreach ($fotos as $f): ?>
      <div class="p-foto<?= foto_em_pe($f) ? ' p-foto--em-pe' : '' ?>" draggable="true" data-id="<?= (int)$f['id'] ?>">
        <img src="<?= e(foto_url($f, 'p')) ?>" alt="" loading="lazy">
        <span class="p-foto__tags"><span class="p-foto__capa">1ª</span></span>
        <span class="p-foto__pe">Em pé</span>
        <button type="button" class="p-foto__excluir" data-excluir aria-label="Excluir foto">✕</button>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="p-oculto" data-aviso-capa></p>
  <label class="p-upload" data-upload>
    <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-input-fotos>
    <strong>Adicionar fotos</strong>
    <span>Toque para escolher ou arraste as fotos aqui</span>
  </label>
  <div class="p-progresso p-oculto" data-progresso><div></div><span></span></div>
</section>
<?php endif; ?>

<form method="post" action="<?= $c ? '/painel/condominios/' . (int)$c['id'] : '/painel/condominios/novo' ?>" class="p-form-imovel" novalidate>
  <?= csrf_field() ?>
  <section class="p-cartao">
    <h2>Dados</h2>
    <label class="p-campo"><span>Nome do condomínio</span><input name="nome" value="<?= $val('nome') ?>" required maxlength="120"><?= $erro('nome') ?></label>
    <label class="p-check">
      <input type="checkbox" name="nome_publico" value="1" <?= !empty($v['nome_publico']) ? 'checked' : '' ?>>
      Mostrar o nome no site (cria uma página pública do condomínio)
    </label>
    <p class="p-nota">Desmarcado: visitantes veem só as fotos e o lazer. Nome e endereço ficam visíveis apenas para quem está logado no painel.</p>
    <?php
      [$lzMarcados, $lzOutros] = separar_itens($v['lazer'] ?? null, itens_do_condominio());
      if (isset($v['lazer_itens'])) $lzMarcados = (array)$v['lazer_itens'];
      if (isset($v['lazer_outros'])) $lzOutros = (string)$v['lazer_outros'];
    ?>
    <fieldset class="p-itens">
      <legend>O condomínio tem</legend>
      <?php foreach (itens_do_condominio() as $item): ?>
        <label><input type="checkbox" name="lazer_itens[]" value="<?= e($item) ?>" <?= in_array($item, $lzMarcados, true) ? 'checked' : '' ?>><span><?= e($item) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <label class="p-campo"><span>Outros itens <small>opcional, um por linha</small></span>
      <textarea name="lazer_outros" rows="2" placeholder="Ex.: Piscina com raia de 25 m"><?= e($lzOutros) ?></textarea>
    </label>
    <label class="p-campo"><span>Descrição pública <small>opcional; não coloque nome nem endereço se forem sigilosos</small></span>
      <textarea name="descricao" rows="4"><?= e($v['descricao'] ?? '') ?></textarea>
    </label>
  </section>
  <section class="p-cartao">
    <h2>Endereço <small>só aparece no painel e no CanalPro, conforme a opção de cada imóvel</small></h2>
    <div class="p-grade p-grade--3">
      <label class="p-campo"><span>Rua</span><input name="endereco" value="<?= $val('endereco') ?>"></label>
      <label class="p-campo"><span>Número</span><input name="numero" value="<?= $val('numero') ?>"></label>
      <label class="p-campo"><span>Bairro</span><input name="bairro" value="<?= $val('bairro') ?>" list="lista-bairros-c"><datalist id="lista-bairros-c"><?php foreach ($bairros as $b): ?><option value="<?= e($b) ?>"><?php endforeach; ?></datalist></label>
      <label class="p-campo"><span>Cidade</span><input name="cidade" value="<?= $val('cidade') ?>"></label>
      <label class="p-campo"><span>CEP</span><input name="cep" value="<?= $val('cep') ?>" inputmode="numeric" maxlength="9"></label>
    </div>
    <details class="p-avancado">
      <summary>Posição no mapa (opcional)</summary>
      <div class="p-grade p-grade--3">
        <label class="p-campo"><span>Latitude</span><input name="latitude" value="<?= $val('latitude') ?>" inputmode="decimal"></label>
        <label class="p-campo"><span>Longitude</span><input name="longitude" value="<?= $val('longitude') ?>" inputmode="decimal"></label>
      </div>
    </details>
  </section>
  <div class="p-salvar"><button class="p-botao p-botao--primario p-botao--grande" type="submit"><?= $c ? 'Salvar' : 'Criar e adicionar fotos' ?></button></div>
</form>

<?php if ($c): ?>
<section class="p-cartao">
  <h2>Imóveis neste condomínio <small>(<?= count($imoveis) ?>)</small></h2>
  <?php if ($imoveis): ?>
    <ul class="p-passos"><?php foreach ($imoveis as $im): ?><li><a href="/painel/imovel/<?= (int)$im['id'] ?>">Cód. <?= e($im['codigo']) ?> · <?= e($im['titulo']) ?></a></li><?php endforeach; ?></ul>
  <?php else: ?><p class="p-nota">Nenhum ainda. No cadastro do imóvel, escolha este condomínio no campo "Condomínio".</p><?php endif; ?>
</section>
<details class="p-cartao p-perigo">
  <summary>Excluir este condomínio</summary>
  <form method="post" action="/painel/condominios/<?= (int)$c['id'] ?>/excluir" class="p-grade p-grade--3">
    <?= csrf_field() ?>
    <p>Apaga o condomínio e as fotos dele. Os imóveis continuam, só sem o vínculo.</p>
    <button class="p-botao p-botao--perigo" type="submit">Excluir condomínio</button>
  </form>
</details>
<?php endif; ?>
