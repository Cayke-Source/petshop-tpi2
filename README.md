# PetVida — Sistema de Gestão de Pet Shop

Trabalho de **Tecnologias para Internet II — Momento I**.

Pequeno sistema administrativo para um pet shop com banho e tosa, clínica veterinária e loja.
Nesta etapa o servidor (PHP) apenas recebe as requisições, valida os campos e aplica uma
pequena lógica de negócio em cada formulário. O banco de dados ainda **não** foi criado,
mas os formulários foram planejados para 7 tabelas.

## Como executar

Requisito: PHP 8.0 ou superior.

```bash
php -S localhost:8000
```

Depois abra <http://localhost:8000> no navegador.

## Estrutura

```
index.html            página inicial com o menu
paginas/*.html        um formulário por página
css/style.css         estilos de todas as páginas
js/app.js             envia os formulários com fetch (POST) e mostra a resposta
api/validacao.php     funções de validação compartilhadas (CPF, CNPJ, datas, números)
api/*.php             um script por formulário; devolve somente JSON
docs/                 questionários de desenvolvimento
```

## Formulários

| Página | Script | Lógica adicional |
|---|---|---|
| Tutores | `api/tutores.php` | CPF, idade mínima de 18 anos, perfil por faixa etária |
| Animais | `api/animais.php` | peso compatível com a espécie, fase da vida, porte do cão |
| Agendamentos | `api/agendamentos.php` | preço por porte, horário de término, expediente, leva-e-traz |
| Funcionários | `api/funcionarios.php` | piso salarial, CRMV para veterinários, nível e custo mensal |
| Fornecedores | `api/fornecedores.php` | CNPJ, classe de prazo, data de chegada em dias úteis |
| Produtos | `api/produtos.php` | preço de venda, reposição de estoque, controle de validade |

## Tabelas planejadas

`tutores`, `animais`, `funcionarios`, `servicos`, `agendamentos`, `fornecedores`, `produtos`.

## Branches

- `main` — versão final, com tudo reunido
- `branch-aluno-1` — Tutores, Animais e Agendamentos
- `branch-aluno-2` — Funcionários, Fornecedores e Produtos
