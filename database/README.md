# Banco de dados

O projeto agora usa migracoes versionadas em `database/migrate.php`.

## Fluxo recomendado

1. Crie o banco `imperio_do_choco`.
2. Crie um usuario dedicado da aplicacao com acesso apenas a esse banco.
3. Configure as credenciais em `.env`.
4. Execute as migracoes:

```powershell
C:\xampp\php\php.exe database\migrate.php
```

5. Se quiser o usuario administrador inicial, execute depois:

```sql
SOURCE database/criar-admin.sql;
```

## O que as migracoes fazem

- criam e versionam o schema em `schema_migrations`
- endurecem `usuarios`, `produtos` e `carrinho_itens`
- criam `enderecos`, `pedidos`, `pedido_itens` e `estoque_movimentacoes`
- adicionam indices para busca e ordenacao
- criam chaves estrangeiras do carrinho para usuarios e produtos
- adicionam `produto_id` ao carrinho mantendo snapshot textual
- padronizam `ref`, `slug` e `peso_gramas`
- aplicam soft delete em produtos com `ativo` e `deleted_at`

## Observacao

As rotas PHP nao criam mais tabelas automaticamente. Se o schema estiver ausente, a aplicacao retorna erro orientando a rodar as migracoes.
