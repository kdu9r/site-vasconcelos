<h1>Entrar no painel</h1>
<?php if ($erro): ?><div class="p-aviso p-aviso--erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
<form method="post" action="/painel/entrar" class="p-form">
  <?= csrf_field() ?>
  <label class="p-campo">
    <span>E-mail</span>
    <input type="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
  </label>
  <label class="p-campo">
    <span>Senha</span>
    <input type="password" name="senha" autocomplete="current-password" required>
  </label>
  <button class="p-botao p-botao--primario" type="submit">Entrar</button>
</form>
<p class="p-nota">Esqueceu a senha? Peça ao administrador para gerar uma nova.</p>
