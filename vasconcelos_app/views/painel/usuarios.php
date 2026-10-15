<?php /** @var array $usuarios @var array $atividades @var ?string $erro @var ?string $senha_gerada @var array $usuario */ ?>
<div class="p-cabeca"><h1>Acessos</h1></div>
<?php if ($erro): ?><div class="p-aviso p-aviso--erro" role="alert"><?= e($erro) ?></div><?php endif; ?>
<?php if ($senha_gerada): ?>
  <div class="p-aviso p-aviso--ok">
    Senha temporária: <code class="p-senha"><?= e($senha_gerada) ?></code><br>
    Envie para a pessoa por um canal seguro. Ela aparece só agora. Peça para trocar em "Minha conta" no primeiro acesso.
  </div>
<?php endif; ?>

<section class="p-cartao">
  <h2>Quem tem acesso</h2>
  <ul class="p-lista">
    <?php foreach ($usuarios as $u): ?>
      <li class="p-item p-item--usuario">
        <div class="p-item__info">
          <strong><?= e($u['nome']) ?></strong>
          <span><?= e($u['email']) ?><?= $u['creci'] ? ' · ' . e($u['creci']) : '' ?></span>
          <span class="p-tags">
            <span class="p-tag"><?= $u['papel'] === 'admin' ? 'Administrador' : 'Corretor' ?></span>
            <?php if (!$u['ativo']): ?><span class="p-tag p-tag--vendido">Bloqueado</span><?php endif; ?>
            <?php if ($u['totp_ativo']): ?><span class="p-tag p-tag--ativo">2 etapas</span><?php else: ?><span class="p-tag p-tag--alerta">Sem 2 etapas</span><?php endif; ?>
            <span class="p-tag">Último acesso: <?= $u['ultimo_login'] ? e(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : 'nunca' ?></span>
          </span>
        </div>
        <div class="p-item__acoes">
          <form method="post" action="/painel/usuarios"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="acao" value="redefinir"><button class="p-botao p-botao--pequeno" type="submit">Nova senha</button></form>
          <?php if ((int)$u['id'] !== (int)$usuario['id']): ?>
            <?php if ($u['totp_ativo']): ?>
              <form method="post" action="/painel/usuarios"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="acao" value="zerar_2fa"><button class="p-botao p-botao--pequeno p-botao--fantasma" type="submit">Tirar 2 etapas</button></form>
            <?php endif; ?>
            <form method="post" action="/painel/usuarios"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$u['id'] ?>"><input type="hidden" name="acao" value="<?= $u['ativo'] ? 'desativar' : 'ativar' ?>"><button class="p-botao p-botao--pequeno <?= $u['ativo'] ? 'p-botao--perigo' : '' ?>" type="submit"><?= $u['ativo'] ? 'Bloquear' : 'Desbloquear' ?></button></form>
          <?php endif; ?>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
</section>

<section class="p-cartao">
  <h2>Dar acesso a alguém</h2>
  <form method="post" action="/painel/usuarios" class="p-grade p-grade--3">
    <?= csrf_field() ?>
    <input type="hidden" name="acao" value="criar">
    <label class="p-campo"><span>Nome</span><input name="nome" required></label>
    <label class="p-campo"><span>E-mail</span><input type="email" name="email" required></label>
    <label class="p-campo"><span>CRECI</span><input name="creci"></label>
    <label class="p-campo"><span>Permissão</span>
      <select name="papel"><option value="corretor">Corretor (cadastra imóveis)</option><option value="admin">Administrador (tudo)</option></select>
    </label>
    <button class="p-botao p-botao--primario" type="submit">Criar acesso</button>
  </form>
</section>

<section class="p-cartao">
  <h2>Últimas atividades</h2>
  <table class="p-tabela">
    <thead><tr><th>Quando</th><th>Quem</th><th>O quê</th></tr></thead>
    <tbody>
      <?php foreach ($atividades as $a): ?>
        <tr><td><?= e(date('d/m H:i', strtotime($a['criado_em']))) ?></td><td><?= e($a['nome'] ?? '—') ?></td><td><?= e($a['acao']) ?><?= $a['detalhe'] ? ' · ' . e($a['detalhe']) : '' ?></td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>
