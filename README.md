# Onboarding System

Sistema simples em HTML, Bootstrap, PHP e MySQL para substituir a planilha de onboarding por cadastros separados por aba. O portal e generico para todos os clientes.

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
4. Acesse `http://localhost/onboardingsite/`.

## Estrutura

- `index.html`: interface com abas e formularios.
- `backend/api/save_onboarding.php`: API unica para gravar cada aba em sua tabela.
- `backend/config/db.php`: conexao com o banco `onboarding_system`.
- `sql/schema.sql`: criacao do banco e das tabelas.
