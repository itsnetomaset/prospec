# Prospec

Painel de prospecção de clientes da **NEMAX Digital**. Encontra empresas locais pelo Google (via [Serper](https://serper.dev)), filtra as que não têm site, só têm rede social ou têm site fraco, e organiza a abordagem por WhatsApp em equipe.

## Funcionalidades

- **Busca por nicho e cidade**, com autocomplete dos municípios do IBGE
- **Filtro automático**: descarta empresas com poucas avaliações e classifica o perfil (sem site, só rede social, site fraco)
- **Análise de site**: verifica problemas como falta de adaptação ao celular e gera argumentos de venda
- **Funil de leads** com status (novo, contatado, respondeu, reunião, fechado, perdido) e histórico de ações
- **Fila de abordagem** com mensagem de WhatsApp pronta e personalizada
- **Multiusuário**: cada lead tem um dono; limite diário de buscas por usuário
- **Painel** com taxa de resposta por nicho e perfil; exportação CSV para admins
- **Segurança**: senhas com hash, troca obrigatória no primeiro acesso, CSRF, CSP com nonce, limite de tentativas de login

## Stack

PHP 8+ (sem framework), MySQL/MariaDB, HTML/CSS/JS puro.

## Instalação

1. Suba os arquivos para a pasta do domínio (ex.: `public_html/`).
2. Crie um banco MySQL e importe `schema.sql` no phpMyAdmin.
3. Copie `config.example.php` para `config.php` e preencha banco, chave do Serper e usuários.
4. Gere o hash da senha inicial de cada usuário:
   ```bash
   php -r "echo password_hash('SUA_SENHA_INICIAL', PASSWORD_DEFAULT), PHP_EOL;"
   ```
5. Acesse o domínio e faça login. O sistema pede a troca da senha no primeiro acesso.

O `.htaccess` bloqueia o acesso direto a `config.php`, `lib.php` e arquivos `.json`/`.sql`.

---

Desenvolvido por [Giuseppe Maset Neto](https://www.linkedin.com/in/giuseppemasetneto) · [NEMAX Digital](https://nemaxdigital.com.br)
