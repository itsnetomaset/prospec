<?php
use Prospec\Site\SiteAuditor;

/**
 * @var string $csrf
 * @var array<string, mixed>|null $audit   last audit result (SiteAuditor::evaluate() + company, typedUrl)
 * @var string $summary                   message for the business owner
 * @var string $shareUrl                  wa.me link with the summary
 * @var string $agency
 */
$labels = [SiteAuditor::CRITICAL => 'Crítico', SiteAuditor::IMPORTANT => 'Importante', SiteAuditor::ATTENTION => 'Atenção'];
?>
<div class="rel">
<div class="box noprint"><span class="eyebrow"><span class="dot"></span>Analisar site</span>
<form class="busca" method="post" data-busy><input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="analisar">
<input name="url" placeholder="www.empresa.com.br" maxlength="300" required value="<?= e($audit['typedUrl'] ?? '') ?>"><input name="empresa" placeholder="Nome da empresa (opcional)" maxlength="100" value="<?= e($audit['company'] ?? '') ?>"><button class="btn btn--cta">Analisar</button></form>
<p class="meta">Confere segurança, celular, velocidade, contato e pontos que influenciam o Google. Leva alguns segundos.</p></div>
<?php if ($audit && isset($audit['error'])): ?>
<div class="msg e"><?= e($audit['error']) ?></div>
<?php elseif ($audit):
    $count = [SiteAuditor::CRITICAL => 0, SiteAuditor::IMPORTANT => 0, SiteAuditor::ATTENTION => 0];
    foreach ($audit['findings'] as $finding) {
        $count[$finding['severity']]++;
    }
    $good = count($audit['good']);
?>
<div class="box"><span class="eyebrow"><span class="dot"></span>Relatório do site</span>
<h2><?= e($audit['company'] !== '' ? $audit['company'] : $audit['host']) ?></h2>
<p class="meta"><?= e($audit['url']) ?> · analisado em <?= e($audit['checkedAt']) ?> por <?= e($agency) ?></p>
<div class="tags">
<span class="tag"><?= $count[SiteAuditor::CRITICAL] ?> <?= $count[SiteAuditor::CRITICAL] === 1 ? 'crítico' : 'críticos' ?></span>
<span class="tag"><?= $count[SiteAuditor::IMPORTANT] ?> <?= $count[SiteAuditor::IMPORTANT] === 1 ? 'importante' : 'importantes' ?></span>
<span class="tag"><?= $count[SiteAuditor::ATTENTION] ?> para melhorar</span>
<span class="tag"><?= $good ?> <?= $good === 1 ? 'ponto bom' : 'pontos bons' ?></span>
</div></div>
<?php if (!$audit['findings']): ?><div class="box"><p>Não encontrei problemas nos itens verificados. O site está bem nos pontos técnicos básicos.</p></div><?php endif; ?>
<?php foreach ($audit['findings'] as $finding): ?>
<div class="box ach ach--<?= e($finding['severity']) ?>"><span class="badge badge--perfil"><?= e($labels[$finding['severity']]) ?></span><h3><?= e($finding['title']) ?></h3>
<p><b>O que encontrei:</b> <?= e($finding['detail']) ?></p><p class="meta"><b>Por que importa:</b> <?= e($finding['impact']) ?></p></div>
<?php endforeach; ?>
<?php if ($audit['good']): ?><div class="box"><span class="eyebrow"><span class="dot"></span>O que está bom</span><ul class="ok"><?php foreach ($audit['good'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($audit['notes'] || $audit['warnings']): ?><p class="meta"><?php foreach (array_merge($audit['notes'], $audit['warnings']) as $note): ?><?= e($note) ?><br><?php endforeach; ?></p><?php endif; ?>
<div class="box noprint"><span class="eyebrow"><span class="dot"></span>Mensagem para o dono</span>
<textarea id="resumo-txt" rows="9" readonly><?= e($summary) ?></textarea>
<div class="acoes"><button type="button" class="btn btn--cta" id="copiar">Copiar mensagem</button><a class="btn btn--wa" target="_blank" rel="noopener" href="<?= e($shareUrl) ?>">Abrir no WhatsApp</a><button type="button" class="btn btn--ghost" id="imprimir">Imprimir ou salvar em PDF</button></div></div>
<?php endif; ?>
</div>
