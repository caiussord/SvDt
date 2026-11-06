# Arquitetura do SvDt

## Fluxo público

```mermaid
flowchart LR
  UI[Shortcode SvDt e fluxo JS] --> Services[GET serviços]
  UI --> Slots[GET disponibilidade]
  UI --> Create[POST reserva]
  Slots --> Schedule[SvDt_Schedule]
  Create --> Booking[SvDt_Bookings]
  Booking --> Lock[GET_LOCK agenda única]
  Lock --> Schedule
  Booking --> DB[(Tabelas MySQL)]
```

`SvDt_Schedule` consulta expediente, bloqueios e reservas pendentes/confirmadas. Para cada período, gera candidatos em passos configuráveis, verifica antecedência, duração mais intervalo e sobreposição. `SvDt_Bookings` valida a entrada, obtém um bloqueio MySQL para a agenda única, recalcula a disponibilidade e insere a reserva. A liberação do bloqueio ocorre em `finally`, inclusive em erros.

## Dados

- `svdt_services`: catálogo e duração.
- `svdt_business_hours`: múltiplos períodos por dia da semana.
- `svdt_blocked_dates`: dias inteiros sem atendimento.
- `svdt_bookings`: dados de contato, intervalo ocupado e estado.

Os nomes físicos recebem `$wpdb->prefix`. A ativação renomeia as tabelas da versão anterior para `svdt_` antes de executar `dbDelta()`, preservando os registros e seus IDs. A versão do esquema fica na opção `svdt_db_version`.

## Segurança e escopo

O REST administrativo exige `manage_options` e a autenticação normal do WordPress. As ações HTML do painel usam nonce e capacidade. O endpoint público tem honeypot e limite de oito tentativas por IP a cada dez minutos. A resposta pública de criação não inclui dados pessoais.

O MVP assume uma agenda/recurso. `GET_LOCK()` serializa criação de reservas no mesmo banco MySQL. Uma versão com múltiplos recursos precisará de uma chave de bloqueio por recurso e testes de concorrência para essa estrutura.
