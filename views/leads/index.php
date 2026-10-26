<?php
use Prospec\Lead\LeadFilter;
use Prospec\Lead\LeadProfile;
use Prospec\Lead\LeadStatus;

/**
 * @var \Prospec\View\View $view
 * @var string $csrf
 * @var string $searchNonce
 * @var array{n: string, c: string}|null $lastSearch
 * @var LeadFilter $filter
 * @var list<string> $cities
 * @var list<string> $niches
 * @var list<array<string, mixed>> $cards
 * @var int $total
 * @var int $pages
 * @var array<string, mixed> $query
 */
$hidden = '<input type="hidden" name="t" value="' . e($csrf) . '">';
?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Nova busca</span>
<form class="busca" method="post" data-busy><?= $hidden ?><input type="hidden" name="n" value="<?= e($searchNonce) ?>"><input type="hidden" name="a" value="buscar">
<input name="nicho" placeholder="Nicho (ex: clínica veterinária)" maxlength="100" required>
<div class="ac"><input name="cidade" placeholder="Cidade (comece a digitar)" maxlength="100" required autocomplete="off" role="combobox" aria-expanded="false" aria-autocomplete="list" aria-controls="ac-lista"><ul id="ac-lista" role="listbox" hidden></ul></div>
<button class="btn btn--cta">Buscar e filtrar</button></form></div>
<?php if ($lastSearch): ?>
<form class="prox" method="post" data-busy><?= $hidden ?><input type="hidden" name="n" value="<?= e($searchNonce) ?>"><input type="hidden" name="a" value="proxima"><button class="btn btn--ghost">Próxima página de <?= e($lastSearch['n']) ?> em <?= e($lastSearch['c']) ?></button></form>
<?php endif; ?>

<form class="filtro" method="get">
<select name="status" data-auto><?php foreach (LeadStatus::LABELS + [LeadFilter::ALL => 'Todos'] as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $filter->status ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<select name="resp" data-auto><?php foreach ([LeadFilter::ALL => 'De todos', LeadFilter::MINE => 'Só meus', LeadFilter::UNOWNED => 'Sem dono'] as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $filter->ownership ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<select name="perfil" data-auto><option value="">Todos os perfis</option><?php foreach (LeadProfile::LABELS as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $filter->profile ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
<select name="cidade" data-auto><option value="">Todas as cidades</option><?php foreach ($cities as $city): ?><option<?= $city === $filter->city ? ' selected' : '' ?>><?= e($city) ?></option><?php endforeach; ?></select>
<select name="nicho" data-auto><option value="">Todos os nichos</option><?php foreach ($niches as $niche): ?><option<?= $niche === $filter->niche ? ' selected' : '' ?>><?= e($niche) ?></option><?php endforeach; ?></select>
</form>
<?php if (!$cards): ?>
<p class="meta">Nenhum lead com esses filtros. Faça uma busca ou mude os filtros.</p>
<?php else: ?>
<p class="meta count"><b><?= $total ?></b> leads, página <?= $filter->page ?> de <?= $pages ?></p>
<?php endif; ?>
<div class="leads">
<?php foreach ($cards as $card) echo $view->render('leads/card', $card + ['csrf' => $csrf]); ?>
</div>
<?php if ($pages > 1): ?>
<div class="pg">
<span><?php if ($filter->page > 1): ?><a href="<?= e(list_url($query, ['pag' => $filter->page - 1])) ?>" class="btn btn--ghost btn--sm">&larr; Anterior</a><?php endif; ?></span>
<span><?php if ($filter->page < $pages): ?><a href="<?= e(list_url($query, ['pag' => $filter->page + 1])) ?>" class="btn btn--ghost btn--sm">Próxima &rarr;</a><?php endif; ?></span>
</div>
<?php endif; ?>
