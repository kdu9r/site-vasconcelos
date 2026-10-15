<?php
/** @var ?array $imovel @var array $v @var array $erros @var array $fotos @var array $pendencias @var array $corretores @var array $bairros @var array $condominios @var string $fotos_token @var array $fotos_temp */
$neg = $v['negocio'] ?? array_values(array_filter([
    ($v['preco_venda'] ?? null) !== null ? 'venda' : null,
    ($v['preco_locacao'] ?? null) !== null ? 'locacao' : null,
    ($v['preco_temporada'] ?? null) !== null ? 'temporada' : null,
]));
if (!$imovel && !isset($v['negocio'])) $neg = ['venda'];
// valores do banco ("850000.00") viram "850.000"; o que o corretor digitou volta exatamente como digitou
$val = fn(string $k) => e(empty($v['_digitado']) && (is_float($v[$k] ?? null) || (is_string($v[$k] ?? null) && preg_match('/^\d+\.\d{2}$/', (string)$v[$k]))) ? num($v[$k]) : ($v[$k] ?? ''));
$erro = fn(string $k) => isset($erros[$k]) ? '<span class="p-erro">' . e($erros[$k]) . '</span>' : '';
$checked = fn(bool $c) => $c ? 'checked' : '';
$acao = $imovel ? '/painel/imovel/' . (int)$imovel['id'] : '/painel/imovel/novo';
// botões 0–5 e "6+" para quartos, banheiros etc.
$chips = function (string $k, string $rotulo) use ($v) {
    $atual = $v[$k] ?? '';
    $mais = $atual === 'mais' || (is_numeric($atual) && (int)$atual > 5);
    $valorMais = $atual === 'mais' ? ($v[$k . '_mais'] ?? '') : ($mais ? (int)$atual : ''); ?>
  <fieldset class="p-chips" data-chips>
    <legend><?= e($rotulo) ?></legend>
    <?php for ($n = 0; $n <= 5; $n++): ?>
      <label><input type="radio" name="<?= $k ?>" value="<?= $n ?>" <?= !$mais && $atual !== '' && $atual !== null && (int)$atual === $n ? 'checked' : '' ?>><span><?= $n ?></span></label>
    <?php endfor; ?>
    <label><input type="radio" name="<?= $k ?>" value="mais" <?= $mais ? 'checked' : '' ?> data-chip-mais><span>6+</span></label>
    <input class="p-chips__mais" name="<?= $k ?>_mais" value="<?= e((string)$valorMais) ?>" inputmode="numeric" maxlength="2" aria-label="<?= e($rotulo) ?>: quantidade" placeholder="Qtd." <?= $mais ? '' : 'hidden' ?>>
  </fieldset>
<?php };
[$itensMarcados, $itensOutros] = separar_itens($v['caracteristicas'] ?? null, itens_do_imovel());
if (isset($v['itens'])) $itensMarcados = (array)$v['itens'];
if (isset($v['caracteristicas_outros'])) $itensOutros = (string)$v['caracteristicas_outros'];
$condAtual = null;
foreach ($condominios as $c) if ((int)$c['id'] === (int)($v['condominio_id'] ?? 0)) $condAtual = $c;
$temCond = isset($v['tem_condominio']) ? !empty($v['tem_condominio']) : (bool)$condAtual;
$condModo = ($v['cond_modo'] ?? '') === 'novo' ? 'novo' : 'existente';
$condItens = isset($v['cond_itens']) ? (array)$v['cond_itens'] : ($condAtual['itens'] ?? []);
?>
<div class="p-cabeca">
  <div>
    <a class="p-voltar" href="/painel">← Imóveis</a>
    <h1><?= $imovel ? 'Cód. ' . e($imovel['codigo']) : 'Novo imóvel' ?></h1>
  </div>
  <?php if ($imovel && $imovel['status'] === 'ativo'): ?>
    <a class="p-botao p-botao--fantasma" href="<?= e(url_imovel($imovel)) ?>" target="_blank" rel="noopener">Ver no site ↗</a>
  <?php endif; ?>
</div>

<?php if ($erros): ?><div class="p-aviso p-aviso--erro" role="alert">Confira os campos marcados em vermelho.</div><?php endif; ?>
<?php if ($imovel && $imovel['status'] === 'pausado' && !$erros): ?>
  <div class="p-aviso p-aviso--aviso">Este imóvel está <strong>fora do ar</strong>. Quando terminar as fotos, mude em <a href="#publicacao">7. Publicação</a> para "No ar".</div>
<?php endif; ?>

<form method="post" action="<?= e($acao) ?>" class="p-form-imovel" novalidate>
  <?= csrf_field() ?>

  <details class="p-cartao p-preencher" data-preencher <?= $imovel ? '' : 'open' ?>>
    <summary><h2><span class="p-passo p-passo--ia" aria-hidden="true">✦</span> Preencher a partir do texto</h2></summary>
    <p class="p-nota">Cole o anúncio pronto ou as anotações do imóvel (do WhatsApp, do bloco de notas). O formulário se preenche sozinho. Nada é salvo antes de você conferir e clicar em Salvar.</p>
    <label class="p-campo">
      <span class="sr">Texto do imóvel</span>
      <textarea rows="6" data-preencher-texto placeholder="APARTAMENTO PARA LOCAÇÃO A 50 M DA PRAIA&#10;&#10;Apartamento mobiliado no Centro…&#10;• 2 dormitórios&#10;• 1 suíte&#10;…&#10;💰 Locação: R$ 4.500"></textarea>
    </label>
    <div class="p-acoes-linha">
      <button type="button" class="p-botao p-botao--primario" data-preencher-botao>Preencher o formulário</button>
      <button type="button" class="p-botao p-botao--fantasma" data-preencher-desfazer hidden>Desfazer</button>
    </div>
    <div class="p-preencher__resultado" data-preencher-resultado aria-live="polite" hidden></div>
  </details>

  <section class="p-cartao">
    <h2><span class="p-passo">1</span> O imóvel</h2>
    <div class="p-grade p-grade--3">
      <label class="p-campo">
        <span>Tipo</span>
        <select name="tipo" required>
          <option value="">Escolha…</option>
          <?php foreach (tipos_imovel() as $k => $t): ?>
            <option value="<?= $k ?>" <?= ($v['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= e($t['rotulo']) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $erro('tipo') ?>
      </label>
      <label class="p-campo">
        <span>Código</span>
        <input name="codigo" value="<?= $val('codigo') ?>" maxlength="20" required inputmode="numeric">
        <?= $erro('codigo') ?>
      </label>
      <label class="p-campo">
        <span>Corretor responsável</span>
        <select name="corretor_id">
          <option value="">—</option>
          <?php foreach ($corretores as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)($v['corretor_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>
    <div class="p-chips-grade">
      <?php $chips('quartos', 'Quartos'); $chips('suites', 'Suítes'); $chips('banheiros', 'Banheiros'); $chips('vagas', 'Vagas'); ?>
    </div>
    <div class="p-grade p-grade--3">
      <label class="p-campo"><span>Área útil (m²)</span><input name="area_util" value="<?= $val('area_util') ?>" inputmode="decimal"></label>
      <label class="p-campo"><span>Área total / terreno (m²)</span><input name="area_total" value="<?= $val('area_total') ?>" inputmode="decimal"></label>
    </div>
    <fieldset class="p-itens">
      <legend>O imóvel tem</legend>
      <?php foreach (itens_do_imovel() as $item): ?>
        <label><input type="checkbox" name="itens[]" value="<?= e($item) ?>" <?= $checked(in_array($item, $itensMarcados, true)) ?>><span><?= e($item) ?></span></label>
      <?php endforeach; ?>
    </fieldset>
    <label class="p-campo">
      <span>Outros itens <small>opcional, um por linha</small></span>
      <textarea name="caracteristicas_outros" rows="2" placeholder="Ex.: Cozinha com ilha"><?= e($itensOutros) ?></textarea>
    </label>
  </section>

  <section class="p-cartao">
    <h2><span class="p-passo">2</span> Valores</h2>
    <div class="p-negocios">
      <?php foreach (['venda' => ['Venda', 'Valor de venda'], 'locacao' => ['Locação', 'Aluguel por mês']] as $k => [$rot, $legenda]): ?>
        <div class="p-negocio">
          <label class="p-check"><input type="checkbox" name="negocio[]" value="<?= $k ?>" <?= $checked(in_array($k, $neg, true)) ?> data-negocio="<?= $k ?>"> <?= $rot ?></label>
          <label class="p-campo p-campo--dinheiro" data-preco="<?= $k ?>">
            <span><?= $legenda ?></span>
            <input name="preco_<?= $k ?>" value="<?= $val('preco_' . $k) ?>" inputmode="decimal" placeholder="0">
            <?= $erro('preco_' . $k) ?>
          </label>
        </div>
      <?php endforeach; ?>
      <?= $erro('negocio') ?>
    </div>
    <label class="p-check" data-preco="locacao"><input type="checkbox" name="locacao_pacote" value="1" <?= $checked(!empty($v['locacao_pacote'])) ?>> O aluguel já inclui condomínio e IPTU (pacote)</label>
    <div class="p-grade p-grade--3">
      <label class="p-campo p-campo--dinheiro"><span>Condomínio (por mês)</span><input name="condominio" value="<?= $val('condominio') ?>" inputmode="decimal"></label>
      <label class="p-campo p-campo--dinheiro"><span>IPTU</span><input name="iptu" value="<?= $val('iptu') ?>" inputmode="decimal"></label>
      <label class="p-campo"><span>IPTU é</span>
        <select name="iptu_periodo">
          <option value="mensal" <?= ($v['iptu_periodo'] ?? 'mensal') === 'mensal' ? 'selected' : '' ?>>por mês</option>
          <option value="anual" <?= ($v['iptu_periodo'] ?? '') === 'anual' ? 'selected' : '' ?>>por ano</option>
        </select>
      </label>
    </div>
  </section>

  <section class="p-cartao">
    <h2><span class="p-passo">3</span> Anúncio</h2>
    <label class="p-campo">
      <span>Título do anúncio <small data-contador-de="titulo">10 a 100 letras</small></span>
      <input name="titulo" value="<?= $val('titulo') ?>" maxlength="100" required placeholder="Ex.: Apartamento pé na areia com varanda gourmet" data-contar="titulo">
      <?= $erro('titulo') ?>
    </label>
    <label class="p-campo">
      <span>Texto do anúncio <small data-contador-de="descricao">mínimo 50 letras</small></span>
      <textarea name="descricao" rows="8" maxlength="3000" data-contar="descricao" placeholder="Conte o que o imóvel tem de melhor: localização, distância da praia, acabamento…"><?= e($v['descricao'] ?? '') ?></textarea>
      <?= $erro('descricao') ?>
    </label>
    <label class="p-campo">
      <span>Vídeo do YouTube <small>opcional — vídeo normal ou Shorts</small></span>
      <input name="video_url" value="<?= $val('video_url') ?>" inputmode="url" placeholder="https://youtube.com/shorts/…">
      <?= $erro('video_url') ?>
    </label>
  </section>

  <section class="p-cartao" data-bloco-condominio>
    <h2><span class="p-passo">4</span> Condomínio</h2>
    <label class="p-interruptor">
      <input type="checkbox" name="tem_condominio" value="1" <?= $checked($temCond) ?> data-tem-condominio>
      <span class="p-interruptor__trilho" aria-hidden="true"></span>
      <span>Fica em condomínio</span>
    </label>
    <div class="p-cond" data-cond-campos <?= $temCond ? '' : 'hidden' ?>>
      <div class="p-segmento" role="radiogroup" aria-label="Condomínio">
        <label><input type="radio" name="cond_modo" value="existente" <?= $checked($condModo === 'existente') ?> data-cond-modo><span>Selecionar</span></label>
        <label><input type="radio" name="cond_modo" value="novo" <?= $checked($condModo === 'novo') ?> data-cond-modo><span>Cadastrar novo</span></label>
      </div>

      <div data-cond-existente>
        <label class="p-campo">
          <span>Condomínio</span>
          <select name="condominio_id" data-condominio>
            <option value="">Escolha…</option>
            <?php foreach ($condominios as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= (int)($v['condominio_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>
                data-dados="<?= e(json_encode(['endereco' => $c['endereco'], 'numero' => $c['numero'], 'bairro' => $c['bairro'], 'cep' => $c['cep'], 'latitude' => $c['latitude'], 'longitude' => $c['longitude'], 'qtd_fotos' => (int)$c['qtd_fotos'], 'itens' => $c['itens'], 'fotos' => $c['miniaturas'], 'editar' => '/painel/condominios/' . (int)$c['id']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"><?= e($c['nome']) ?><?= $c['bairro'] ? ' · ' . e($c['bairro']) : '' ?></option>
            <?php endforeach; ?>
          </select>
          <?= $erro('condominio_id') ?>
          <?php if (!$condominios): ?><span class="p-nota">Nenhum condomínio cadastrado ainda. Use "Cadastrar novo".</span><?php endif; ?>
        </label>
        <div class="p-cond__fotos" data-cond-fotos hidden></div>
        <p class="p-nota" data-info-condominio hidden></p>
      </div>

      <div class="p-grade" data-cond-novo>
        <label class="p-campo">
          <span>Nome do condomínio</span>
          <input name="cond_nome" value="<?= e($v['cond_nome'] ?? '') ?>" maxlength="120">
          <?= $erro('cond_nome') ?>
        </label>
        <label class="p-check"><input type="checkbox" name="cond_nome_publico" value="1" <?= $checked(!empty($v['cond_nome_publico'])) ?>> Mostrar o nome no site</label>
        <p class="p-nota">O endereço vem do bloco Localização, abaixo. As fotos das áreas comuns você adiciona depois, em Condomínios.</p>
      </div>

      <fieldset class="p-itens">
        <legend>O condomínio tem <small>vale para todos os imóveis dele</small></legend>
        <?php foreach (itens_do_condominio() as $item): ?>
          <label><input type="checkbox" name="cond_itens[]" value="<?= e($item) ?>" <?= $checked(in_array($item, $condItens, true)) ?>><span><?= e($item) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
    </div>
  </section>

  <section class="p-cartao">
    <h2><span class="p-passo">5</span> Localização</h2>
    <?php
      // lista de bairros: os de Bertioga + os já usados; "Outro bairro…" abre um campo livre
      $listaBairros = array_values(array_unique(array_merge(bairros_bertioga(), $bairros)));
      sort($listaBairros, SORT_LOCALE_STRING);
      $bairroAtual = (string)($v['bairro'] ?? '');
      $bairroOutro = (string)($v['bairro_outro'] ?? '');
      if ($bairroAtual !== '' && $bairroAtual !== '__outro' && !in_array($bairroAtual, $listaBairros, true)) {
          $bairroOutro = $bairroAtual;
          $bairroAtual = '__outro';
      }
      $temEndereco = !empty($v['endereco']) || !empty($v['numero']) || !empty($v['complemento']) || !empty($v['latitude'])
          || ($v['exibir_endereco'] ?? 'bairro') !== 'bairro' || !empty($v['endereco_completo']);
    ?>
    <div class="p-grade p-grade--3">
      <label class="p-campo">
        <span>Bairro</span>
        <select name="bairro" required data-bairro data-ceps="<?= e(json_encode(ceps_bairros(), JSON_UNESCAPED_UNICODE)) ?>">
          <option value="">Escolha…</option>
          <?php foreach ($listaBairros as $b): ?>
            <option value="<?= e($b) ?>" <?= $bairroAtual === $b ? 'selected' : '' ?>><?= e($b) ?></option>
          <?php endforeach; ?>
          <option value="__outro" <?= $bairroAtual === '__outro' ? 'selected' : '' ?>>Outro bairro…</option>
        </select>
        <?= $erro('bairro') ?>
      </label>
      <label class="p-campo" data-bairro-outro <?= $bairroAtual === '__outro' ? '' : 'hidden' ?>>
        <span>Nome do bairro</span>
        <input name="bairro_outro" value="<?= e($bairroOutro) ?>" maxlength="80" autocomplete="off">
      </label>
    </div>

    <label class="p-interruptor">
      <input type="checkbox" name="endereco_completo" value="1" <?= $checked($temEndereco) ?> data-endereco-completo>
      <span class="p-interruptor__trilho" aria-hidden="true"></span>
      <span>Endereço completo <small>rua, número, CEP, o que aparece no anúncio e posição no mapa</small></span>
    </label>

    <div class="p-cond" data-endereco-campos <?= $temEndereco ? '' : 'hidden' ?>>
      <div class="p-grade p-grade--3">
        <label class="p-campo"><span>Rua</span><input name="endereco" value="<?= $val('endereco') ?>"></label>
        <label class="p-campo"><span>Número</span><input name="numero" value="<?= $val('numero') ?>"></label>
        <label class="p-campo"><span>Complemento</span><input name="complemento" value="<?= $val('complemento') ?>" placeholder="Apto, bloco…"></label>
      </div>
      <div class="p-grade p-grade--3">
        <label class="p-campo"><span>CEP <small>vem do bairro; troque se souber o exato</small></span><input name="cep" value="<?= $val('cep') ?>" inputmode="numeric" maxlength="9" data-cep></label>
        <label class="p-campo"><span>Cidade</span><input name="cidade" value="<?= $val('cidade') ?>"></label>
      </div>
      <fieldset class="p-opcoes">
        <legend>O que mostrar no site e nos portais</legend>
        <?php foreach (['bairro' => 'Só o bairro', 'rua' => 'Rua, sem número', 'completo' => 'Endereço completo'] as $k => $r): ?>
          <label><input type="radio" name="exibir_endereco" value="<?= $k ?>" <?= $checked(($v['exibir_endereco'] ?? 'bairro') === $k) ?>> <?= $r ?></label>
        <?php endforeach; ?>
      </fieldset>
      <details class="p-avancado">
        <summary>Posição no mapa (opcional)</summary>
        <p class="p-nota">No Google Maps, segure o dedo sobre o imóvel e copie os dois números que aparecem.</p>
        <div class="p-grade p-grade--3">
          <label class="p-campo"><span>Latitude</span><input name="latitude" value="<?= e($v['latitude'] ?? '') ?>" inputmode="decimal" placeholder="-23.85"></label>
          <label class="p-campo"><span>Longitude</span><input name="longitude" value="<?= e($v['longitude'] ?? '') ?>" inputmode="decimal" placeholder="-46.14"></label>
        </div>
      </details>
    </div>
    <input type="hidden" name="uf" value="<?= $val('uf') ?: 'SP' ?>">
  </section>

  <section class="p-cartao" id="fotos" aria-labelledby="fotos-titulo">
    <div class="p-cartao__cabeca">
      <h2 id="fotos-titulo"><span class="p-passo">6</span> Fotos <?php if ($imovel): ?><small>(<?= count($fotos) ?>)</small><?php endif; ?></h2>
      <?php if ($imovel): ?><p class="p-nota">Arraste para mudar a ordem. A primeira que aparece é a capa. De preferência, fotos deitadas.<?php if ($condAtual && $condAtual['qtd_fotos']): ?> As fotos do condomínio estão aqui junto: coloque onde quiser, até como capa, ou toque em <strong>Esconder</strong> para não mostrar neste anúncio.<?php endif; ?></p><?php endif; ?>
    </div>
    <?php if ($imovel): ?>
      <div class="p-fotos" data-fotos data-base="/painel/imovel/<?= (int)$imovel['id'] ?>">
        <?php foreach ($fotos as $f): $daCond = !empty($f['condominio_foto_id']); ?>
          <div class="p-foto<?= foto_em_pe($f) ? ' p-foto--em-pe' : '' ?><?= $daCond ? ' p-foto--cond' : '' ?><?= !empty($f['oculta']) ? ' p-foto--oculta' : '' ?>" draggable="true" data-id="<?= (int)$f['id'] ?>">
            <img src="<?= e(foto_url($f, 'p')) ?>" alt="" loading="lazy">
            <span class="p-foto__tags"><span class="p-foto__capa">Capa</span><?php if ($daCond): ?><span class="p-foto__cond">Condomínio</span><span class="p-foto__escondida">Escondida</span><?php endif; ?></span>
            <span class="p-foto__pe">Em pé</span>
            <?php if ($daCond): ?>
              <button type="button" class="p-foto__ocultar" data-ocultar aria-pressed="<?= !empty($f['oculta']) ? 'true' : 'false' ?>"><?= !empty($f['oculta']) ? 'Mostrar' : 'Esconder' ?></button>
            <?php else: ?>
              <button type="button" class="p-foto__excluir" data-excluir aria-label="Excluir foto">✕</button>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="p-aviso p-aviso--aviso p-oculto" data-aviso-capa>A capa está em pé. Para os cards ficarem bonitos, arraste uma foto deitada para o primeiro lugar.</p>
      <label class="p-upload" data-upload>
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-input-fotos>
        <strong>Adicionar fotos</strong>
        <span>Toque para escolher ou arraste as fotos aqui</span>
      </label>
      <div class="p-progresso p-oculto" data-progresso><div></div><span></span></div>
    <?php else: ?>
      <p class="p-nota">Pode enviar as fotos agora: elas entram no imóvel quando você salvar, na ordem abaixo. A primeira é a capa. Depois de salvar, dá para arrastar e mudar a ordem, e as fotos do condomínio aparecem junto.</p>
      <input type="hidden" name="fotos_token" value="<?= e($fotos_token) ?>">
      <div class="p-fotos" data-fotos-novas data-token="<?= e($fotos_token) ?>">
        <?php foreach ($fotos_temp as $n): if (!caminho_foto_temp($fotos_token, $n)) continue; ?>
          <div class="p-foto" data-nome="<?= e($n) ?>">
            <img src="/painel/fotos-temp/ver?t=<?= e($fotos_token) ?>&amp;n=<?= e($n) ?>" alt="">
            <span class="p-foto__tags"><span class="p-foto__capa">Capa</span></span>
            <input type="hidden" name="fotos_temp[]" value="<?= e($n) ?>">
            <button type="button" class="p-foto__excluir" data-excluir-temp aria-label="Tirar foto">✕</button>
          </div>
        <?php endforeach; ?>
      </div>
      <label class="p-upload" data-upload-novas>
        <input type="file" accept="image/jpeg,image/png,image/webp" multiple data-input-fotos-novas>
        <strong>Adicionar fotos</strong>
        <span>Toque para escolher ou arraste as fotos aqui</span>
      </label>
      <div class="p-progresso p-oculto" data-progresso-novas><div></div><span></span></div>
    <?php endif; ?>
  </section>

  <section class="p-cartao" id="publicacao">
    <h2><span class="p-passo">7</span> Publicação</h2>
    <label class="p-campo p-campo--curto">
      <span>Situação no site</span>
      <select name="status">
        <?php foreach (['ativo' => 'No ar', 'pausado' => 'Fora do ar', 'vendido' => 'Vendido', 'alugado' => 'Alugado'] as $k => $r): ?>
          <option value="<?= $k ?>" <?= ($v['status'] ?? 'ativo') === $k ? 'selected' : '' ?>><?= $r ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="p-interruptor">
      <input type="checkbox" name="destaque_site" value="1" <?= $checked(!empty($v['destaque_site'])) ?>>
      <span class="p-interruptor__trilho" aria-hidden="true"></span>
      <span>Destaque na página inicial</span>
    </label>
    <label class="p-interruptor">
      <input type="checkbox" name="publicar_portais" value="1" <?= $checked(!empty($v['publicar_portais'])) ?> data-toggle-portal>
      <span class="p-interruptor__trilho" aria-hidden="true"></span>
      <span>Anunciar no CanalPro <small>ZAP Imóveis e VivaReal</small></span>
    </label>
    <div data-portal>
      <fieldset class="p-opcoes">
        <legend>Tipo de anúncio no CanalPro</legend>
        <?php foreach (['padrao' => 'Padrão', 'destaque' => 'Destaque', 'super_destaque' => 'Super destaque'] as $k => $r): ?>
          <label><input type="radio" name="destaque_portal" value="<?= $k ?>" <?= $checked(($v['destaque_portal'] ?? 'padrao') === $k) ?>> <?= $r ?></label>
        <?php endforeach; ?>
        <p class="p-nota">Usa a cota de destaques do plano no CanalPro.</p>
      </fieldset>
      <?php if ($imovel && $pendencias): ?>
        <div class="p-aviso p-aviso--aviso">
          <strong>Ainda não vai para o CanalPro:</strong>
          <ul><?php foreach ($pendencias as $p): ?><li><?= e($p) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <div class="p-salvar">
    <button class="p-botao p-botao--primario p-botao--grande" type="submit"><?= $imovel ? 'Salvar alterações' : 'Salvar e adicionar fotos' ?></button>
  </div>
</form>

<?php if ($imovel): ?>
<details class="p-cartao p-perigo">
  <summary>Excluir este imóvel</summary>
  <p>Isso apaga o imóvel e todas as fotos. Para só esconder do site, use a situação <strong>Pausado</strong>.</p>
  <form method="post" action="/painel/imovel/<?= (int)$imovel['id'] ?>/excluir" class="p-grade p-grade--3">
    <?= csrf_field() ?>
    <label class="p-campo"><span>Digite o código <?= e($imovel['codigo']) ?> para confirmar</span><input name="confirmar" autocomplete="off" required></label>
    <button class="p-botao p-botao--perigo" type="submit">Excluir definitivamente</button>
  </form>
</details>
<?php endif; ?>
