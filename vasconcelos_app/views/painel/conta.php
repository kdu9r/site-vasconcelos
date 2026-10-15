<?php /** @var array $usuario @var ?string $erro @var ?string $totp_secret @var ?string $totp_uri */ ?>
<div class="p-cabeca"><h1>Minha conta</h1></div>
<?php if ($erro): ?><div class="p-aviso p-aviso--erro" role="alert"><?= e($erro) ?></div><?php endif; ?>

<section class="p-cartao">
  <h2>Verificação em duas etapas</h2>
  <?php if ($usuario['totp_ativo']): ?>
    <p><span class="p-tag p-tag--ativo">Ativada</span> Ao entrar, o painel pede o código do app no celular.</p>
    <form method="post" action="/painel/conta" class="p-grade p-grade--3">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="desativar_2fa">
      <label class="p-campo"><span>Senha atual</span><input type="password" name="senha_atual" autocomplete="current-password" required></label>
      <button class="p-botao" type="submit">Desativar</button>
    </form>
  <?php else: ?>
    <p>Protege o painel mesmo se alguém descobrir sua senha. <strong>Recomendado.</strong></p>
    <ol class="p-passos">
      <li>Instale o <strong>Google Authenticator</strong> (ou Microsoft Authenticator) no celular.</li>
      <li>No app, toque em <strong>+</strong> e escaneie o código abaixo.</li>
      <li>Digite o código de 6 números que aparecer no app e sua senha atual.</li>
    </ol>
    <div class="p-qr" data-qr="<?= e($totp_uri) ?>" aria-label="QR code para o app autenticador"></div>
    <p class="p-nota">Não conseguiu escanear? Digite esta chave no app: <code><?= e(trim(chunk_split((string)$totp_secret, 4, ' '))) ?></code></p>
    <form method="post" action="/painel/conta" class="p-grade p-grade--3">
      <?= csrf_field() ?>
      <input type="hidden" name="acao" value="ativar_2fa">
      <label class="p-campo"><span>Código do app</span><input name="codigo" inputmode="numeric" autocomplete="one-time-code" maxlength="7" required></label>
      <label class="p-campo"><span>Senha atual</span><input type="password" name="senha_atual" autocomplete="current-password" required></label>
      <button class="p-botao p-botao--primario" type="submit">Ativar</button>
    </form>
  <?php endif; ?>
</section>

<section class="p-cartao">
  <h2>Trocar senha</h2>
  <form method="post" action="/painel/conta" class="p-grade p-grade--3">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="senha">
    <label class="p-campo"><span>Senha atual</span><input type="password" name="senha_atual" autocomplete="current-password" required></label>
    <label class="p-campo"><span>Nova senha <small>mín. <?= SENHA_MINIMA ?>, letras e números</small></span><input type="password" name="nova_senha" autocomplete="new-password" minlength="<?= SENHA_MINIMA ?>" required></label>
    <label class="p-campo"><span>Repita a nova senha</span><input type="password" name="confirmar" autocomplete="new-password" required></label>
    <button class="p-botao p-botao--primario" type="submit">Trocar senha</button>
  </form>
</section>
<script src="<?= e(asset('js/qrcode.js')) ?>" defer></script>
