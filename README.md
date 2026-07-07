# Leandro DEV Financeiro — MM Construtora

**Versão:** 1.0.0
**Licença:** Proprietary — MM Construtora
**Stack:** PHP 8.2+ puro (sem framework) + MySQL 8.0+/MariaDB 10.6+ + Bootstrap 5.3 + Chart.js 4

## Descrição

Sistema financeiro completo para a MM Construtora. Auto-instalável, com módulos de:
- Dashboard com saldos por conta, fluxo de caixa (6 meses), próximas contas programadas e últimos lançamentos
- Contas (bancárias, caixa, ASAAS, carteira)
- Plano de Contas hierárquico (RECEITA/DESPESA/NEUTRO)
- Lançamentos (receitas, despesas, transferências)
- Contas Programadas (recorrências com geração automática)
- Transferências entre contas (atômicas — 2 lançamentos)
- Clientes/Fornecedores (com busca ViaCEP)
- Relatórios (Fluxo de Caixa, DRE Simplificado, Saldos)
- Usuários e Permissões (ADMIN/OPERADOR/VISUALIZADOR)
- Configurações (empresa, segurança, backup, sistema)
- Backup/Restore (mysqldump ou fallback PDO)
- Logs de Auditoria (todas operações de escrita)

## Requisitos

- PHP 8.2 ou superior
- MySQL 8.0+ ou MariaDB 10.6+
- Extensões PHP: `pdo_mysql`, `mbstring`, `openssl`, `gd`, `json`, `session`
- Permissões de escrita em `/storage/logs`, `/storage/backups`, `/storage/uploads`, `/storage/sessions`

## Instalação

1. Faça upload de todos os arquivos para o servidor (FTP, git, etc.)
2. Acesse a URL do sistema no navegador
3. Você verá a tela de login com um banner amarelo no topo: "Sistema ainda não instalado"
4. Clique em "Instalar agora"
5. Siga o wizard de 4 etapas:
   - **Etapa 1 — Pré-requisitos:** validação automática de PHP, extensões e permissões
   - **Etapa 2 — Banco de Dados:** informe host, porta, nome do banco, usuário e senha do MySQL
   - **Etapa 3 — Empresa + Admin:** razão social (default: MM Construtora), CNPJ, logo e dados do administrador
   - **Etapa 4 — Confirmar:** revise e clique em "Instalar agora"
6. Após instalar, faça login com as credenciais do admin criado
7. **Recomendado:** após instalar, delete o arquivo `install.php` por segurança

## Reinstalação

Para reinstalar o sistema:

1. Delete o arquivo `config.php` (na raiz do projeto)
2. Delete o arquivo `storage/.installed`
3. Acesse a URL novamente — o banner de instalação aparecerá

**Atenção:** reinstalar não apaga o banco de dados existente. Para limpar todos os dados, drop o schema no MySQL antes de reinstalar:

```sql
DROP DATABASE leandro_dev_fin;
```

## Estrutura de Diretórios

```
leandro-dev-financeiro/
├── index.php              # Front controller / router
├── install.php            # Wizard de instalação (delete após instalar)
├── login.php              # Tela de login com banner install
├── logout.php             # Logout
├── config.php             # Gerado pelo installer (NÃO commitar)
├── config.example.php     # Template de config
├── db.sql                 # Schema do banco
├── .htaccess              # Rewrite rules + segurança
├── README.md              # Este arquivo
├── app/
│   ├── Core/              # Classes core (App, Database, Auth, Csrf, Session, etc.)
│   ├── Controllers/       # Controllers MVC
│   ├── Models/            # Models (extends BaseModel)
│   ├── Helpers/           # Helpers (Format, Sanitizer, Menu)
│   ├── Views/             # Views PHP por módulo
│   └── Layouts/           # Layouts (header, sidebar, topbar, footer)
├── public/
│   ├── css/app.css
│   ├── js/{app,mask,viacep}.js
│   └── assets/img/
└── storage/               # Logs, backups, uploads (NÃO commitar)
    ├── logs/
    ├── backups/
    ├── uploads/
    └── sessions/
```

## Segurança

- Senhas com `password_hash` bcrypt custo 12
- CSRF token em todos formulários POST
- Session cookie HttpOnly + SameSite=Strict
- Rate limit de login (5 tentativas / 15 minutos por IP e por email)
- PDO prepared statements em todas as queries (zero concatenação SQL)
- `.htaccess` bloqueia acesso direto a `/app/`, `/storage/`, `config.php`, `db.sql`
- Logs de auditoria em todas as operações de escrita (CREATE/UPDATE/DELETE/LOGIN/LOGOUT/BACKUP/RESTORE/INSTALL)
- Validação server-side sempre (JavaScript é apenas conveniência)

## Troubleshooting

### Tela branca ao acessar
- Verifique se o PHP está na versão 8.2+
- Verifique `error_log` do Apache/nginx
- Verifique permissões em `/storage/`
- Ative temporariamente `display_errors=On` no php.ini para diagnóstico

### Erro "Sistema já instalado"
- Delete `storage/.installed` para reinstalar
- OU delete `config.php` e `storage/.installed`

### Erro de conexão com banco
- Confirme credenciais no `config.php`
- Teste conexão: `mysql -u <user> -p -h <host> -P <port> <db_name>`
- Verifique se MySQL permite conexões do host do PHP
- Verifique firewall do servidor MySQL

### db.sql não executa
- Verifique se o usuário MySQL tem privilégio `CREATE DATABASE`
- Execute manualmente: `mysql -u root -p < db.sql`
- Verifique se MySQL é 8.0+ (algumas features requerem 8.0+)
- Veja erro detalhado em `/storage/logs/error_YYYYMMDD.log`

### Logs de erro
- Erros PHP: `/storage/logs/error_YYYYMMDD.log`
- Auditoria: tabela `logs_auditoria` no banco
- Tentativas de login: tabela `logs_tentativas_login` no banco

## Backup

- Acesse o módulo "Backup" (apenas ADMIN)
- Clique em "Gerar Backup Agora"
- O sistema tenta `mysqldump` primeiro; se indisponível, faz dump via PDO
- Arquivos `.sql` ficam em `/storage/backups/`
- Manter últimos N backups (configurável em Configurações → Backup)

## Módulo NFSe Bahia

O sistema inclui módulo NFSe (Nota Fiscal de Serviço Eletrônica) para os 10 maiores municípios da Bahia:

### Municípios suportados
- Salvador (sistema próprio)
- Feira de Santana, Camaçari, Vitória da Conquista, Juazeiro, Lauro de Freitas (WebISS)
- Itabuna, Ilhéus, Jequié (Betha)
- Teixeira de Freitas (DSF)

### Configuração inicial
1. Acesse o menu **NFSe → Configurar** e selecione o município ativo + ambiente (HOMOLOGACAO/PRODUCAO)
2. Informe a Inscrição Municipal (IM) da MM Construtora
3. Acesse **NFSe → Certificado** e faça upload do certificado digital A1 (.pfx/.p12) + senha
4. Teste emissão em HOMOLOGACAO antes de mudar para PRODUCAO

### Operações
- **Emitir NFSe**: gera RPS, assina XML com certificado A1, envia para prefeitura, retorna número + código verificação
- **Consultar**: consulta situação pelo protocolo
- **Cancelar**: cancela NFSe autorizada com motivo
- **DANFSE**: gera PDF imprimível da NFSe autorizada

### Testes
```bash
# Instalar PHPUnit (opcional, apenas para testes)
composer require --dev phpunit/phpunit ^10

# Rodar testes unitários
vendor/bin/phpunit tests/Nfse

# Rodar teste E2E com mock HTTP server
php tests/E2E/mock_homologacao.php
```

### Limitações
- Apenas 4 provedores implementados (WebISS, Betha, DSF, Salvador). SimplISS e ISSNet retornam erro claro se selecionados.
- Apenas emissão síncrona unitária (não suporta lote assíncrono).
- Não suporta substituição de NFSe.

## Suporte

Para suporte, entre em contato com o desenvolvedor:

- **E-mail:** [leog3@live.com](mailto:leog3@live.com)
- **Telefone/WhatsApp:** [+55 71 99178-2319](https://wa.me/5571991782319)
- **Horário de atendimento:** Segunda a Sexta, 08h às 18h (Bahia)

Para abrir um chamado técnico, descreva:
- Versão do sistema (rodapé do sistema mostra v1.0.0)
- Ambiente (homologação/produção)
- Passos para reproduzir o problema
- Logs relevantes em `/storage/logs/error_YYYYMMDD.log`

---

Copyright © 2026 Leandro DEV — MM Construtora. Todos os direitos reservados.
