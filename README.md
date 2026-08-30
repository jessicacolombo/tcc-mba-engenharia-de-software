# 📊 Avaliação Experimental: Amazon SQS Standard vs. FIFO

Este repositório contém a implementação da prova de conceito e o ambiente de execução utilizados no Trabalho de Conclusão de Curso do **MBA em Engenharia de Software da USP ESALQ**.

O objetivo deste README é servir como **modelo final de documentação dos resultados**, já estruturado para receber os valores consolidados após a execução dos testes com **1000, 10000 e 50000 eventos** em cada tipo de fila.

## 🎯 Objetivo da Pesquisa

Esta pesquisa aplicada investiga, de forma empírica, o comportamento e o desempenho dos modelos de fila **Standard** e **FIFO** do **Amazon Simple Queue Service (SQS)**. O foco está em três dimensões principais:

- **Latência** de enfileiramento e processamento.
- **Throughput** do pipeline produtor-consumidor.
- **Garantia de ordenação** entre mensagens publicadas e mensagens processadas.

O estudo busca evidenciar os trade-offs entre flexibilidade, consistência e previsibilidade de processamento em arquiteturas assíncronas baseadas em filas.

## 🧪 Desenho Experimental

Os experimentos serão executados em três cenários de carga para cada tipo de fila:

- **1.000 eventos**
- **10.000 eventos**
- **50.000 eventos**

Para cada cenário, serão coletadas métricas de envio, recebimento, latência e ordem de processamento, permitindo uma comparação direta entre os perfis Standard e FIFO.

### Métricas Avaliadas

- **Latência média** por mensagem.
- **Latência mínima** e **latência máxima**.
- **Throughput** agregado do sistema.
- **Percentual de inversões de ordem**.
- **Tempo total de execução** do experimento.
- **Identificador único do lote** para separar execuções posteriores.

## 📈 Resultados Finais

> **Observação:** esta seção foi estruturada como modelo final e será preenchida com os valores consolidados após a execução dos testes reais.

### 1.000 eventos

| Métrica                        |  Fila FIFO  | Fila Standard |
| :----------------------------- | :---------: | :-----------: |
| Throughput (mensagens/segundo) | A preencher |  A preencher  |
| Latência média (ms)            | A preencher |  A preencher  |
| Latência mínima (ms)           | A preencher |  A preencher  |
| Latência máxima (ms)           | A preencher |  A preencher  |
| Inversões de ordem             | A preencher |  A preencher  |
| Percentual de inversões        | A preencher |  A preencher  |

### 10.000 eventos

| Métrica                        |  Fila FIFO  | Fila Standard |
| :----------------------------- | :---------: | :-----------: |
| Throughput (mensagens/segundo) | A preencher |  A preencher  |
| Latência média (ms)            | A preencher |  A preencher  |
| Latência mínima (ms)           | A preencher |  A preencher  |
| Latência máxima (ms)           | A preencher |  A preencher  |
| Inversões de ordem             | A preencher |  A preencher  |
| Percentual de inversões        | A preencher |  A preencher  |

### 50.000 eventos

| Métrica                        |  Fila FIFO  | Fila Standard |
| :----------------------------- | :---------: | :-----------: |
| Throughput (mensagens/segundo) | A preencher |  A preencher  |
| Latência média (ms)            | A preencher |  A preencher  |
| Latência mínima (ms)           | A preencher |  A preencher  |
| Latência máxima (ms)           | A preencher |  A preencher  |
| Inversões de ordem             | A preencher |  A preencher  |
| Percentual de inversões        | A preencher |  A preencher  |

## 🔎 Análise Comparativa Esperada

Ao final dos testes, espera-se observar:

- Na **fila FIFO**, preservação integral da ordem de processamento.
- Na **fila Standard**, possibilidade de reordenação entre mensagens, especialmente em cenários com maior volume.
- Diferenças mais evidentes de tempo total e latência em volumes mais altos, sobretudo sob contenção de consumidor.

## 🛠️ Tecnologias Utilizadas

- **Linguagem:** PHP 8.3
- **Framework:** Laravel 12.x
- **Infraestrutura local:** Docker e Docker Compose
- **Fila em nuvem:** Amazon SQS
- **Banco de apoio:** MySQL 8.0
- **Observabilidade:** OpenTelemetry + Jaeger
- **Integração AWS:** AWS SDK for PHP

## 📦 Ambiente de Execução

O projeto foi preparado para execução em ambiente containerizado, com os seguintes serviços principais:

- Aplicação Laravel
- Banco MySQL
- Jaeger para visualização de traces

## ▶️ Como Executar os Testes

### 1. Preparar o ambiente

```bash
cp .env.example .env
docker compose up -d --build
```

### 2. Executar os testes por volume

#### Fila Standard

```bash
php artisan sqs:benchmark standard --total=1000
php artisan sqs:benchmark standard --total=10000
php artisan sqs:benchmark standard --total=50000
```

#### Fila FIFO

```bash
php artisan sqs:benchmark fifo --total=1000
php artisan sqs:benchmark fifo --total=10000
php artisan sqs:benchmark fifo --total=50000
```

### 3. Processar as mensagens

```bash
php artisan queue:work sqs --queue=tcc-fila-standard
php artisan queue:work sqs --queue=tcc-fila-fifo.fifo
```

### 4. Consultar os traces

Os traces podem ser visualizados no Jaeger em:

```text
http://localhost:16686/search
```

## 🧾 Estrutura dos Dados Coletados

Ao final de cada execução, recomenda-se consolidar os dados em tabelas separadas por volume e por tipo de fila, com os seguintes campos:

- Identificador do teste
- Identificador único do lote
- Tipo de fila
- Quantidade de eventos
- Tempo total de execução
- Throughput
- Latência média
- Latência mínima
- Latência máxima
- Quantidade de inversões
- Percentual de inversões

## 🧠 Interpretação Esperada dos Resultados

Este estudo parte da hipótese de que:

- A fila **FIFO** apresentará maior previsibilidade e ordenação, com possível custo adicional de desempenho em cenários de alta carga.
- A fila **Standard** apresentará maior flexibilidade operacional, porém com maior risco de desordem entre mensagens.

## 👩‍🎓 Autoria e Contexto Acadêmico

**Autora:** Jéssica Aparecida Colombo <br>
**Orientadora:** Prof.ª Mestra Daniele Aparecida Cicillini Pimenta <br>
**Instituição:** USP ESALQ - Pecege <br>
**Programa:** MBA em Engenharia de Software <br>
