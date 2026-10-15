<h1>Criar administrador</h1>
<p class="p-nota">Este é o primeiro acesso ao painel. Crie a sua conta de administrador.</p>
<?php if ($erro): ?><div class="p-aviso p-aviso--erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
<form method="post" action="/painel/primeiro-acesso" class="p-form">
  <?= csrf_field() ?>
  <input type="hidden" name="token" value="<?= e($token) ?>">
  <label class="p-campo"><span>Seu nome</span><input name="nome" required autocomplete="name"></label>
  <label class="p-campo"><span>E-mail</span><input type="email" name="email" required autocomplete="username"></label>
  <label class="p-campo"><span>Senha <small>mín. <?= SENHA_MINIMA ?>, letras e números</small></span><input type="password" name="senha" required minlength="<?= SENHA_MINIMA ?>" autocomplete="new-password"></label>
  <button class="p-botao p-botao--primario" type="submit">Criar administrador</button>
</form>
