<?php

declare(strict_types=1);

/*
 * Fallback PSR-4 autoloader for hosts without Composer (e.g. shared hosting).
 * When vendor/autoload.php exists, Composer's autoloader is used instead.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Prospec\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/Support/helpers.php';
