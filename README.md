# Mazzuco CarWash

Aplicação em PHP 8.2+, MySQL 8/MariaDB, HTML, CSS, JavaScript, AJAX/Fetch e Bootstrap 5.3.3, com instalação via Composer. Catálogo público, contas de clientes, solicitações de orçamento, agendamento com reserva de box e painel administrativo. Visual baseado no logo fornecido, com preto, ciano, azul e prata.

## Produção com Composer

Para Nginx como proxy do Apache e MariaDB, siga [o guia de produção](deploy/README.md). Há exemplos de virtual hosts e ajustes PHP na pasta `deploy/`.

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
composer run env:setup
```

Edite `.env`, configure APP_ENV=production, domínio HTTPS, conexão MariaDB e TRUSTED_PROXIES conforme o guia. Então execute:

```sh
composer run app:install
composer run admin:create
```

O `composer.lock` fixa as versões das dependências. `composer install` preserva banco, contas e `.env`; configuração e instalação do banco são comandos explícitos.

## Acessar neste computador

- Site: http://127.0.0.1:8088
- Administração: http://127.0.0.1:8088/admin
- Conexão: arquivo `.env` na raiz, já configurado com os dados fornecidos. Não compartilhe esse arquivo.
- Primeiro acesso administrativo: `storage/primeiro-acesso.txt`. Conta local com senha aleatória exclusiva, sem senha padrão no código.

Servidor de desenvolvimento:

```powershell
C:/Sites/php82/php.exe -S 127.0.0.1:8088 router.php
```

Ou execute `./iniciar.ps1` no PowerShell dentro da pasta do projeto. O servidor de verificação é encerrado ao concluir o desenvolvimento; execute o comando para abrir a aplicação novamente.

O servidor usa o MySQL existente na porta 3306. A instância isolada em `storage/mysql-dev` foi utilizada no preparo; ela não é necessária para a aplicação configurada. Seus arquivos temporários foram preservados porque a limpeza foi bloqueada pela revisão automática. Não copie `storage/` para uma pasta pública.

## Instalação em outro ambiente

1. Instale PHP 8.2+ com PDO MySQL, cURL, OpenSSL, mbstring, fileinfo e GD; MySQL 8 ou MariaDB. Execute `composer install`.
2. Copie `.env.example` para `.env` e preencha a conexão MySQL e APP_URL.
3. Gere APP_KEY com `php bin/setup-local.php` antes de criar `.env`, ou use 64 caracteres hexadecimais aleatórios gerados de forma segura. O comando preserva arquivos existentes.
4. Execute `php bin/install.php`. O instalador cria banco e tabelas, preservando catálogo existente. Se o usuário não tiver permissão de criar bancos, crie previamente o banco configurado com um usuário autorizado.
5. Execute `php bin/create-admin.php` e informe os dados no terminal. Não passe senhas em argumentos de linha de comando.
6. Aponte o DocumentRoot do Apache para `public/` e habilite mod_rewrite/AllowOverride. O logo está em `public/images/`; o original foi preservado em `images/`.
7. Garanta escrita em `public/uploads/` e configure envio de e-mail do PHP para recuperação de senha; defina MAIL_FROM.

O DocumentRoot deve apontar exclusivamente para `public/`, onde está o `.htaccess` de roteamento. Não há `.htaccess` de roteamento na raiz do repositório: diretórios ancestrais podem ter permissões mais restritas no Apache, como acontece no HestiaCP. Na combinação Nginx + Apache, consulte `deploy/README.md`. Não exponha a raiz do repositório pelo servidor web.

Use um usuário MySQL exclusivo com permissões apenas no banco da aplicação em produção. APP_ENV deve ser `production`. Ative HTTPS. A senha do banco não está no código versionado; `.env` e dados locais estão no `.gitignore`.

## Configuração inicial pelo painel

1. **Serviços:** revise o catálogo de 17 serviços, categorias, textos, duração e veículos. Defina preço fixo ou por veículo para permitir contratação online; mantenha serviços especializados sob orçamento quando necessário. Envie fotos reais e ajuste os destaques.
2. **Agenda → Expediente:** ative os dias reais de funcionamento e configure intervalos. O expediente começa desativado para não inventar horários da empresa.
3. **Agenda → Boxes:** confira os recursos e tipos de veículo atendidos. Caminhões e aeronaves começam sob orçamento e sem recurso habilitado para contratação.
4. **Configurações:** cadastre WhatsApp com DDI e DDD, telefone, endereço, regiões/taxa do leva e traz, antecedência e políticas. Os textos de políticas iniciais são rascunhos operacionais a revisar pela empresa.
5. **Pagamentos:** configure credenciais, ambiente e webhook.

Não foram inventados preços, avaliações, depoimentos ou contatos. O leva e traz começa sob consulta. As políticas de reagendamento são aplicadas ao envio de solicitações; o administrador analisa e executa a alteração. Cancelar o atendimento não reembolsa automaticamente o pagamento.

## Mercado Pago Checkout Pro

A integração utiliza a API de Preferências. Ela cria a preferência no servidor com o total recalculado e o identificador externo do pedido; redireciona para o Checkout Pro; valida a assinatura HMAC do webhook e consulta o pagamento autenticado antes de atualizar o pedido.

1. Configure APP_URL com uma URL pública HTTPS.
2. No painel da aplicação, em Pagamentos → Configurar integração, informe Access Token e segredo do webhook, escolhendo Testes ou Produção.
3. No painel de desenvolvedores do Mercado Pago, configure o tópico de pagamentos para `https://seu-dominio/api/webhook`.
4. Teste com as contas e credenciais de teste apropriadas. A modalidade Testes seleciona `sandbox_init_point`; confirme o fluxo indicado para suas credenciais na documentação do provedor.
5. Valide uma compra de teste, o retorno e a notificação; só então altere para produção e credenciais correspondentes.

Credenciais salvas no painel são criptografadas com APP_KEY e têm prioridade sobre MP_ACCESS_TOKEN e MP_WEBHOOK_SECRET do `.env`. Não troque APP_KEY sem recadastrar esses segredos. O painel mostra apenas se estão configurados, nunca devolve os valores completos ao navegador.

Pagamento aprovado fora do prazo da reserva fica em **Análise necessária**, sem tomar o horário de outro cliente. A equipe deve resolver o atendimento e, quando aplicável, solicitar reembolso em Pagamentos. Notificações duplicadas são idempotentes. Reembolsos integrais exigem perfil administrador e digitação de REEMBOLSAR. Casos com múltiplos pagamentos aprovados exigem resolução individual no provedor.

O retorno do checkout não confirma pagamento. Não há pagamentos fictícios em produção. Sem credenciais e URL HTTPS, a contratação online permanece bloqueada e o orçamento continua disponível.

Agende `php bin/reconcile.php` a cada cinco minutos para expirar reservas e reconciliar pagamentos conhecidos pendentes. As notificações e as consultas manuais pelo ID do Mercado Pago complementam esse processo. Monitore a entrega de webhooks; pagamentos ainda sem ID local dependem da notificação ou da consulta manual.

Documentação oficial consultada:

- [Criar preferência](https://www.mercadopago.com.br/developers/pt/reference/online-payments/checkout-pro-preferences/create-preference/post)
- [Notificações e assinatura](https://www.mercadopago.com.br/developers/en/docs/checkout-pro-preferences/additional-settings/optional-notifications)

## Estrutura

- `public/`: entrada HTTP, estilos, scripts, fontes, imagens públicas e uploads.
- `app/`: configuração, domínio, autenticação, APIs, integração e views.
- `database/`: schema SQL e catálogo inicial.
- `bin/`: instalação, criação de administradores e reconciliação.
- `tests/`: testes com schema MySQL descartável próprio.
- `storage/`: informações locais privadas, ignoradas no Git.

Valores financeiros são armazenados em centavos inteiros; preços e durações ficam registrados nos itens do pedido. Reservas usam transações e bloqueios ordenados dos recursos para impedir concorrência. Promoções não se acumulam: o sistema aplica o melhor desconto elegível e verifica limites no servidor. Histórico administrativo em Auditoria.

## Verificação

```powershell
C:/Sites/php82/php.exe tests/run.php
node --check public/assets/app.js
node --check public/assets/admin.js
C:/Sites/php82/php.exe tests/http.php
```

Os testes criam um schema `mazzuco_test_<identificador>` exclusivo e o removem ao concluir; não utilizam nem apagam os dados do banco principal. O usuário MySQL de testes precisa poder criar/remover esse schema. Incluem reserva concorrente em dois processos, conflito de horário, desconto, validação de pagamento, idempotência, pagamento tardio, transições e assinatura de webhook.

Validação de cobrança real depende das credenciais, URL pública e simulação no Mercado Pago. Revisão visual automatizada no navegador não foi concluída: a ferramenta de Computer Use bloqueou o acesso por não identificar a URL atual com segurança.

## Dependências locais

Bootstrap 5.3.3 (MIT) e fonte Manrope (OFL) foram incluídos em `public/assets/`, sem dependência de CDN em tempo de execução. Composer instala o autoloader e phpdotenv para leitura da configuração. Não há instalação Node necessária; JavaScript usa APIs nativas de navegadores modernos.
