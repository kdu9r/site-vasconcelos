<h1>Código de verificação</h1>
<p class="p-nota">Abra o app autenticador no celular e digite o código de 6 números.</p>
<?php if ($erro): ?><div class="p-aviso p-aviso--erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
<form method="post" action="/painel/verificar" class="p-form">
  <?= csrf_field() ?>
  <label class="p-campo">
    <span>Código</span>
    <input name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" maxlength="7" required autofocus class="p-codigo">
  </label>
  <button class="p-botao p-botao--primario" type="submit">Confirmar</button>
</form>
<p class="p-nota"><a href="/painel/entrar">Voltar</a></p>
