# PRYSMA — Sistema de Gestão Imobiliária

Aplicação web completa (PHP puro + PostgreSQL) que implementa os casos de uso
UC01–UC08 do projeto PRYSMA, para apresentação em `localhost` na banca da
semana de 22 de setembro.

Stack: **PHP 8.3+ (sem framework) · PostgreSQL 16 · HTML5/CSS3/JS puro**,
igual ao descrito no memorial do projeto.

## 1. Pré-requisitos

- PHP 8.3 ou superior, com as extensões `pdo_pgsql` e `pgsql` habilitadas.
- PostgreSQL 16 instalado e em execução.
- (Opcional) `psql` no PATH para rodar os scripts SQL pela linha de comando.

Para conferir se as extensões do PHP estão ativas:

```bash
php -m | grep pgsql
```

Se não aparecer `pdo_pgsql` e `pgsql`, instale-as (ex.: no Windows via XAMPP/WampServer
já vêm habilitadas; no Ubuntu: `sudo apt install php-pgsql`).

## 2. Criar o banco de dados

Abra um terminal com `psql` (ou o pgAdmin) e rode o script de schema, que já
cria a base `prysma`, as tabelas, restrições (CHECK/FK/UNIQUE) e as triggers
de regra de negócio:

```bash
psql -U postgres -f prysma_schema.sql
```

Se preferir criar a base antes manualmente, remova as 3 primeiras linhas do
arquivo (`CREATE DATABASE prysma;` e os comentários) e rode o restante já
conectado ao banco `prysma`.

Crie um usuário de aplicação (ajuste a senha se desejar, mas mantenha
coerência com `app/includes/config.php`):

```sql
CREATE ROLE prysma_app WITH LOGIN PASSWORD 'prysma_app';
GRANT ALL PRIVILEGES ON DATABASE prysma TO prysma_app;
\c prysma
GRANT ALL ON ALL TABLES IN SCHEMA public TO prysma_app;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO prysma_app;
```

> **Se você já tinha criado o banco antes** (numa versão anterior deste
> pacote), não precisa recriar tudo do zero: basta rodar a migração
> incremental abaixo, que adiciona só o que mudou (colunas novas na tabela
> `Boleto` e o gatilho de prioridade alta dos chamados) sem apagar seus
> dados:
> ```bash
> psql -U postgres -d prysma -f migration_02.sql
> ```

## 3. Configurar a conexão

Edite `app/includes/config.php` se o host/porta/usuário/senha do seu
PostgreSQL forem diferentes dos padrões abaixo:

```php
const DB_HOST = '127.0.0.1';
const DB_PORT = '5432';
const DB_NAME = 'prysma';
const DB_USER = 'prysma_app';
const DB_PASS = 'prysma_app';
```

## 4. Configurar o Sistema Bancário (Boleto Cloud sandbox)

O UC03 (Emitir 2ª Via de Boleto) do memorial define o **Sistema Bancário**
como ator secundário: o sistema pede a ele a geração do boleto e recebe de
volta código de barras e linha digitável. Isso é implementado de verdade em
`includes/boletocloud.php`, usando o ambiente **sandbox** (testes) da
**Boleto Cloud** (developers.boleto.cloud) — gratuito, sem precisar de CNPJ
nem convênio bancário real. Diferente de outras integrações, a API já
devolve o **PDF pronto do boleto na própria resposta**, que a aplicação
salva em `app/storage/boletos/` e serve pelo próprio site
(`locatario/boleto_pdf.php`) — sem depender de link externo.

1. Crie uma conta gratuita em **https://sandbox.boletocloud.com**.
2. No menu da sua conta, gere sua **API Key** (algo como `api-key_AbCd...`).
3. No menu **Conta**, cadastre uma conta bancária de teste (qualquer banco
   suportado; os dados de agência/conta podem ser fictícios, já que é
   ambiente de sandbox). Depois de criada, abra a conta, clique em
   **"Editar dados"** e depois em **"Gerar Token"** para obter o token
   dessa conta.
4. Abra `app/includes/boletocloud.php` e substitua:
   ```php
   const BC_API_KEY = 'COLOQUE_AQUI_SUA_API_KEY_DE_TESTE';
   const BC_CONTA_TOKEN = 'COLOQUE_AQUI_O_TOKEN_DA_SUA_CONTA';
   ```
   pelos valores copiados, mantendo as aspas.

Sem essa configuração, a tela de boletos continua funcionando, mas cai
sempre no fluxo alternativo 4a do UC03 ("O Sistema Bancário está
indisponível no momento") — o que já é, por si só, uma forma de demonstrar
esse fluxo alternativo para a banca, se for útil.

Com as credenciais configuradas, ao clicar em "Solicitar 2ª via" numa
cobrança em aberto, o sistema chama a API real da Boleto Cloud (sandbox),
salva o PDF retornado e grava no banco o código de barras e a linha
digitável (quando a conta de teste os disponibiliza), exatamente como
descrito no UC03. O link "Baixar PDF do boleto" abre o arquivo diretamente
pelo próprio PRYSMA.

> Nota: a integração com o Mercado Pago (arquivo `includes/mercadopago.php`,
> mantido no pacote apenas como referência) também foi testada e funciona
> de ponta a ponta, mas o link de PDF do boleto fica indisponível no
> ambiente de testes deles. A Boleto Cloud foi adotada por resolver esse
> ponto, já que devolve o arquivo pronto na resposta da API.

## 5. Popular dados de demonstração

Com o banco criado e a conexão configurada, rode (pode repetir sempre que
quiser reiniciar os dados — o script limpa e recria tudo):

```bash
cd app
php seed.php
```

Isso cria usuários de teste, imóveis, contratos, cobranças (uma paga, uma
pendente, uma atrasada — para testar todos os status), chamados de
manutenção em diferentes estágios e perguntas de FAQ.

## 6. Rodar o servidor local

Ainda dentro da pasta `app`:

```bash
php -S 127.0.0.1:8000
```

Acesse **http://127.0.0.1:8000** no navegador.

## 7. Contas de teste (senha para todas: `123456`)

| Perfil | Email | Observação |
|---|---|---|
| Locador | joao.locador@prysma.com | possui imóveis, repasses e informe de rendimento com dados |
| Locatário | maria.locataria@prysma.com | contrato ativo, cobrança paga e pendente, chamados |
| Locatário | carlos.locatario@prysma.com | contrato com cobrança **atrasada** (testa multa/juros) |
| Locatário (inativo) | fernanda.inativa@prysma.com | usado para testar o fluxo alternativo "usuário inativo" do login (UC01) |
| Administrador | admin@prysma.com | gerencia chamados, contratos, imóveis e FAQ |

## 8. Casos de uso implementados

- **UC01 — Autenticar-se**: `login.php` (fluxos alternativos: credenciais
  inválidas e usuário inativo).
- **UC02 — Consultar FAQ**: `locatario/faq.php`, `locador/faq.php`
  (administração em `admin/faq.php`).
- **UC03 — Emitir 2ª via de boleto**: `locatario/boletos.php`, com o
  Sistema Bancário implementado via Boleto Cloud sandbox
  (`includes/boletocloud.php` — ver seção 4). Cobre os fluxos alternativos
  2a (nenhuma cobrança em aberto) e 4a (falha de comunicação com o banco).
- **UC04 — Abrir chamado de manutenção**: `locatario/chamados.php`. Exibe o
  número de protocolo ao final (fluxo 6) e aplica o fluxo alternativo 5a
  (prioridade ALTA move o imóvel para `EM_MANUTENCAO` automaticamente).
- **UC05 — Acompanhar meus chamados**: `locatario/chamados.php` (lista/histórico) e
  `locatario/chamado_detalhes.php` (detalhe de um chamado: descrição completa,
  prestador designado com contato, e linha do tempo com abertura, atribuição
  de prestador, última atualização de status e encerramento).
- **UC06 — Consultar repasses**: `locador/repasses.php`, com filtro de
  período (data início/fim).
- **UC07 — Emitir informe de rendimento**: `locador/informe.php`
  (agrupado por ano e imóvel, com opção de impressão).
- **UC08 — Gerenciar chamados de manutenção**: `admin/chamados.php`
  (atribuição de prestador e mudança de status).

Além dos UCs, o administrador conta com telas de apoio: cadastro de imóveis
(`admin/imoveis.php`), contratos (`admin/contratos.php`), FAQ
(`admin/faq.php`) e **cobranças** (`admin/cobrancas.php`, onde o
administrador marca uma cobrança como paga — cobre o CT-09 do Plano de
Testes do memorial). O locador também tem uma consulta somente-leitura de
IPTU por imóvel (`locador/iptu.php`).

## 9. Regras de negócio no banco (triggers)

Já incluídas em `prysma_schema.sql` (ou em `migration_02.sql`, se você
aplicou sobre um banco já existente), funcionam automaticamente ao usar o
sistema (não é preciso replicar a lógica em PHP):

- Ativar um contrato marca o imóvel como `ALUGADO`; encerrar/rescindir volta
  para `DISPONIVEL`.
- Uma cobrança pendente com vencimento no passado é automaticamente marcada
  `ATRASADO`.
- O valor líquido de um repasse é calculado a partir da `taxaAdministracao`
  vigente no contrato correspondente.
- A data de atualização de um chamado de manutenção é preenchida automaticamente
  a cada alteração.
- Um chamado de manutenção aberto com prioridade `ALTA` move o imóvel
  automaticamente para `EM_MANUTENCAO` (UC04, fluxo 5a).

## 10. Dicas para a apresentação

- Rode `php seed.php` pouco antes da apresentação para garantir dados limpos
  e previsíveis (datas de vencimento relativas ao mês atual).
- Teste o login com `fernanda.inativa@prysma.com` para mostrar o tratamento
  de exceção do UC01 sem precisar improvisar.
- O usuário `carlos.locatario@prysma.com` já tem uma cobrança atrasada com
  multa/juros configurados, útil para mostrar a 2ª via de boleto com atraso.
- Todas as ações destrutivas (mudar status, excluir FAQ) usam token CSRF por
  sessão; se o servidor for reiniciado no meio dos testes, atualize a página
  antes de reenviar um formulário.
