<?php /** @var array $estado @var bool $token_configurado @var string $feed_url */ ?>
<?php require __DIR__ . '/config_subnav.php'; ?>
<h2 class="p-subtitulo">Migração da Code49</h2>

<section class="p-cartao">
  <?php if (!$token_configurado): ?>
    <div class="p-aviso p-aviso--aviso">Coloque o token da API da Code49 no arquivo <code>config.php</code> (campo <code>code49.token</code>) para liberar a migração.</div>
  <?php endif; ?>
  <p>Traz todos os imóveis da Code49 com textos e fotos em tamanho cheio. Pode rodar quantas vezes quiser: o que já foi importado não duplica, só atualiza o que mudou.</p>
  <ul class="p-passos">
    <li>A API permite 1.000 consultas por dia. Cada imóvel usa 1 consulta.</li>
    <li>Deixe esta página aberta até terminar. Se fechar, é só voltar e clicar em <strong>Continuar</strong>.</li>
    <li>Os destaques do CanalPro não vêm da Code49: marque de novo depois, no painel.</li>
  </ul>
  <div class="p-acoes">
    <button class="p-botao p-botao--primario" type="button" data-migrar="listar" <?= $token_configurado ? '' : 'disabled' ?>>1. Buscar lista de imóveis</button>
    <button class="p-botao" type="button" data-migrar="proximo" <?= $token_configurado && $estado['pendentes'] ? '' : 'disabled' ?>>2. <?= $estado['feitos'] ? 'Continuar' : 'Importar' ?> (<?= count($estado['pendentes']) ?> na fila)</button>
  </div>
  <div class="p-progresso<?= $estado['total'] ? '' : ' p-oculto' ?>" data-progresso-migracao>
    <div data-pct="<?= $estado['total'] ? round($estado['feitos'] / $estado['total'] * 100) : 0 ?>"></div>
    <span><?= (int)$estado['feitos'] ?> de <?= (int)$estado['total'] ?></span>
  </div>
  <ol class="p-log" data-log aria-live="polite"></ol>
  <p class="p-nota">O relatório completo fica em <code>vasconcelos_app/storage/relatorio_migracao.csv</code>.</p>
</section>

<section class="p-cartao">
  <h2>Ligar o CanalPro de uma vez</h2>
  <p class="p-nota">Imóveis novos e migrados chegam com o CanalPro <strong>desligado</strong>. Quando for trocar o cadastro manual pelo feed automático, use este botão para ligar em todos os imóveis que estão no ar. Depois disso, pare de cadastrar à mão no CanalPro para não duplicar anúncios.</p>
  <form method="post" action="/painel/importar">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="ligar_portais">
    <button class="p-botao" type="submit">Ligar CanalPro em todos os imóveis no ar</button>
  </form>
</section>

<section class="p-cartao" id="feed">
  <h2>Endereço do feed (para o CanalPro)</h2>
  <p class="p-nota">Cadastre este endereço no CanalPro (Integração, formato VRSync). Ele é secreto: o feed tem o endereço completo e a localização de todos os imóveis, então não divulgue nem cole em grupos.</p>
  <?php if (config('modo_teste')): ?>
    <div class="p-aviso p-aviso--aviso">Este é o site de demonstração: o feed fica desligado aqui. Ele passa a funcionar no endereço definitivo.</div>
  <?php endif; ?>
  <label class="p-campo"><span>Endereço do feed</span><input value="<?= e($feed_url) ?>" readonly spellcheck="false" data-selecionar></label>
  <form method="post" action="/painel/importar" data-confirmar="Trocar o endereço? O endereço atual para de funcionar e você vai precisar cadastrar o novo no CanalPro.">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="novo_feed">
    <button class="p-botao" type="submit">Gerar endereço novo</button>
  </form>
  <p class="p-nota">Use só se o endereço vazar.</p>
</section>
