<?php /** @var array $corretores @var string $atual */ ?>
<?php require __DIR__ . '/config_subnav.php'; ?>

<form method="post" action="/painel/configuracoes/compartilhar" class="p-pilha">
  <?= csrf_field() ?>
  <section class="p-cartao">
    <div class="p-cartao__cabeca">
      <h2>Para quem vai o WhatsApp do site</h2>
      <p class="p-nota">Vale para os botões que não escolhem um corretor: "Quero saber mais", "Agendar uma visita", o botão flutuante, o menu e o blog. Os botões com o nome de cada corretor continuam indo para ele.</p>
    </div>
    <fieldset class="p-opcoes p-opcoes--coluna">
      <legend>Enviar as conversas para</legend>
      <label><input type="radio" name="whatsapp_destino" value="aleatorio" <?= $atual === 'aleatorio' ? 'checked' : '' ?>> Aleatório <small class="p-nota">cada clique sorteia entre <?= e(implode(' e ', array_map(fn($c) => explode(' ', $c['nome'])[0], $corretores))) ?></small></label>
      <?php foreach ($corretores as $c): ?>
        <label><input type="radio" name="whatsapp_destino" value="<?= e($c['whatsapp']) ?>" <?= $atual === $c['whatsapp'] ? 'checked' : '' ?>> <?= e($c['nome']) ?> <small class="p-nota"><?= e(formata_telefone($c['whatsapp'])) ?></small></label>
      <?php endforeach; ?>
    </fieldset>
  </section>
  <div class="p-salvar">
    <button class="p-botao p-botao--primario p-botao--grande" type="submit">Salvar</button>
  </div>
</form>
