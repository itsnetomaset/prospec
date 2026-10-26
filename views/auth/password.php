<?php /** @var string $error @var string $csrf @var string $userName */ ?>
<span class="eyebrow"><span class="dot"></span>Olá, <?= e($userName) ?></span>
<h1>Crie sua <em>senha</em></h1>
<p class="lead lead--intro">Escolha uma senha só sua para continuar. Mínimo de 8 caracteres.</p>
<form method="post">
<?php if ($error !== ''): ?><p class="e"><?= e($error) ?></p><?php endif; ?>
<input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="senha">
<input name="n1" type="password" placeholder="Nova senha" autocomplete="new-password" minlength="8" required>
<input name="n2" type="password" placeholder="Repita a nova senha" autocomplete="new-password" minlength="8" required>
<button class="btn btn--cta btn--lg">Salvar senha</button>
</form>
<form method="post" class="form--secondary"><input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="sair"><button class="btn btn--ghost btn--sm">Sair</button></form>
