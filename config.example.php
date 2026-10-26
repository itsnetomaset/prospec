<?php
// Copie este arquivo para config.php e preencha os dados. O config.php não vai para o Git (.gitignore).
// O .htaccess bloqueia o acesso direto ao config.php pelo navegador.
return [
    // Banco MySQL (hPanel > Bancos de dados MySQL) e chave do Serper (serper.dev > API Key).
    'db' => ['host' => 'localhost', 'name' => 'COLE_AQUI_NOME_DO_BANCO', 'user' => 'COLE_AQUI_USUARIO', 'pass' => 'COLE_AQUI_SENHA'],
    'serper_key' => 'COLE_AQUI_CHAVE_SERPER',
    'agencia' => 'NEMAX Digital',  // nome da agência usado na mensagem para o cliente
    'min_avaliacoes' => 9,         // abaixo disso a empresa é descartada
    'limite_buscas_dia' => 30,     // buscas no Serper por usuário por dia (cada busca gasta crédito)
    'por_pagina' => 50,            // leads por página na lista
    // Usuários. 'hash' é a senha inicial, gerada com:
    //   php -r "echo password_hash('SUA_SENHA_INICIAL', PASSWORD_DEFAULT), PHP_EOL;"
    // No primeiro acesso o sistema obriga a trocar; a nova senha fica no banco (tabela senhas).
    // 'nome' é como a pessoa se apresenta na mensagem. 'admin' pode devolver/alterar qualquer lead e exportar CSV.
    'users' => [
        'admin' => ['nome' => 'Seu Nome', 'admin' => true, 'hash' => 'COLE_AQUI_HASH'],
    ],
];
