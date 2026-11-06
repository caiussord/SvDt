# SvDt — agendamento com uma experiência melhor

Plugin WordPress autoral demonstrativo para negócios que atendem com hora marcada. O visitante percorre três etapas claras: serviço, data e horário, dados. O painel apresenta as reservas como uma linha do tempo e permite confirmar, cancelar e concluir cada pedido.

**SvDt** vem de **Save the Date**. A identidade usa a marca enviada para este projeto. O ícone de barbearia também foi fornecido para a demonstração; confirme sua autoria e licença antes da publicação pública. Ícones gratuitos do Flaticon podem exigir crédito ao autor.

## Experiência

- A agenda aparece após a escolha do serviço e mostra apenas horários válidos para sua duração.
- Um horário selecionado abre o formulário; voltar preserva as escolhas anteriores.
- O resumo e o indicador de progresso acompanham cada etapa.
- Estados de carregamento, vazio, erro e sucesso têm mensagens próprias.
- A página de demonstração usa o template opcional **SvDt · Página de reservas**. O shortcode continua funcionando em outros temas.
- Navegação por teclado, foco visível, labels e preferência por movimento reduzido.

![Página SvDt em desktop](docs/screenshots/svdt-desktop-step1.png)

![Escolha de horário no celular](docs/screenshots/svdt-mobile-slots.png)

![Escolha de horário em desktop](docs/screenshots/svdt-desktop-slots.png)

![Painel de agendamentos](docs/screenshots/svdt-admin-bookings.png)

Outras telas: [serviço no celular](docs/screenshots/svdt-mobile-step1.png), [dados](docs/screenshots/svdt-mobile-step3.png), [confirmação](docs/screenshots/svdt-success.png), [serviços no painel](docs/screenshots/svdt-admin-services.png) e [expediente](docs/screenshots/svdt-admin-schedule.png).

## Funcionalidades do MVP

- Serviços com duração, intervalo após atendimento, descrição e estado ativo.
- Expediente com múltiplos intervalos por dia e bloqueio de datas.
- Disponibilidade com antecedência mínima de 60 minutos e passos de 30 minutos.
- Reserva pública com status pendente, validação no servidor, honeypot e limite de tentativas por IP.
- Bloqueio MySQL por agenda durante a nova verificação e gravação, evitando reservas simultâneas sobrepostas.
- Painel com filtros por data, status e serviço; ações de confirmação, cancelamento e conclusão.
- Endpoints REST públicos e administrativos.

## Instalação

Requisitos: WordPress 6.2+, PHP 8.0+ e MySQL com suporte a `GET_LOCK()`. O MVP usa uma agenda de atendimento.

1. Copie a pasta do plugin para `wp-content/plugins/` e ative **SvDt** em **Plugins**.
2. Em **SvDt → Serviços**, cadastre os serviços.
3. Em **SvDt → Disponibilidade**, configure o expediente. Sem intervalos ativos, não há horários livres.
4. Crie uma página com `[svdt_booking]`. Para reproduzir a demonstração, selecione o template **SvDt · Página de reservas** e defina a página como inicial.
5. Ajuste o fuso em **Configurações → Geral**. O plugin usa esse fuso para todas as reservas.

O shortcode antigo `[agendapro_booking]` continua aceito para preservar páginas existentes. As tabelas e os nomes internos ainda usam `agendapro_` para preservar os dados da versão anterior; a marca pública, o menu e a API principal são SvDt.

Nesta instalação Local, a demonstração abre em **`http://localhost:10011/`** e o painel em **`http://localhost:10011/wp-admin/`**. O domínio `agendapro.local` cadastrado no Local não resolve neste Windows porque falta sua entrada no arquivo `hosts`.

## API

| Método | Rota | Acesso |
|---|---|---|
| GET | `/wp-json/svdt/v1/services` | Público |
| GET | `/wp-json/svdt/v1/availability?service_id=1&date=AAAA-MM-DD` | Público |
| POST | `/wp-json/svdt/v1/bookings` | Público |
| GET | `/wp-json/svdt/v1/admin/bookings` | Administrador |
| PATCH | `/wp-json/svdt/v1/admin/bookings/{id}` | Administrador |

As rotas `agendapro/v1` permanecem como alias de compatibilidade. O corpo do POST contém `service_id`, `date`, `time`, `customer_name`, `customer_email`, `customer_phone` e `notes` opcional. A resposta pública contém apenas `reference` e `status`.

## Verificação

Validado no Local com WordPress 7.1.4, PHP 8.2.29 e MySQL 8.4.0. O plugin ativou e criou quatro tabelas. O teste `tests/smoke-local.php` verificou reserva, conflitos, transições de status, cancelamento, data bloqueada, serviço inativo e entrada inválida. Duas requisições simultâneas para o mesmo horário produziram HTTP 201 e HTTP 409. O endpoint administrativo rejeitou acesso público com HTTP 401. No navegador, o novo fluxo avançou e voltou sem perder a seleção, enviou uma reserva e permitiu confirmá-la no painel.

Para reproduzir: `wp eval-file tests/seed-local.php`, `wp eval-file tests/smoke-local.php` e `node tests/concurrency-local.mjs` no ambiente Local. O teste de concorrência usa a porta 10011 por padrão; ajuste `AGENDAPRO_PORT` se necessário. Ele deixa uma reserva fictícia pendente.

## Privacidade e limites

Nome, e-mail, telefone e observações são armazenados para administrar a solicitação. Reservas canceladas e concluídas são removidas pelo WP-Cron após 12 meses. Reservas pendentes e confirmadas permanecem até que o administrador altere seu status. A desinstalação preserva dados por padrão; para apagá-los de forma irreversível, defina `AGENDAPRO_DELETE_DATA` como `true` no `wp-config.php` antes de desinstalar.

O MVP não oferece pagamentos, e-mails automáticos, múltiplos profissionais, integração com calendários ou reagendamento pelo cliente. Horas locais repetidas em mudanças de horário de verão exigem teste adicional antes do uso em fusos afetados.

Veja [arquitetura](docs/architecture.md) para as decisões técnicas. Este é um projeto demonstrativo, sem clientes reais. Licença GPL-2.0-or-later.
