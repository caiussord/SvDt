# Migração para SvDt 2.0

1. Faça um backup completo do banco e dos arquivos do site com o recurso de backup do Local ou da hospedagem. Guarde-o fora do repositório.
2. Registre as contagens de serviços, períodos de expediente, bloqueios e reservas, além de IDs, datas e status das reservas.
3. Substitua os arquivos do plugin pela versão 2.0.0 e abra o painel WordPress. A ativação cria as tabelas de equipe, adiciona `professional_id` às reservas e migra a agenda única para **Profissional principal**.
4. Confira **SvDt → Profissionais**, **Disponibilidade** e **Agendamentos**. Renomeie o profissional padrão e configure os novos integrantes antes de anunciar o fluxo de equipe.
5. Compare as contagens e uma amostra de reservas com o backup. Não remova as tabelas `svdt_business_hours` e `svdt_blocked_dates`; a segunda continua sendo usada para fechamentos globais.

A migração é idempotente: se for interrompida antes da marca de conclusão, uma nova execução não duplica vínculos nem intervalos já copiados. Intervalos legados sobrepostos são unidos para preservar a disponibilidade efetiva; intervalos inválidos interrompem a migração para revisão. O esquema só é marcado como 2.0.0 depois de verificar que não existem reservas sem profissional.

## Recuperação

Se a verificação falhar, pare as reservas públicas e restaure **o backup completo do banco e dos arquivos** feito no passo 1 pelo Local ou pela hospedagem. Restaurar apenas os arquivos antigos sem o banco pode deixar o esquema incompatível. No ambiente Local deste projeto, também foi guardado um JSON privado com esquema e registros das quatro tabelas antigas; ele serve para comparação e recuperação direcionada, mas não substitui um backup completo para produção.

Após restaurar, confirme que IDs, status e datas das reservas coincidem com o estado anterior. Investigue o erro da migração antes de tentar novamente. Não inclua o backup em Git ou no ZIP distribuível.
