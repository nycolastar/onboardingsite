# Onboarding System

Sistema simples em HTML, Bootstrap, PHP e MySQL para substituir a planilha de onboarding por cadastros separados por aba. O portal usa login por PIN para clientes e um painel admin para gerar PINs e acompanhar os envios.

## Acesso

- Admin: PIN `7X90K`
- Cliente: PIN gerado pelo admin no painel.
- Link do Drive: informado pelo admin ao criar o usuario, usado na aba Planta de loja.

## Painel admin

No painel admin e possivel:

- criar usuarios e gerar PINs;
- acompanhar quantos registros cada cliente enviou por aba;
- visualizar os dados enviados em formato de tabela;
- exportar os dados do cliente para Excel;
- exportar um script SQL para uso no DBeaver ou outro cliente de banco.

## Preenchimento em grade

Na area do cliente, o modo `Grade` permite preencher varias linhas da mesma aba antes de salvar. Linhas vazias sao ignoradas, e linhas parcialmente preenchidas ainda respeitam os campos obrigatorios.

## Registros do cliente

Abaixo dos formularios, o cliente ve os registros ja enviados na aba atual. O botao `Editar` carrega a linha no formulario individual para corrigir e salvar novamente.

## Abas da planilha mapeadas

- Dados de Loja
- Categoria
- Usuarios Internos
- Gerente de loja
- Industrias
- Dados Bancarios
- Planta de loja
- Ativos Fisicos
- Ativos Digitais
- Alcada

## Como rodar localmente

1. Coloque a pasta em `C:\xampp\htdocs\onboardingsite`.
2. Inicie Apache e MySQL no XAMPP.
3. Importe ou rode o arquivo `sql/schema.sql` no MySQL.
4. Se o banco ja existia antes do login por PIN, rode tambem `sql/migration_login_pins.sql`.
5. Se o banco ja existia antes do campo de Drive, rode tambem `sql/migration_drive_link.sql`.
6. Acesse `http://localhost/onboardingsite/`.

## Estrutura

- `index.html`: interface com abas e formularios.
- `backend/api/save_onboarding.php`: API unica para gravar cada aba em sua tabela.
- `backend/api/user_records.php`: lista os registros ja enviados pelo cliente logado.
- `backend/api/auth.php`: login, status da sessao e logout.
- `backend/api/admin_users.php`: criacao de usuarios/PINs e painel de progresso.
- `backend/api/admin_export.php`: exportacoes de Excel e SQL restritas ao admin.
- `backend/config/db.php`: conexao com o banco `onboarding_system`.
- `backend/config/onboarding_sections.php`: definicao compartilhada das abas e campos.
- `sql/schema.sql`: criacao do banco e das tabelas.
- `sql/migration_login_pins.sql`: ajuste para bancos ja criados antes do login por PIN.
- `sql/migration_drive_link.sql`: adiciona o link do Drive por usuario.
