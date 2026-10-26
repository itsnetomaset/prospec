<?php
use Prospec\Lead\LeadProfile;
use Prospec\Lead\LeadStatus;
use Prospec\Outreach\PhoneNumber;

/**
 * @var \Prospec\Lead\Lead $lead
 * @var bool $mine          the current user owns the lead
 * @var bool $canManage     owner or admin
 * @var string $ownerName
 * @var \Prospec\Outreach\WhatsAppMessage|null $first
 * @var \Prospec\Outreach\WhatsAppMessage|null $followUp
 * @var \Prospec\Outreach\WhatsAppMessage|null $toContact
 * @var string $csrf
 */
$id = $lead->id;
$hidden = fn (string $action): string => '<input type="hidden" name="t" value="' . e($csrf) . '"><input type="hidden" name="a" value="' . $action . '"><input type="hidden" name="id" value="' . $id . '">';
$contact = $lead->contact;
?>
<div class="card">
<div class="card__top"><span class="badge badge--perfil"><?= e(LeadProfile::label($lead->profile)) ?></span><span class="badge st-<?= e($lead->status) ?>"><?= e(LeadStatus::label($lead->status)) ?></span></div>
<h3><?= e($lead->name) ?></h3>
<div class="meta"><?= e($lead->rating) ?> estrelas, <?= $lead->reviewCount ?> avaliações. Pontos: <?= $lead->score ?></div>
<div class="tags"><?php foreach ($lead->reasons as $reason): ?><span class="tag"><?= e($reason) ?></span><?php endforeach; ?></div>
<div class="meta"><?= e($lead->address) ?></div>
<?php if ($lead->lastContactAt): ?><div class="meta">Último contato: <?= e(date('d/m H:i', (int)strtotime($lead->lastContactAt))) ?></div><?php endif; ?>
<?php if ($lead->lastNote): ?><div class="meta">Nota: <?= e($lead->lastNote) ?></div><?php endif; ?>
<div class="acoes">
<?php if ($lead->owner === null): ?>
<form method="post"><?= $hidden('pegar') ?><button class="btn btn--cta">Pegar este lead</button></form>
<?php else: ?>
<?php if ($mine): ?>
<?php if ($first): ?>
<a class="btn btn--wa wa" data-id="<?= $id ?>" target="_blank" rel="noopener" href="<?= e($first->url) ?>">Abrir WhatsApp<?= PhoneNumber::isLandline((string)PhoneNumber::toWhatsApp($lead->phone)) ? ' (fixo)' : '' ?></a>
<?php if ($followUp): ?><a class="btn btn--ghost btn--sm wa" data-id="<?= $id ?>" target="_blank" rel="noopener" href="<?= e($followUp->url) ?>">2ª mensagem (link)</a><?php endif; ?>
<?php if ($toContact): ?><a class="btn btn--wa wa" data-id="<?= $id ?>" target="_blank" rel="noopener" href="<?= e($toContact->url) ?>">WhatsApp do responsável</a><?php endif; ?>
<?php else: ?>
<a class="tel" href="tel:<?= e(preg_replace('/\D/', '', $lead->phone)) ?>">Ligar: <?= e($lead->phone) ?></a>
<?php endif; ?>
<?php else: ?>
<span class="meta">Com <?= e($ownerName) ?> (<?= e(LeadStatus::label($lead->status)) ?>)</span>
<?php endif; ?>
<?php if ($canManage): ?>
<form method="post"><?= $hidden('status') ?>
<select name="status" data-auto><?php foreach (LeadStatus::LABELS as $key => $label): ?><option value="<?= e($key) ?>"<?= $key === $lead->status ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></form>
<form method="post" data-confirm="Devolver este lead para a fila?"><?= $hidden('devolver') ?><button class="btn btn--ghost btn--sm">Devolver</button></form>
<?php if ($lead->profile === LeadProfile::WEAK_WEBSITE && $lead->website !== ''): ?>
<form method="post"><input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="analisar"><input type="hidden" name="url" value="<?= e($lead->website) ?>"><input type="hidden" name="empresa" value="<?= e($lead->name) ?>"><button class="btn btn--ghost btn--sm">Analisar site</button></form>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
</div>
<?php if ($first): ?>
<details><summary>Ver as mensagens</summary>
<div class="txt"><b>1ª mensagem</b><br><?= nl2br(e($first->text)) ?></div>
<?php if ($followUp): ?><div class="txt"><b>2ª mensagem (mesmo número, sem saudação)</b><br><?= nl2br(e($followUp->text)) ?></div><?php endif; ?>
<?php if ($toContact): ?><div class="txt"><b>Mensagem para o responsável</b><br><?= nl2br(e($toContact->text)) ?></div><?php endif; ?>
</details>
<?php endif; ?>
<?php if ($canManage): ?>
<details<?= $contact ? ' open' : '' ?>><summary>Responsável<?= $contact ? ': ' . e(trim($contact->name) . ' ' . $contact->phone) : '' ?></summary>
<form method="post"><?= $hidden('responsavel') ?>
<input name="nome" maxlength="80" placeholder="Nome (ex: Dra. Ana), opcional" value="<?= e($contact?->name ?? '') ?>"><input type="tel" name="zap" maxlength="25" placeholder="(17) 99999-9999" inputmode="tel" value="<?= e($contact?->phone ?? '') ?>"><button class="btn btn--ghost btn--sm">Salvar</button></form>
<p class="meta">Número que a recepção passou. Deixe o campo do número vazio e salve para remover.</p></details>
<details><summary>Informar WhatsApp manualmente</summary><form method="post"><?= $hidden('whatsapp') ?>
<input type="tel" name="zap" maxlength="25" placeholder="(17) 99999-9999" inputmode="tel" required><button class="btn btn--ghost btn--sm">Salvar número</button></form></details>
<details><summary>Anotar</summary><form method="post"><?= $hidden('nota') ?>
<textarea name="texto" rows="2" maxlength="500" placeholder="O que foi falado, quando retornar, motivo de perda..."></textarea><button class="btn btn--ghost btn--sm">Salvar nota</button></form></details>
<?php endif; ?>
</div>
