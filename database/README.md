# Banco de dados

O schema do projeto é controlado por migrações versionadas em `database/migrate.php`.

## Fluxo recomendado

1. Crie o banco `imperio_do_choco`.
2. Crie um usuário dedicado da aplicação com acesso apenas a esse banco.
3. Copie `.env.example` para `.env` e configure as credenciais locais.
4. Execute as migrações:

```powershell
C:\xampp\php\php.exe database\migrate.php
```

5. Se precisar de um administrador, use o script CLI sem versionar e-mail ou senha:

```powershell
$env:ADMIN_NAME="Administrador local"
$env:ADMIN_EMAIL="seu-email@exemplo.com"
$env:ADMIN_PASSWORD="uma-senha-local-com-12-ou-mais-caracteres"
C:\xampp\php\php.exe scripts\criar-admin.php
Remove-Item Env:ADMIN_NAME, Env:ADMIN_EMAIL, Env:ADMIN_PASSWORD
```

O script cria ou atualiza o usuário informado e armazena somente o hash produzido por `password_hash`.

## O que as migrações fazem

- criam e versionam o schema em `schema_migrations`;
- estruturam `usuarios`, `produtos` e `carrinho_itens`;
- criam `enderecos`, `pedidos`, `pedido_itens` e `estoque_movimentacoes`;
- adicionam índices, restrições e chaves estrangeiras;
- adicionam `produto_id` ao carrinho mantendo um snapshot textual;
- padronizam `ref`, `slug` e `peso_gramas`;
- aplicam soft delete em produtos;
- criam a estrutura de recuperação de senha.

As rotas PHP não criam tabelas automaticamente. Se o schema estiver ausente, a aplicação orienta a executar as migrações.
