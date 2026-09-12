# 📊 Avaliação Experimental: Amazon SQS Standard vs. FIFO

Prova de conceito e ambiente de execução do Trabalho de Conclusão de Curso do
**MBA em Engenharia de Software da USP ESALQ**. Este README documenta a
metodologia e os resultados consolidados dos testes com **1.000, 10.000 e
50.000 eventos** em cada tipo de fila.

## 🎯 Objetivo

Investigar, de forma empírica, o comportamento das filas **Standard** e
**FIFO** do Amazon SQS em três dimensões:

- **Latência** de enfileiramento e processamento.
- **Throughput** do pipeline produtor-consumidor.
- **Garantia de ordenação** entre mensagens publicadas e processadas.

## 🧪 Desenho Experimental

Cada tipo de fila é testado com **1.000, 10.000 e 50.000 eventos**. Em cada
cenário são coletadas métricas de envio, recebimento, latência e ordem de
processamento, comparando Standard e FIFO sob o mesmo volume.

**Métricas avaliadas:** latência média/mínima/máxima, throughput agregado,
percentual de inversões de ordem, tempo total de execução e `batch_id` como
identificador único de cada lote.

## 📈 Resultados

> Preencha esta seção com os valores retornados pela consulta 3 de
> `commands.sql` após cada rodada.

### 1.000 eventos

| Métrica                        |  Fila FIFO  | Fila Standard |
| :------------------------------ | :---------: | :-----------: |
| Throughput (mensagens/segundo)  |     2.2     |     6.12      |
| Latência média (ms)             |  180223.73  |    41779.13   |
| Latência mínima (ms)            |   1234.25   |     484.65    |
| Latência máxima (ms)            |  373849.42  |    83174.33   |
| Quebras de ordem                |     0.00    |     640.80    |
| Percentual de quebras           |    0.0000   |    64.1442    |

### 10.000 eventos

| Métrica                        |   Fila FIFO   | Fila Standard |
| :------------------------------ | :-----------: | :-----------: |
| Throughput (mensagens/segundo)  |      2.11     |     6.13      |
| Latência média (ms)             |  1535220.50   |    41460.60   |
| Latência mínima (ms)            |   80918.56    |     165.31    |
| Latência máxima (ms)            |  3176003.21   |    86586.85   |
| Quebras de ordem                |      0.00     |    5773.60    |
| Percentual de quebras           |     0.0000    |    57.7418    |

### 50.000 eventos

| Métrica                        |   Fila FIFO   | Fila Standard |
| :------------------------------ | :-----------: | :-----------: |
| Throughput (mensagens/segundo)  |      2.06     |     6.11      |
| Latência média (ms)             |  7198322.24   |    41517.09   |
| Latência mínima (ms)            |    1691.68    |     153.02    |
| Latência máxima (ms)            | 15707214.53   |   103171.21   |
| Quebras de ordem                |      0.00     |   28287.40    |
| Percentual de quebras           |     0.0000    |    56.5759    |

## 🔎 Critérios de Análise

- **FIFO**: preservação integral da ordem de processamento.
- **Standard**: possibilidade de reordenação, mais evidente em volumes altos.
- Diferenças de tempo total e latência tendem a crescer com o volume,
  sobretudo sob contenção de consumidor.

## 🧠 Hipótese

- **FIFO**: maior previsibilidade e ordenação, com possível custo de
  desempenho em alta carga.
- **Standard**: maior flexibilidade operacional, com maior risco de desordem.

## 🛠️ Tecnologias

- PHP 8.3 · Laravel 12.x
- Docker e Docker Compose
- Amazon SQS · MySQL 8.0
- OpenTelemetry + Jaeger
- AWS SDK for PHP

## ▶️ Como Executar

### 1. Preparar o ambiente

```bash
cp .env.example .env
docker compose up -d --build
```

### 2. Disparar os lotes

Execute **um tipo de fila por vez** e aguarde o processamento completo do
lote (confirme pelo `batch_id`, consulta 1 de `commands.sql`) antes de
iniciar o próximo. Isso evita que as duas filas concorram pelos mesmos
workers durante a mesma medição.

```bash
php artisan sqs:benchmark standard --total=1000
php artisan sqs:benchmark standard --total=10000
php artisan sqs:benchmark standard --total=50000
php artisan sqs:benchmark fifo --total=1000
php artisan sqs:benchmark fifo --total=10000
php artisan sqs:benchmark fifo --total=50000
```

Repita o protocolo variando a ordem dos volumes entre rodadas (para reduzir
viés de posição) e execute pelo menos três repetições por cenário — a
consulta 3 de `commands.sql` já consolida a média entre lotes.

### 3. Processamento pelo worker

O Supervisor mantém **5 processos** do worker (`laravel-worker.conf`),
escutando `tcc-fila-standard` e `tcc-fila-fifo.fifo`, com `--sleep=3`,
`--tries=3` e `--max-time=3600`. Logs em `storage/logs/worker.log`.

Não inicie `queue:work` adicionais — isso alteraria o número de
consumidores do experimento.

As métricas **não** são gravadas pelos 5 workers de benchmark: o job publica
um evento na conexão `database`/fila `metrics`, e um worker dedicado
(`laravel-metrics-worker`, `numprocs=1`) persiste esse evento em
`queue_metrics` e `queue_metric_attempts`. Esse worker não entra no cálculo
de throughput das filas Standard/FIFO.

### 4. Consultar os traces

```text
http://localhost:16686/search
```

## 🧾 Modelo de Dados

| Tabela                   | Conteúdo                                                             |
| ------------------------ | --------------------------------------------------------------------- |
| `queue_benchmark_runs`   | Um registro por lote: volume esperado, status do envio, timestamps.  |
| `queue_metrics`          | Um registro por mensagem: status final, latência, tentativas, reentregas. |
| `queue_metric_attempts`  | Um registro por tentativa de entrega de cada mensagem.                |

**Status de `queue_metrics`:** toda mensagem começa em `dispatched`; termina
em `processed` ou `failed`; `retrying` marca uma tentativa intermediária que
será reenviada. Mensagens ainda em `dispatched`/`retrying` após o fim da
janela de coleta são consideradas não processadas. `failed_jobs` (Laravel)
é a fonte complementar para falhas que esgotaram as tentativas configuradas.

**Cálculo das métricas** (todos os timestamps em segundos, via
`microtime(true)`):

- `latency_ms = (received_timestamp - sent_timestamp) * 1000` — tempo entre
  a criação do job e o início do processamento (inclui despacho, espera na
  SQS e serialização FIFO; não é o tempo de execução do job).
- Fim do lote = `MAX(COALESCE(processed_at, failed_at))` das mensagens
  (`queue_benchmark_runs` não guarda mais esse dado).
- `throughput_total_msg_s = expected_messages / (fim_lote - started_at)`.
- Inversão de ordem = par de mensagens em que a de menor `sequence_id` foi
  processada depois da de maior `sequence_id`; percentual calculado sobre o
  total de pares possíveis (`n * (n - 1) / 2`), não sobre `n`, já que uma
  mensagem pode participar de várias inversões.

## 📐 Consultas de Consolidação

Todas as consultas usadas para gerar os resultados estão em `commands.sql`,
em ordem lógica: completude do lote → latência/throughput por lote →
consolidado médio por cenário → inversões por lote → inversões consolidadas
→ tentativas/retries → detalhamento e mensagens problemáticas (apoio).

## 👩‍🎓 Autoria

**Autora:** Jéssica Aparecida Colombo </br>
**Orientador:** Prof. Me. Marcelo Pereira da Silva </br>
**Instituição:** USP ESALQ – Pecege </br>
**Programa:** MBA em Engenharia de Software
