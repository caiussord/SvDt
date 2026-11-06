# Validação em hospedagem real (pendente)

Os testes automatizados da versão 2.0.1 foram executados no Local. A instalação e o banco de uma hospedagem real ainda precisam ser verificados. Execute esta lista primeiro em um ambiente de homologação, com backup restaurável e sem clientes reais.

1. Anote as versões do WordPress, PHP e MySQL/MariaDB, o prefixo das tabelas e o fuso configurado no WordPress. Instale o ZIP de distribuição do SvDt e ative o plugin. Confira erros no log do PHP e do WordPress.
2. Confirme que as tabelas do SvDt foram criadas com o prefixo correto. Cadastre um serviço, um profissional, a associação entre eles e um expediente de teste.
3. Pela página pública, faça uma solicitação com nome fictício e e-mail `example.test`. Confirme que a resposta mostra a solicitação como pendente e que o registro aparece no painel e no banco com serviço, profissional, data e horário corretos.
4. Tente reservar o mesmo horário para o mesmo profissional. A segunda tentativa deve ser recusada. Se houver outro profissional disponível, verifique que ele pode receber uma reserva no mesmo horário.
5. Confirme uma reserva no painel e confira o novo status no banco. Teste também cancelamento e a liberação do horário conforme as regras do plugin.
6. Se a hospedagem já tiver a versão 0.3.x, faça uma cópia de homologação e siga [migração e recuperação](migration-v2.md). Compare contagens, IDs, horários e status antes e depois da migração.
7. Remova os registros fictícios criados durante a validação e confirme que a agenda original permanece íntegra. Registre data, ambiente, resultados e eventuais erros antes de liberar a versão em produção.

Não inclua o backup do banco, credenciais ou registros reais no GitHub nem no ZIP do plugin.
