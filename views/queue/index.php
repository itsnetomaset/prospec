<?php
use Prospec\Lead\LeadProfile;

/**
 * @var string $csrf
 * @var \Prospec\Lead\Lead|null $lead       next lead to contact
 * @var \Prospec\Outreach\WhatsAppMessage|null $message
 * @var int $queued
 * @var int $withoutWhatsApp
 * @var list<string> $cities
 * @var list<string> $niches
 */
$hidden = fn (string $action, int $id): string => '<input type="hidden" name="t" value="' . e($csrf) . '"><input type="hidden" name="a" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">';
?>
<div class="fila">
<?php if ($lead && $message): ?>
<p class="meta count"><b><?= $queued ?></b> na fila<?= $withoutWhatsApp ? ', ' . $withoutWhatsApp . ' sem WhatsApp válido (ligar pela aba Leads)' : '' ?></p>
<div class="card">
<div class="card__top"><span class="badge badge--perfil"><?= e(LeadProfile::label($lead->profile)) ?></span><span class="meta"><?= e($lead->city) ?> · <?= e($lead->niche) ?></span></div>
<h3><?= e($lead->name) ?></h3>
<div class="meta"><?= e($lead->rating) ?> estrelas, <?= $lead->reviewCount ?> avaliações. Pontos: <?= $lead->score ?></div>
<div class="tags"><?php foreach ($lead->reasons as $reason): ?><span class="tag"><?= e($reason) ?></span><?php endforeach; ?></div>
<div class="txt"><?= nl2br(e($message->text)) ?></div>
<div class="acoes"><a class="btn btn--wa btn--lg fila-send" data-fid="<?= $lead->id ?>" target="_blank" rel="noopener" href="<?= e($message->url) ?>">Enviar no WhatsApp</a></div>
<div class="acoes">
<form method="post"><?= $hidden('pular', $lead->id) ?><button class="btn btn--ghost btn--sm">Pular</button></form>
<form method="post" data-confirm="Descartar este lead?"><?= $hidden('status', $lead->id) ?><input type="hidden" name="status" value="descartado"><button class="btn btn--ghost btn--sm">Descartar</button></form>
<form method="post" data-confirm="Devolver este lead para a fila geral?"><?= $hidden('devolver', $lead->id) ?><button class="btn btn--ghost btn--sm">Devolver</button></form>
</div>
</div>
<?php else: ?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Fila vazia</span><p class="meta">Pegue um lote para começar. Vêm primeiro os leads com mais pontos e WhatsApp válido.<?= $withoutWhatsApp ? ' Você tem ' . $withoutWhatsApp . ' sem WhatsApp válido (aba Leads).' : '' ?></p></div>
<?php endif; ?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Pegar mais leads</span>
<form class="lote" method="post"><input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="pegarlote">
<select name="qtd"><?php foreach ([10, 20, 30, 50] as $n): ?><option value="<?= $n ?>"<?= $n === 20 ? ' selected' : '' ?>><?= $n ?> leads</option><?php endforeach; ?></select>
<select name="cidade"><option value="">Todas as cidades</option><?php foreach ($cities as $city): ?><option><?= e($city) ?></option><?php endforeach; ?></select>
<select name="nicho"><option value="">Todos os nichos</option><?php foreach ($niches as $niche): ?><option><?= e($niche) ?></option><?php endforeach; ?></select>
<select name="perfil"><option value="">Todos os perfis</option><?php foreach (LeadProfile::LABELS as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
<button class="btn btn--cta">Pegar lote</button></form></div>
</div>
