# Sistema de Monitoramento de Linhas de Produção

Sistema para monitorar e distribuir automaticamente as linhas de produção em operação entre os monitores Raspberry Pi.

## Funcionalidades

-  **Monitoramento de Status**: Mostra se cada Raspberry e TV estão ligados/desligados
-  **Distribuição Automática**: Distribui linhas rodando em pares entre os rasps, sempre em ordem crescente
-  **Atualização Dinâmica**: Quando uma linha liga/desliga, a distribuição se reorganiza automaticamente
-  **Redirect Automático**: Cada rasp acessa uma URL fixa e é redirecionado para o conteúdo correto

## Configuração Inicial

### 1. Configure o Banco de Dados

Edite o arquivo `config.php` e preencha as credenciais do PostgreSQL:

```php
$db_config = [
    'host' => 'seu_host',
    'dbname' => 'seu_banco',
    'user' => 'seu_usuario',
    'password' => 'sua_senha',
    'port' => 5432,
    'schema' => 'public'   // schema da tabela
];
```

### 2. Configure os Raspberries

O arquivo `rasps.json` deve conter a lista de seus raspberries:

```json
{
    "rasps": [
        {
            "id": "01",
            "name": "Raspmon1",
            "ip": "000.000.0.000",
            "local": "Fundo da Fabrica"
        }
    ]
}
```

### 3. Configure cada Raspberry para acessar

Cada Raspberry deve abrir no navegador (Chromium em modo kiosk):

```
.../rasp.php?id=01
```

Ou configure para identificar automaticamente por IP (o sistema tenta fazer isso se não vier `?id=`).

## Estrutura de Arquivos

### Arquivos Principais

- **`config.php`** - Configurações do banco de dados
- **`funcoes.php`** - Funções compartilhadas (buscar linhas, distribuir, montar URLs)
- **`rasp.php`** - Endpoint que cada rasp acessa (faz redirect dinâmico)
- **`painel.php`** - Painel visual para monitoramento (você acessa pelo navegador)
- **`api.php`** - API JSON com status dos rasps e distribuição
- **`update_status.php`** - Endpoint para rasp enviar heartbeat (status rasp/TV)
- **`json_store.php`** - Funções seguras para ler/gravar JSON
- **`rasps.json`** - Arquivo com dados dos raspberries
- **`teste_banco.php`** - Teste de conexão e query (abrir no navegador para diagnóstico)

## Como Funciona

### Fluxo de Distribuição

1. O sistema consulta o banco para encontrar linhas rodando (últimas 12 horas)
2. Ordena as linhas por número (ASC)
3. Distribui em pares:
   - Rasp1: linhas 1 e 2
   - Rasp2: linhas 3 e 4
   - Rasp3: linhas 5 e 6
   - etc.
4. Quando uma linha nova liga, ela entra na ordem e empurra as outras

### Exemplo Prático

**Momento 1**: Linhas rodando: `E07, E09, E12, E18`

```
Rasp1: E07 e E09
Rasp2: E12 e E18
```

**Momento 2**: Linha `E08` liga → Linhas: `E07, E08, E09, E12, E18`

```
Rasp1: E07 e E08  ← E08 entrou ao lado da E07
Rasp2: E09 e E12  ← E09 foi empurrada
Rasp3: E18        ← E12 foi empurrada
```

### Monitoramento de Status

Cada Raspberry envia periodicamente (via `update_status.php`):
- `status_rasp`: se o rasp está ligado
- `status_tv`: se a TV está ligada
- `last_seen`: timestamp da última comunicação

O sistema considera offline se não receber comunicação em 30 segundos.

## URLs

- **Painel Visual**: `.../painel.php`
- **API JSON**: `.../api.php`
- **Raspberry 01**: `.../rasp.php?id=01`
- **Raspberry 02**: `.../rasp.php?id=02`
- etc.

## Query do Banco

O sistema usa esta query para buscar linhas ativas:

```sql
WITH linhas_ativas AS (
    SELECT
        linha,
        espec,
        MAX(measurement_created) AS ultima_medicao
    FROM bda
    WHERE linha IS NOT NULL
    GROUP BY linha, espec
    HAVING MAX(measurement_created) >= NOW() - INTERVAL '12 hours'
)
SELECT linha, espec, ultima_medicao
FROM linhas_ativas 
ORDER BY linha ASC
```

## Manutenção

- O painel atualiza automaticamente a cada 30 segundos
- Os raspberries devem recarregar a página periodicamente (ou usar auto-refresh)
- O arquivo `rasps.json` é atualizado automaticamente quando os rasps enviam status

## Notas

- O sistema mantém compatibilidade com o sistema antigo de monitoramento de status
- A distribuição é sempre dinâmica baseada no banco de dados
- Se não houver linhas rodando para um rasp, ele mostra uma página de "aguardando"


### AVISO

- Os dados e nomes de bancos e urls são artificiais, para evitar vazamentos de dados
- Se for usufruir do sistema, deveria colocar os dados do seu banco para funcionar corretamente.


## Autor do Sistema

- Thiogo Antonio Gambeta
