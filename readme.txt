=== SvDt ===
Contributors: caiussord
Tags: booking, appointments, scheduling
Requires at least: 6.2
Requires PHP: 8.0
Stable tag: 2.0.1
License: GPLv2 or later

Plugin autoral demonstrativo para reservas de serviços por profissionais autônomos e pequenas equipes.

== Installation ==

1. Copie a pasta do plugin para wp-content/plugins.
2. Ative SvDt no painel WordPress.
3. Cadastre serviços e vincule-os aos profissionais no menu SvDt.
4. Configure o expediente e as folgas de cada profissional.
5. Coloque [svdt_booking] em uma página.

== Upgrade Notice ==

= 2.0.0 =
Faça backup do banco antes de atualizar. As reservas existentes são vinculadas a um profissional padrão. Consulte a documentação de migração em https://github.com/caiussord/SvDt/blob/main/docs/migration-v2.md.

== Privacy ==

Nome, e-mail, telefone e observações são guardados para administrar reservas. Agendamentos cancelados ou concluídos são apagados após 12 meses. A desinstalação preserva dados por padrão. Para apagá-los ao desinstalar, defina SVDT_DELETE_DATA como true no wp-config.php antes da desinstalação.
