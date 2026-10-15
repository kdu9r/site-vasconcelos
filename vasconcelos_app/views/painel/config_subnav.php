<?php
/** Sub-abas de Configurações (só administrador). */
$aqui = request_path();
$abas = [
    '/painel/configuracoes/compartilhar' => 'Compartilhar',
    '/painel/integracoes' => 'Integrações',
    '/painel/importar' => 'Migração',
];
?>
<div class="p-cabeca"><h1>Configurações</h1></div>
<nav class="p-subnav" aria-label="Configurações">
  <?php foreach ($abas as $url => $rot): ?>
    <a href="<?= $url ?>"<?= str_starts_with($aqui, $url) ? ' aria-current="page"' : '' ?>><?= $rot ?></a>
  <?php endforeach; ?>
</nav>
