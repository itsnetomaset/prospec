<?php
/**
 * @var \Prospec\View\View $view
 * @var string $content
 * @var string $base
 * @var string $csrf
 * @var string $tab        leads|queue|audit|dashboard
 * @var string $userName
 * @var bool $isAdmin
 * @var int $searchesToday
 * @var int $searchLimit
 * @var string $csvUrl
 * @var array{0: string, 1: string}|null $flash
 */
echo $view->render('partials/head', ['title' => 'Prospec | NEMAX Digital']);
$tabs = ['leads' => ['./', 'Leads'], 'queue' => ['?fila=1', 'Fila'], 'audit' => ['?site=1', 'Analisar'], 'dashboard' => ['?painel=1', 'Painel']];
?>
<body data-t="<?= e($csrf) ?>">
<header class="hdr"><div class="wrap hdr__in">
<a class="brand" href="./" aria-label="Prospec, NEMAX Digital"><?= $view->render('partials/brand') ?></a>
<nav class="nav">
<?php foreach ($tabs as $key => [$href, $label]): ?><a href="<?= e($href) ?>"<?= $key === $tab ? ' class="is-on"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
<?php if ($isAdmin): ?><a href="<?= e($csvUrl) ?>">CSV</a><?php endif; ?>
<form method="post" class="nav__f"><input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="trocarsenha"><button>Trocar senha</button></form>
</nav>
<div class="user"><span class="uso">Buscas hoje <?= $searchesToday ?>/<?= $searchLimit ?></span><b><?= e($userName) ?></b>
<form method="post"><input type="hidden" name="t" value="<?= e($csrf) ?>"><input type="hidden" name="a" value="sair"><button class="btn btn--ghost btn--sm">Sair</button></form></div>
</div></header>
<main class="wrap">
<?php if ($flash): ?><div class="msg<?= $flash[0] === 'error' ? ' e' : '' ?>"><?= e($flash[1]) ?></div><?php endif; ?>
<?= $content ?>
</main>
<script src="<?= e($base) ?>assets/js/app.js"></script>
</body>
</html>
