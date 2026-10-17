<?php
/** @var array $imovel @var array $fotos @var array $parecidos @var bool $indisponivel */
$im = $imovel;
$yt = youtube_id($im['video_url'] ?? null);
$caracteristicas = caracteristicas_lista($im);
$terreno = eh_terreno($im);

$specs = [];
if ($im['quartos']) $specs[] = ['quartos', (string)$im['quartos'], $im['quartos'] > 1 ? 'Dormitórios' : 'Dormitório'];
if ($im['suites']) $specs[] = ['suites', (string)$im['suites'], $im['suites'] > 1 ? 'Suítes' : 'Suíte'];
if ($im['banheiros']) $specs[] = ['banheiros', (string)$im['banheiros'], $im['banheiros'] > 1 ? 'Banheiros' : 'Banheiro'];
if ($im['vagas']) $specs[] = ['vagas', (string)$im['vagas'], $im['vagas'] > 1 ? 'Vagas de garagem' : 'Vaga de garagem'];
if ($im['area_util'] !== null && (float)$im['area_util'] > 0) $specs[] = ['area_util', num($im['area_util']) . ' m²', $terreno ? 'Área útil' : 'Área construída'];
if ($im['area_total'] !== null && (float)$im['area_total'] > 0) $specs[] = ['area_total', num($im['area_total']) . ' m²', $terreno ? 'Área do terreno' : 'Área total'];

// Ícones de traço (24x24), cor herdada do texto
$iconesSpec = [
    'quartos' => '<path d="M3 18v-6.5A1.5 1.5 0 0 1 4.5 10h15a1.5 1.5 0 0 1 1.5 1.5V18"/><path d="M3 15h18"/><path d="M3 18v2M21 18v2"/><path d="M5 10V6.5A1.5 1.5 0 0 1 6.5 5h11A1.5 1.5 0 0 1 19 6.5V10"/><path d="M8 10V8.5h3V10M13 10V8.5h3V10"/>',
    'suites' => '<path d="M4 20V7a3 3 0 0 1 6 0"/><path d="M7.5 9.5h5"/><path d="M14 12.5v.01M17 12.5v.01M20 12.5v.01M15.5 15.5v.01M18.5 15.5v.01M17 18.5v.01"/><path d="M3 20h8"/>',
    'banheiros' => '<path d="M5 4h7v6H5z"/><path d="M4 10h16v1a6 6 0 0 1-6 6h-4a6 6 0 0 1-6-6z"/><path d="M8 17l-1 3h10l-1-3"/>',
    'vagas' => '<path d="M5 17h14v-4.5l-1.8-4.4A1.8 1.8 0 0 0 15.5 7h-7a1.8 1.8 0 0 0-1.7 1.1L5 12.5z"/><path d="M5 12.5h14"/><circle cx="8" cy="14.8" r=".6"/><circle cx="16" cy="14.8" r=".6"/><path d="M6.5 17v2M17.5 17v2"/>',
    'area_util' => '<path d="M3 21V10l9-7 9 7v11z"/><path d="M8 21v-6h8v6"/><path d="M8 12h8"/>',
    'area_total' => '<rect x="4" y="4" width="16" height="16" rx="1"/><path d="M4 9h2M4 14h2M9 4v2M14 4v2M18 20v-2M20 15h-2"/><circle cx="4" cy="4" r="1.5"/><circle cx="20" cy="20" r="1.5"/>',
];

// Botão de compartilhar: ao lado de "Sobre o imóvel"; sem descrição, vai para o topo
ob_start(); ?>
<button type="button" class="botao-compartilhar" aria-label="Compartilhar imóvel" title="Compartilhar" data-compartilhar data-titulo="<?= e($im['titulo']) ?>" data-url="<?= e(url(url_imovel($im))) ?>">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
          <span class="botao-compartilhar__aviso" data-compartilhar-rotulo role="status"></span>
        </button>
<?php $botaoCompartilhar = ob_get_clean();
$temDescricao = trim((string)$im['descricao']) !== '';

$valores = [];
if ($im['preco_venda'] !== null) $valores[] = ['Venda', money($im['preco_venda']), '', (float)$im['preco_venda']];
if ($im['preco_locacao'] !== null) $valores[] = ['Locação', money($im['preco_locacao']), '/mês', (float)$im['preco_locacao']];
if ($im['preco_temporada'] !== null) $valores[] = ['Temporada', money($im['preco_temporada']), '/diária', (float)$im['preco_temporada']];

$extras = [];
if ($im['locacao_pacote'] && $im['preco_locacao'] !== null) $extras[] = 'Locação em pacote com condomínio e IPTU';
if ($im['condominio'] !== null && !$im['locacao_pacote']) $extras[] = 'Condomínio ' . money($im['condominio']) . '/mês';
if ($im['iptu'] !== null && !$im['locacao_pacote']) $extras[] = 'IPTU ' . money($im['iptu']) . ($im['iptu_periodo'] === 'anual' ? '/ano' : '/mês');

$mapa = null;
if ($pos = coordenadas_publicas($im)) {
    [$lat, $lng, $d, $marcador] = $pos;
    $mapa = 'https://www.openstreetmap.org/export/embed.html?' . http_build_query([
        'bbox' => implode(',', [$lng - $d, $lat - $d * 0.6, $lng + $d, $lat + $d * 0.6]),
        'layer' => 'mapnik',
    ]) . ($marcador ? '&marker=' . $lat . ',' . $lng : '');
}

$urlCompleta = url(url_imovel($im));
$json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'RealEstateListing',
    'name' => $im['titulo'],
    'url' => $urlCompleta,
    'description' => mb_substr((string)$im['descricao'], 0, 500),
    'image' => array_map(fn($f) => str_starts_with($u = foto_url($f, 'g'), '/') ? url($u) : $u, array_slice($fotos, 0, 6)),
    'datePosted' => date('Y-m-d', strtotime((string)$im['criado_em'])),
    'offers' => array_map(fn($v) => ['@type' => 'Offer', 'price' => $v[3], 'priceCurrency' => 'BRL', 'businessFunction' => $v[0] === 'Venda' ? 'http://purl.org/goodrelations/v1#Sell' : 'http://purl.org/goodrelations/v1#LeaseOut'], $valores),
    'address' => ['@type' => 'PostalAddress', 'addressLocality' => $im['cidade'], 'addressRegion' => $im['uf'], 'addressCountry' => 'BR'],
];
$mensagem = whatsapp_mensagem($im);
?>
<div class="secao secao--sem-fundo imovel-topo">
  <nav class="trilha" aria-label="Você está em">
    <a href="/">Início</a> /
    <?php if ($im['preco_venda'] !== null): ?><a href="/comprar">Comprar</a><?php else: ?><a href="/alugar">Alugar</a><?php endif; ?> /
    <span>Cód. <?= e($im['codigo']) ?></span>
  </nav>
</div>

<?php if ($indisponivel): ?>
  <?php if ($im['status'] === 'pausado'): ?>
  <div class="aviso aviso--aviso">Este anúncio está pausado no momento. Fale com a gente pelo WhatsApp para saber se o imóvel ainda está disponível, ou veja opções parecidas abaixo.</div>
  <?php else: ?>
  <div class="aviso aviso--aviso">Este imóvel não está mais disponível<?= $im['status'] === 'vendido' ? ' (vendido)' : ($im['status'] === 'alugado' ? ' (alugado)' : '') ?>. Veja opções parecidas abaixo ou fale com a gente.</div>
  <?php endif; ?>
<?php endif; ?>

<section class="galeria" aria-label="Fotos do imóvel" data-galeria>
  <?php if ($fotos): ?>
    <div class="galeria__trilho" data-trilho tabindex="0">
      <?php foreach ($fotos as $i => $f): ?>
        <figure class="galeria__slide<?= foto_em_pe($f) ? ' galeria__slide--em-pe' : '' ?>">
          <img src="<?= e(foto_url($f, 'g')) ?>" alt="<?= e($f['legenda'] ?: 'Foto ' . ($i + 1) . ' do imóvel') ?>"
               <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async"
               <?php if ($f['largura']): ?>width="<?= (int)$f['largura'] ?>" height="<?= (int)$f['altura'] ?>"<?php endif; ?>>
        </figure>
      <?php endforeach; ?>
    </div>
    <?php if (count($fotos) > 1): ?>
      <button class="galeria__seta galeria__seta--ant" type="button" data-ant aria-label="Foto anterior"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg></button>
      <button class="galeria__seta galeria__seta--prox" type="button" data-prox aria-label="Próxima foto"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg></button>
      <span class="galeria__contador" data-contador aria-live="polite">1 / <?= count($fotos) ?></span>
      <button class="galeria__tela-cheia" type="button" data-tela-cheia>Ver em tela cheia</button>
    <?php endif; ?>
  <?php else: ?>
    <div class="galeria__vazia"><img src="<?= e(asset('img/sem-foto.svg')) ?>" alt=""><p>Fotos em breve</p></div>
  <?php endif; ?>
</section>

<?php if (count($fotos) > 1): ?>
<div class="miniaturas" data-miniaturas>
  <?php foreach ($fotos as $i => $f): ?>
    <button type="button" data-ir="<?= $i ?>" aria-label="Ver foto <?= $i + 1 ?>"<?= $i === 0 ? ' aria-current="true"' : '' ?>>
      <img src="<?= e(foto_url($f, 'p')) ?>" alt="" loading="lazy" width="120" height="80">
    </button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="imovel">
  <div class="imovel__principal">
    <header class="imovel__cabeca">
      <div class="selos">
        <span class="selo selo--teal"><?= e(transacao_rotulo($im)) ?></span>
        <span class="selo selo--cinza">Cód. <?= e($im['codigo']) ?></span>
        <span class="selo selo--cinza"><?= e(tipo_rotulo($im['tipo'])) ?></span>
        <?php if (!$temDescricao): ?><span class="selos__acao"><?= $botaoCompartilhar ?></span><?php endif; ?>
      </div>
      <h1><?= e($im['titulo']) ?></h1>
      <p class="imovel__endereco"><?= e(endereco_publico($im)) ?></p>
    </header>

    <div class="imovel__preco-movel">
      <?php foreach ($valores as $v): ?>
        <p><small><?= e($v[0]) ?></small><strong><?= e($v[1]) ?></strong><?= e($v[2]) ?></p>
      <?php endforeach; ?>
    </div>

    <?php if ($specs): ?>
    <ul class="specs">
      <?php foreach ($specs as $s): ?>
        <li>
          <svg class="specs__icone" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconesSpec[$s[0]] ?></svg>
          <span class="specs__texto"><strong><?= e($s[1]) ?></strong><span><?= e($s[2]) ?></span></span>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($yt): ?>
    <section class="bloco">
      <h2>Vídeo</h2>
      <div class="video<?= youtube_vertical($im['video_url']) ? ' video--vertical' : '' ?>" data-video="<?= e($yt) ?>">
        <button type="button" class="video__capa" aria-label="Assistir ao vídeo do imóvel">
          <img src="https://i.ytimg.com/vi/<?= e($yt) ?>/hqdefault.jpg" alt="" loading="lazy" width="480" height="360">
          <span class="video__play" aria-hidden="true"><svg width="28" height="28" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></span>
        </button>
      </div>
    </section>
    <?php endif; ?>

    <?php if (trim((string)$im['descricao']) !== ''): ?>
    <section class="bloco">
      <div class="bloco__cabeca">
        <h2>Sobre o imóvel</h2>
        <?= $botaoCompartilhar ?>
      </div>
      <div class="texto"><?= nl2br(e($im['descricao'])) ?></div>
    </section>
    <?php endif; ?>

    <?php if ($caracteristicas): ?>
    <section class="bloco">
      <h2>Características</h2>
      <ul class="lista-check">
        <?php foreach ($caracteristicas as $c): ?><li><?= e($c) ?></li><?php endforeach; ?>
      </ul>
    </section>
    <?php endif; ?>

    <?php if (!empty($condominio) && (lazer_lista($condominio) || $condominio['descricao'] || $condominio['nome_publico'])): ?>
    <section class="bloco">
      <h2><?= $condominio['nome_publico'] ? 'Condomínio ' . e($condominio['nome']) : 'O condomínio possui' ?></h2>
      <?php if ($condominio['descricao']): ?><div class="texto"><?= nl2br(e($condominio['descricao'])) ?></div><?php endif; ?>
      <?php if ($lazer = lazer_lista($condominio)): ?>
        <ul class="lista-check"><?php foreach ($lazer as $l): ?><li><?= e($l) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($condominio['nome_publico']): ?><p><a class="link-forte" href="<?= e(condominio_url($condominio)) ?>">Ver todos os imóveis deste condomínio</a></p><?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="bloco">
      <h2>Localização</h2>
      <p class="imovel__endereco"><?= e(endereco_publico($im)) ?></p>
      <?php if ($mapa): ?>
        <div class="mapa">
          <iframe title="Mapa da região do imóvel" src="<?= e($mapa) ?>" loading="lazy" referrerpolicy="no-referrer"></iframe>
        </div>
        <?php if ($im['exibir_endereco'] !== 'completo'): ?><p class="nota">Localização aproximada. O endereço exato é informado na visita.</p><?php endif; ?>
      <?php endif; ?>
    </section>
  </div>

  <aside class="imovel__lateral" aria-label="Valores e contato">
    <div class="cartao-preco">
      <?php foreach ($valores as $v): ?>
        <div class="cartao-preco__valor">
          <span><?= e($v[0]) ?></span>
          <strong><?= e($v[1]) ?></strong><?php if ($v[2]): ?><small><?= e($v[2]) ?></small><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php foreach ($extras as $x): ?><p class="cartao-preco__extra"><?= e($x) ?></p><?php endforeach; ?>

      <a class="botao botao--whats botao--grande" href="<?= e(whatsapp_link($im, 'imovel')) ?>" rel="nofollow">
        <?php partial('icone_whats', ['tam' => 22]); ?> Quero saber mais
      </a>
      <a class="botao botao--contorno" href="<?= e(whatsapp_link($im, 'visita')) ?>" rel="nofollow">Agendar uma visita</a>
      <p class="cartao-preco__msg"><span>Abre o WhatsApp com a mensagem:</span> “<?= e($mensagem) ?>”</p>
    </div>
  </aside>
</div>

<div class="barra-whats" aria-hidden="false">
  <a class="botao botao--whats botao--grande" href="<?= e(whatsapp_link($im, 'barra_celular')) ?>" rel="nofollow">
    <?php partial('icone_whats', ['tam' => 22]); ?> Quero saber mais
  </a>
</div>

<?php if ($parecidos): ?>
<section class="secao-faixa" aria-labelledby="parecidos-titulo">
  <div class="secao">
    <h2 id="parecidos-titulo">Imóveis parecidos</h2>
    <div class="grade">
      <?php foreach ($parecidos as $p) partial('card', ['im' => $p]); ?>
    </div>
  </div>
</section>
<?php endif; ?>

<dialog class="tela-cheia" data-dialogo aria-label="Fotos em tela cheia">
  <button class="tela-cheia__fechar" type="button" data-fechar aria-label="Fechar">✕</button>
  <div class="tela-cheia__trilho" data-trilho-cheio>
    <?php foreach ($fotos as $i => $f): ?>
      <figure><img data-src="<?= e(foto_url($f, 'g')) ?>" alt="<?= e($f['legenda'] ?: 'Foto ' . ($i + 1)) ?>"></figure>
    <?php endforeach; ?>
  </div>
</dialog>
<?php $sem_flutuante = true; ?>
