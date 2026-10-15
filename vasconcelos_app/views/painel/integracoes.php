<?php /** @var array $campos @var array $valores @var array $erros */
$campo = function (string $k) use ($campos, $valores, $erros) {
    $c = $campos[$k];
    $id = 'int-' . $k; ?>
  <label class="p-campo" for="<?= e($id) ?>">
    <span><?= e($c['rotulo']) ?></span>
    <input id="<?= e($id) ?>" name="<?= e($k) ?>" value="<?= e((string)($valores[$k] ?? '')) ?>" placeholder="Ex.: <?= e($c['exemplo']) ?>" autocomplete="off" spellcheck="false"<?= isset($erros[$k]) ? ' aria-invalid="true"' : '' ?>>
    <?php if (isset($erros[$k])): ?><small class="p-erro"><?= e($erros[$k]) ?></small><?php endif; ?>
    <small class="p-nota"><?= $c['ajuda'] /* texto fixo do sistema, não vem do usuário */ ?></small>
  </label>
<?php };
$ativo = fn(string $k) => !empty($valores[$k]) ? '<span class="p-tag p-tag--ativo">Ativo</span>' : '<span class="p-tag">Desligado</span>';
?>
<div class="p-cabeca"><h1>Integrações</h1></div>
<p class="p-nota">Cole só o código (ID) de cada ferramenta. O site coloca os scripts sozinho, nas páginas públicas, e nunca no painel. Deixe em branco o que não usar. Visitas de quem está logado no painel não entram nas estatísticas.</p>
<?php if ($erros): ?><div class="p-aviso p-aviso--erro" role="alert">Confira os campos marcados.</div><?php endif; ?>

<form method="post" action="/painel/integracoes" class="p-pilha">
  <?= csrf_field() ?>

  <section class="p-cartao">
    <h2>Google <?= $ativo('gtm_id') ?></h2>
    <p class="p-nota">Use o <strong>Tag Manager</strong> se quiser gerenciar tudo por lá. Se preferir o simples, preencha só Analytics e Ads e deixe o Tag Manager vazio. Usar os dois para a mesma coisa conta as visitas em dobro.</p>
    <div class="p-grade">
      <?php $campo('gtm_id'); $campo('ga4_id'); ?>
    </div>
  </section>

  <section class="p-cartao">
    <h2>Google Ads <?= $ativo('ads_id') ?></h2>
    <p class="p-nota">Cada clique em um botão de WhatsApp vira uma conversão no Google Ads, com o código do imóvel.</p>
    <div class="p-grade">
      <?php $campo('ads_id'); $campo('ads_rotulo_whatsapp'); ?>
    </div>
  </section>

  <section class="p-cartao">
    <h2>Instagram e Facebook <?= $ativo('meta_pixel') ?></h2>
    <p class="p-nota">O Pixel registra visitas, imóveis vistos e cliques no WhatsApp (evento "Contato"), para anúncios e remarketing no Instagram.</p>
    <div class="p-grade">
      <?php $campo('meta_pixel'); $campo('meta_dominio'); ?>
    </div>
  </section>

  <section class="p-cartao">
    <h2>Google Search Console <?= $ativo('gsc_verificacao') ?></h2>
    <p class="p-nota">Prova ao Google que o site é seu. Depois de verificado, envie o mapa do site: <code><?= e(url('/sitemap.xml')) ?></code></p>
    <div class="p-grade">
      <?php $campo('gsc_verificacao'); ?>
    </div>
  </section>

  <section class="p-cartao">
    <h2>Privacidade (LGPD)</h2>
    <label class="p-check"><input type="checkbox" name="pedir_consentimento" value="1" <?= ($valores['pedir_consentimento'] ?? '1') !== '0' ? 'checked' : '' ?>> Mostrar aviso de cookies e só ativar Google e Meta depois que o visitante aceitar</label>
    <p class="p-nota">Recomendado. A política de privacidade do site é atualizada sozinha conforme o que estiver ativo.</p>
  </section>

  <div class="p-salvar"><button class="p-botao p-botao--primario" type="submit">Salvar integrações</button></div>
</form>
