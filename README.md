# Onboarding System

Sistema simples em HTML, Bootstrap, PHP e MySQL para substituir a planilha de onboarding por cadastros separados por aba. O portal usa login por PIN para clientes e um painel admin para gerar PINs e acompanhar os envios.

## Acesso

- Admin: PIN `7X90K`
- Cliente: PIN gerado pelo admin no painel.

## Abas da planilha mapeadas

- Dados de Loja
- Categoria
- Ativos Fisicos
- Usuarios Internos
- Gerente de loja
- Industrias
- Dados Bancarios
- Planta de loja
- Ativos Digitais
- Alcada
- Header Clientes

## Como rodar localmente

1. Coloque a pasta em `C:\xampp\htdocs\onboardingsite`.
2. Inicie Apache e MySQL no XAMPP.
3. Importe ou rode o arquivo `sql/schema.sql` no MySQL.
4. Se o banco ja existia antes do login por PIN, rode tambem `sql/migration_login_pins.sql`.
5. Acesse `http://localhost/onboardingsite/`.

## Estrutura

- `index.html`: interface com abas e formularios.
- `backend/api/save_onboarding.php`: API unica para gravar cada aba em sua tabela.
- `backend/api/auth.php`: login, status da sessao e logout.
- `backend/api/admin_users.php`: criacao de usuarios/PINs e painel de progresso.
- `backend/config/db.php`: conexao com o banco `onboarding_system`.
- `sql/schema.sql`: criacao do banco e das tabelas.
- `sql/migration_login_pins.sql`: ajuste para bancos ja criados antes do login por PIN.
