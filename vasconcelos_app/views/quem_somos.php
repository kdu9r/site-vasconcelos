<?php
$corretores = (array)config('contato.corretores', []);
// foto de cada corretor: assets/img/equipe/<primeiro nome>.jpg (ex.: fred.jpg, priscila.jpg)
$fotoPessoa = 'foto_corretor';
$bios = [
    'fred' => 'Corretor em Bertioga, atende compra, venda e locação de imóveis residenciais, do Centro ao Maitinga e Indaiá.',
    'priscila' => 'Corretora parceira, cuida de perto da locação e do atendimento a quem está chegando à cidade.',
];
$fotoCidade = is_file(rtrim((string)config('public_dir'), '/') . '/assets/img/hero.jpg') ? asset('img/hero.jpg') : null;
?>
<section class="sobre-topo">
  <div class="secao sobre-topo__in">
    <p class="sobretitulo">Quem somos</p>
    <h1>Corretores de imóveis em Bertioga</h1>
    <p class="lead">Atendimento próximo e direto para quem quer comprar, vender ou alugar um imóvel em Bertioga, da primeira conversa à entrega das chaves.</p>
  </div>
</section>

<section class="secao sobre">
  <?php if ($fotoCidade): ?>
    <img class="sobre__foto" src="<?= e($fotoCidade) ?>" alt="Vista aérea de Bertioga" loading="lazy" width="1200" height="675">
  <?php else: ?>
    <div class="sobre__foto preencher-foto" role="img" aria-label="Espaço para foto da equipe ou do escritório"><span>Foto da equipe ou do escritório</span></div>
  <?php endif; ?>
  <div class="sobre__texto">
    <h2>Nossa história</h2>
    <p>A Vasconcelos Corretor nasceu do trabalho de quem conhece Bertioga de perto: as ruas, os bairros, a distância de cada prédio até a praia e o que faz diferença no dia a dia de quem mora ou passa temporadas aqui.</p>
    <p>Cada imóvel é visitado, fotografado e descrito com cuidado, e o atendimento é feito pelos próprios corretores, direto pelo WhatsApp. Sem intermediários e sem formulário: você conversa com quem conhece o imóvel.</p>
  </div>
</section>

<section class="secao-faixa">
  <div class="secao">
    <h2>Equipe</h2>
    <div class="equipe">
      <?php foreach ($corretores as $c): ?>
        <article class="pessoa">
          <?php if ($f = $fotoPessoa($c)): ?>
            <img class="pessoa__foto" src="<?= e($f) ?>" alt="<?= e($c['nome']) ?>" loading="lazy" width="480" height="480">
          <?php else: ?>
            <div class="pessoa__foto preencher-foto" role="img" aria-label="Espaço para foto de <?= e($c['nome']) ?>"><span><?= e($c['iniciais'] ?? '') ?></span></div>
          <?php endif; ?>
          <div class="pessoa__info">
            <h3><?= e($c['nome']) ?></h3>
            <p class="pessoa__creci"><?= e($c['creci']) ?></p>
            <p><?= e($bios[slugify(strtok((string)$c['nome'], ' '))] ?? '') ?></p>
            <a class="botao botao--whats botao--pequeno" href="<?= e(whatsapp_link(null, 'quem_somos', $c['whatsapp'])) ?>" rel="nofollow">
              <?php partial('icone_whats', ['tam' => 16]); ?> <?= e(formata_telefone($c['whatsapp'])) ?>
            </a>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="secao">
  <h2>Como trabalhamos</h2>
  <ul class="lista-check">
    <li>Atendimento direto pelo WhatsApp, sem formulário</li>
    <li>Imóveis anunciados no site e no ZAP Imóveis / VivaReal</li>
    <li>Visitas agendadas no horário que for melhor para você</li>
    <li>Acompanhamento até a assinatura do contrato</li>
  </ul>
</section>
