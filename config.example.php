<?php
// Copy this file to config.php and fill in your values. config.php is git-ignored.
// The .htaccess file blocks direct browser access to config.php.
return [
    // MySQL database and Serper API key (serper.dev > API Key).
    'db' => ['host' => 'localhost', 'name' => 'COLE_AQUI_DB_NAME', 'user' => 'COLE_AQUI_DB_USER', 'pass' => 'COLE_AQUI_DB_PASSWORD'],
    'serper_key' => 'COLE_AQUI_SERPER_KEY',
    'agencia' => 'NEMAX Digital',  // agency name used in the outreach message
    'min_avaliacoes' => 9,         // businesses with fewer reviews are discarded
    'limite_buscas_dia' => 30,     // Serper searches per user per day (each search costs credits)
    'por_pagina' => 50,            // leads per page in the list
    // Users. 'hash' is the initial password, generated with:
    //   php -r "echo password_hash('INITIAL_PASSWORD', PASSWORD_DEFAULT), PHP_EOL;"
    // On first login the user must set a new password, which is stored in the database (table senhas).
    // 'nome' is how the user signs the outreach message. 'admin' can reassign any lead and export CSV.
    'users' => [
        'admin' => ['nome' => 'Your Name', 'admin' => true, 'hash' => 'COLE_AQUI_HASH'],
    ],
];
