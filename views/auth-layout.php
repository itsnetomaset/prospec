<?php
/** @var \Prospec\View\View $view @var string $content @var string $title */
echo $view->render('partials/head', ['title' => $title]);
?>
<body class="login">
<div class="login__box">
<div class="brand"><?= $view->render('partials/brand') ?></div>
<?= $content ?>
</div>
</body>
</html>
