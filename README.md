# SvDt — Sistema de Agendamento para WordPress

O **SvDt** (*Save the Date*) é um plugin WordPress para agendamentos de profissionais autônomos e pequenas equipes. O cliente escolhe um serviço, um profissional disponível e um horário; o administrador organiza e confirma as solicitações no painel do WordPress.

O projeto combina uma interface responsiva com validação das regras de disponibilidade no servidor e prevenção de reservas simultâneas conflitantes.

## Demonstração

![Interface pública do SvDt](docs/screenshots/svdt-desktop-step1.png)

O repositório apresenta capturas do fluxo público e do painel. Ainda não há uma demonstração online; a [instalação](#instalação) permite testar o plugin em um WordPress próprio.

## Funcionalidades

- Agendamento online por serviço, profissional, data e horário, com solicitação inicialmente pendente.
- Agendas individuais para vários profissionais, inclusive atendimentos simultâneos.
- Serviços com duração, intervalo após atendimento e vínculo com os profissionais.
- Expedientes, folgas individuais e bloqueios globais de datas.
- Calendário e filtros no painel, com ações de confirmar, cancelar e concluir reservas.
- Disponibilidade limitada aos próximos 90 dias e revalidada ao gravar a reserva.
- Interface responsiva, com etapas progressivas, estados de carregamento e navegação por teclado.

## Tecnologias

WordPress, PHP, JavaScript, HTML, CSS, MySQL e WordPress REST API.

## Capturas de tela

### Escolha de horário

![Horários disponíveis no desktop](docs/screenshots/svdt-desktop-slots.png)

### Agendamento pelo celular

![Horários disponíveis no celular](docs/screenshots/svdt-mobile-slots.png)

### Gerenciamento de reservas

![Painel de agendamentos](docs/screenshots/svdt-admin-bookings.png)

Outras telas: [equipe no desktop](docs/screenshots/svdt-desktop-team.png), [equipe no celular](docs/screenshots/svdt-mobile-team.png), [profissionais](docs/screenshots/svdt-admin-professionals.png), [serviços](docs/screenshots/svdt-admin-services.png), [expediente](docs/screenshots/svdt-admin-schedule.png), [serviço no celular](docs/screenshots/svdt-mobile-step1.png), [dados do cliente](docs/screenshots/svdt-mobile-step3.png) e [confirmação](docs/screenshots/svdt-success.png).

## Como funciona

1. O cliente escolhe o serviço e, se houver mais de um profissional habilitado, seleciona quem vai atendê-lo.
2. A agenda mostra datas e horários compatíveis com o expediente, a duração do serviço e as reservas existentes.
3. O cliente informa seus dados e envia a solicitação. O horário fica reservado como **pendente**, aguardando decisão administrativa.
4. No painel WordPress, o administrador confirma, cancela ou conclui a reserva.

## Instalação

**Requisitos:** WordPress 6.2+, PHP 8.0+ e MySQL com suporte a `GET_LOCK()`.

1. Copie a pasta `svdt` para `wp-content/plugins/` e ative **SvDt** no WordPress.
2. Em **SvDt → Serviços**, cadastre os serviços; em **Profissionais**, vincule cada pessoa aos serviços que atende.
3. Em **Disponibilidade**, configure o expediente de cada profissional e o fuso em **Configurações → Geral**.
4. Crie uma página com o shortcode `[svdt_booking]`. O template opcional **SvDt · Página de reservas** reproduz a apresentação das capturas.

Uma instalação nova cria um profissional padrão, editável no painel. Para atualizar uma instalação antiga, faça backup e siga o [guia de migração](docs/migration-v2.md).

## Arquitetura

O código separa persistência e migração, cálculo de disponibilidade, criação de reservas, API REST e administração. Consulte a [arquitetura](docs/architecture.md) e a [referência da API](docs/api.md).

## Segurança e testes

O servidor valida serviço, profissional, expediente e horário. A criação de reservas usa honeypot, limite de tentativas por IP e lock MySQL por profissional para evitar conflitos. As ações administrativas exigem autenticação, permissão e nonce quando feitas por formulários.

Os testes locais cobrem fluxo individual, equipe, concorrência, migração e instalação limpa. A verificação em hospedagem real continua pendente. Veja [testes e resultados](docs/testing.md) e o [roteiro de homologação](docs/hosting-validation.md).

O ZIP de distribuição contém apenas os arquivos necessários do plugin. Backups, dados de clientes, testes, capturas e histórico Git ficam fora dele.

## Limitações e próximos passos

Esta versão não inclui pagamentos, e-mails automáticos, integração com calendários externos, múltiplas unidades ou reagendamento pelo cliente. Fusos com horário de verão exigem validação adicional. O próximo passo antes do uso em produção é executar a homologação em hospedagem real.

## Licença

Distribuído sob [GPL-2.0-or-later](LICENSE). Este é um projeto demonstrativo; as reservas exibidas nas capturas usam dados fictícios.
