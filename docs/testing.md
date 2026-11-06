# Testes e resultados

## Ambiente Local

Validado no Local com WordPress 7.1.4, PHP 8.2.29 e MySQL 8.4.0. Os scripts abaixo são para a instalação de desenvolvimento e não acompanham o ZIP de distribuição.

- `wp eval-file tests/smoke-local.php`: fluxo individual e regras do MVP.
- `wp eval-file tests/team-local.php`: equipe temporária, serviço incompatível, folga individual, disponibilidade e conflitos por profissional. O script executa `tests/team-concurrency-local.mjs` e remove seus registros ao terminar. Ajuste `SVDT_PORT` se o Local não usar a porta 10011.
- `wp eval-file tests/seed-local.php`: prepara serviços, expediente e página da demonstração. Altera dados do site; use apenas em uma instalação de demonstração.
- `wp eval-file tests/seed-showcase-local.php`: com a tabela de reservas vazia, cria cinco reservas fictícias para os profissionais já cadastrados, com e-mails `example.test`. Use apenas em uma instalação de demonstração.

Nos testes HTTP concorrentes, duas solicitações para o mesmo profissional e horário resultaram em **201/409**. Para profissionais diferentes no mesmo horário, o resultado foi **201/201**. O endpoint administrativo de profissionais retornou **401** sem autenticação.

Uma instalação limpa isolada ativou o plugin, criou o profissional padrão, exibiu horários e gravou uma solicitação. Ela usou o MySQL do Local com prefixo de tabelas separado; as tabelas de teste foram removidas.

## Migração

Antes da migração 2.0, foi gerado um backup local fora do repositório. A comparação posterior confirmou IDs, dados, datas e status dos sete serviços e onze agendamentos existentes. Todas as reservas ficaram ligadas ao profissional padrão. Reexecutar a migração não duplicou serviços, expediente ou reservas. Consulte [migração e recuperação](migration-v2.md).

## Interface e limites

No navegador, cadastro de profissional, expediente, rejeição de intervalos sobrepostos e os fluxos públicos individual e em equipe foram verificados em desktop e celular. A validação em hospedagem real e as mudanças de horário de verão em fusos afetados continuam pendentes. Siga o [roteiro de homologação](hosting-validation.md).
