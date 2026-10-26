<?php /** @var string $error @var string $csrf */ ?>
<span class="eyebrow"><span class="dot"></span>Acesso restrito</span>
<h1>Entrar no <em>painel</em></h1>
<form method="post">
<?php if ($error !== ''): ?><p class="e"><?= e($error) ?></p><?php endif; ?>
<input type="hidden" name="t" value="<?= e($csrf) ?>">
<input name="u" placeholder="Usuário" autocomplete="username" required>
<input name="p" type="password" placeholder="Senha" autocomplete="current-password" required>
<button class="btn btn--cta btn--lg" name="login" value="1">Entrar</button>
</form>
