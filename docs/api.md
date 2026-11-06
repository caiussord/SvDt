# API REST do SvDt

| Método | Rota | Acesso |
|---|---|---|
| GET | `/wp-json/svdt/v1/services` | Público |
| GET | `/wp-json/svdt/v1/professionals?service_id=1` | Público |
| GET | `/wp-json/svdt/v1/availability?service_id=1&professional_id=2&date=AAAA-MM-DD` | Público |
| POST | `/wp-json/svdt/v1/bookings` | Público |
| GET | `/wp-json/svdt/v1/admin/bookings` | Administrador |
| GET | `/wp-json/svdt/v1/admin/professionals` | Administrador |
| PATCH | `/wp-json/svdt/v1/admin/bookings/{id}` | Administrador |

O corpo do POST contém `service_id`, `professional_id`, `date`, `time`, `customer_name`, `customer_email`, `customer_phone` e `notes` opcional. Quando `professional_id` é omitido, a API o escolhe automaticamente apenas se um único profissional ativo atender o serviço. Se houver vários, retorna `professional_required` (HTTP 400).

A resposta pública de criação contém apenas `reference` e `status`. Os endpoints administrativos exigem autenticação WordPress e a capacidade `manage_options`. Os formulários administrativos usam nonces.

Para o fluxo completo e as regras do banco, consulte [arquitetura](architecture.md).
