<?php /** @var array $condominios */ ?>
<div class="p-cabeca">
  <h1>Condomínios</h1>
  <a class="p-botao p-botao--primario" href="/painel/condominios/novo">+ Novo condomínio</a>
</div>
<p class="p-nota">Cadastre uma vez e use em vários imóveis: as fotos das áreas comuns, o lazer e o endereço entram sozinhos no anúncio.</p>
<?php if (!$condominios): ?>
  <div class="p-vazio"><p>Nenhum condomínio cadastrado.</p><a class="p-botao p-botao--primario" href="/painel/condominios/novo">Cadastrar o primeiro</a></div>
<?php else: ?>
<ul class="p-lista">
  <?php foreach (anexar_capas_condominios($condominios) as $c): ?>
    <li class="p-item">
      <a class="p-item__foto" href="/painel/condominios/<?= (int)$c['id'] ?>"><img src="<?= e(foto_url($c['capa'], 'p')) ?>" alt="" loading="lazy" width="120" height="90"></a>
      <div class="p-item__info">
        <a class="p-item__titulo" href="/painel/condominios/<?= (int)$c['id'] ?>"><?= e($c['nome']) ?></a>
        <span><?= e($c['bairro'] ?? 'Sem bairro') ?> · <?= (int)$c['qtd_fotos'] ?> fotos · <?= (int)$c['qtd_imoveis'] ?> imóveis</span>
        <span class="p-tags">
          <?php if ($c['nome_publico']): ?><span class="p-tag p-tag--ativo">Nome aparece no site</span><?php else: ?><span class="p-tag">Nome só no painel</span><?php endif; ?>
        </span>
      </div>
      <div class="p-item__acoes"><a class="p-botao p-botao--pequeno" href="/painel/condominios/<?= (int)$c['id'] ?>">Editar</a></div>
    </li>
  <?php endforeach; ?>
</ul>
<?php endif; ?>
