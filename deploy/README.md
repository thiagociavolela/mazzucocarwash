# Produção: Nginx → Apache → PHP → MariaDB

O Composer instala e fixa as dependências PHP. Nginx, Apache, PHP e MariaDB continuam sendo serviços do servidor; não são instalados pelo Composer. Este projeto mantém PHP/HTML/JS/Bootstrap e usa PDO `mysql` também para conectar ao MariaDB.

## Requisitos

- PHP 8.2+ no terminal e no Apache/PHP-FPM, com as extensões declaradas em `composer.json`.
- Composer 2 atualizado.
- MariaDB 10.11+ como alvo de implantação, com InnoDB e utf8mb4; MySQL 8 também suportado pelo código.
- Nginx e Apache 2.4. Exemplos usam Nginx como entrada HTTPS e Apache ouvindo somente em `127.0.0.1:8080`.

O schema usa `JSON`, suportado no MariaDB como alias de LONGTEXT com validação. Não depende de operadores JSON exclusivos do MySQL. A validação executada neste computador usou MySQL 8; execute os testes em uma instância MariaDB de homologação antes da publicação.

## Instalação

Envie o projeto com `composer.json` e `composer.lock`, preservando `.env`, uploads e APP_KEY de instalações existentes. Não envie `storage/primeiro-acesso.txt`, o banco temporário local ou credenciais de desenvolvimento.

Na pasta da aplicação, por exemplo `/var/www/mazzuco`:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
composer run env:setup
```

`env:setup` cria `.env` com chave aleatória apenas se ele não existir. **Antes de continuar**, edite esse arquivo:

```dotenv
APP_ENV=production
APP_URL=https://carwash.exemplo.com
TRUSTED_PROXIES=127.0.0.1,::1
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=mazzuco_carwash
DB_USER=mazzuco_app
DB_PASS="substitua-pela-senha-do-banco"
MAIL_FROM=atendimento@seu-dominio.com
```

Preserve APP_KEY gerada. Senhas com espaços ou `#` devem ficar entre aspas. Variáveis definidas no ambiente do processo têm prioridade sobre `.env`.

Crie o banco no painel da hospedagem ou por uma sessão administrativa do MariaDB. Crie um usuário próprio com acesso apenas a `mazzuco_carwash.*`, usando utf8mb4/utf8mb4_unicode_ci e InnoDB. O instalador utiliza um banco existente sem exigir privilégios globais; caso ele não exista, só tentará criá-lo com as permissões disponíveis.

```sh
composer run app:install
composer run admin:create
```

Criação de administrador solicita os dados no terminal; não cria conta nem senha padrão. `composer install` não cria administradores nem modifica o banco. Instalações posteriores devem usar `composer install` com o mesmo lock; alterações futuras de schema exigem migrações próprias, não são inferidas pelo instalador inicial.

## Servidores e permissões

Adapte `nginx.conf.example` e `apache.conf.example` ao domínio, caminho e versão/socket PHP existentes. O exemplo Apache usa PHP-FPM; com mod_php, mantenha o handler já configurado e remova o SetHandler do exemplo. O DocumentRoot deve ser exclusivamente `public/`. Os exemplos são arquivos para revisão, não foram aplicados a nenhum servidor.

Configure `public/uploads/` para escrita pelo usuário do PHP. Mantenha o restante do código sem escrita pelo processo web e `.env` legível somente pelo usuário/grupo autorizado. Não use permissões 777. O diretório privado `storage/` não precisa de escrita pelo processo web nas rotas atuais.

Habilite os módulos necessários de acordo com a distribuição. Valide as configurações antes de recarregar:

```sh
nginx -t
apachectl configtest
```

O Nginx sobrescreve X-Forwarded-Proto e X-Real-IP. A aplicação só confia nesses cabeçalhos se o IP que conecta ao Apache estiver listado em TRUSTED_PROXIES. Isso mantém cookies Secure quando TLS termina no Nginx e preserva a limitação de login por IP de cliente. Não habilite `mod_remoteip` em conjunto sem adaptar esse contrato, pois ele pode substituir REMOTE_ADDR antes da aplicação. Se o proxy estiver em outra máquina, configure seu IP exato e restrinja a porta do Apache à rede interna correspondente.

Os exemplos não contemplam proxy/CDN anterior ao Nginx. Se houver um, configure a identificação do IP real na camada Nginx, confiando somente nos endereços desse serviço.

## Rotina de operação

### HestiaCP

Mantenha os templates gerenciados pelo HestiaCP. Configure a raiz personalizada para a pasta `public/` da aplicação. Para um clone em `/home/mazzuco/web/mazzucocarwash.com/public_html/mazzucocarwash`, o diretório relativo é `mazzucocarwash/public`.

O arquivo de roteamento deve estar somente em `public/.htaccess`. Um `.htaccess` com blocos IfModule na raiz do repositório pode causar `500: <IfModule not allowed here`, pois essa pasta está acima do DocumentRoot e pode aceitar menos diretivas. Essa versão remove o arquivo da raiz. Atualize com `git pull --ff-only origin main`, preservando `public/.htaccess` e `public/uploads/.htaccess`.

Se o erro persistir, consulte as últimas linhas de `/var/log/apache2/domains/mazzucocarwash.com.error.log`. Um eventual erro `open_basedir restriction in effect` é uma configuração distinta: a raiz permitida ao PHP precisa incluir a aplicação completa, enquanto o DocumentRoot continua limitado a `public/`.

### Pagamentos e manutenção

Configure credenciais e webhook do Mercado Pago no painel, em Pagamentos. URL pública HTTPS necessária. Configure transporte de e-mail do PHP, além de MAIL_FROM, para recuperação de senha.

Agende o comando abaixo a cada cinco minutos no cron do usuário da aplicação, adaptando os caminhos. Exemplo com flock para impedir sobreposição:

```cron
*/5 * * * * /usr/bin/flock -n /var/www/mazzuco/storage/reconcile.lock /usr/bin/php /var/www/mazzuco/bin/reconcile.php >> /var/www/mazzuco/storage/reconcile.log 2>&1
```

O usuário do cron precisa escrever esses dois arquivos em storage. Configure rotação do log. Faça backup do MariaDB, uploads, `.env` e APP_KEY; restaurar apenas o banco não permite descriptografar os segredos sem a chave.

## Homologação

```sh
composer validate --strict
composer run test:runtime
composer run test
```

`test` cria e remove um schema descartável próprio; use um usuário de homologação autorizado a essa operação, separado do usuário de produção. Após iniciar um servidor local, `composer run test:http` cobre as rotas e o login com a conta local gerada no desenvolvimento; esse teste não deve ser executado na produção.

Confirme também o cookie Secure através do domínio HTTPS, login, upload, criação de reserva, webhook e pagamento de teste antes de ativar vendas.

Referências: [Composer install](https://getcomposer.org/doc/03-cli.md#install-i), [MariaDB JSON](https://mariadb.com/docs/server/reference/data-types/string-data-types/json), [Nginx proxy](https://nginx.org/en/docs/http/ngx_http_proxy_module.html).
