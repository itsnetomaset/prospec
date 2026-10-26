<?php
use Prospec\Dashboard\FunnelReport;
use Prospec\Lead\LeadProfile;
use Prospec\Lead\LeadStatus;

/**
 * @var list<array<string, mixed>> $byNiche
 * @var array<string, int> $byStatus
 * @var list<array<string, mixed>> $byOwner
 * @var \Prospec\Auth\UserDirectory $users
 */
?>
<span class="eyebrow"><span class="dot"></span>Funil</span><h1 class="pg-title">Painel do <em>funil</em></h1>
<p class="lead lead--spaced">Abordados = contatado, respondeu, reunião, fechado ou perdido. % de resposta = quem respondeu ou avançou, dividido pelos abordados.</p>
<div class="tw"><table><tr><th>Status</th><th>Leads</th></tr>
<?php foreach (LeadStatus::LABELS as $key => $label): ?><tr><td><?= e($label) ?></td><td><?= (int)($byStatus[$key] ?? 0) ?></td></tr><?php endforeach; ?>
</table></div>
<div class="tw"><table><tr><th>Nicho</th><th>Perfil</th><th>Total</th><th>Abordados</th><th>Responderam</th><th>% resp.</th><th>Reuniões</th><th>Fechados</th></tr>
<?php foreach ($byNiche as $row): ?><tr><td><?= e($row['nicho']) ?></td><td><?= e(LeadProfile::label((string)$row['perfil'])) ?></td><td><?= (int)$row['total'] ?></td><td><?= (int)$row['abordados'] ?></td><td><?= (int)$row['responderam'] ?></td><td><?= FunnelReport::rate((int)$row['responderam'], (int)$row['abordados']) ?></td><td><?= (int)$row['reunioes'] ?></td><td><?= (int)$row['fechados'] ?></td></tr><?php endforeach; ?>
</table></div>
<div class="tw"><table><tr><th>Pessoa</th><th>Leads pegos</th><th>Já trabalhados</th></tr>
<?php foreach ($byOwner as $row): ?><tr><td><?= e($users->displayName((string)$row['dono'])) ?></td><td><?= (int)$row['pegos'] ?></td><td><?= (int)$row['trabalhados'] ?></td></tr><?php endforeach; ?>
</table></div>
