# 📊 Avaliação Experimental: Amazon SQS Standard vs. FIFO

Este repositório contém o código-fonte e o ambiente de execução da Prova de Conceito (PoC) desenvolvida para o Trabalho de Conclusão de Curso do **MBA em Engenharia de Software pela USP ESALQ**.

## 🎯 Objetivo do Projeto
O objetivo desta pesquisa aplicada é avaliar e comparar empiricamente o desempenho (latência e *throughput*) e o comportamento (garantia de ordenação) dos modelos de filas **Standard** e **FIFO** do serviço **Amazon Simple Queue Service (SQS)**. O estudo visa fornecer parâmetros quantitativos claros sobre os *trade-offs* entre alta disponibilidade e consistência rigorosa em arquiteturas distribuídas assíncronas.

## 📈 Resumo dos Resultados Preliminares
A prova de conceito consistiu no envio e processamento de um lote de 100 mensagens para cada tipo de fila. Os dados coletados comprovaram empiricamente o alto índice de desordenamento da fila Standard contra a integridade sequencial perfeita da fila FIFO:

| Métrica | Fila FIFO | Fila Standard |
| :--- | :--- | :--- |
| **Garantia de Ordenação (Inversões)** | 0 (0,0%) | 97 (97,0%) |
| **Throughput (Vazão)** | 2,36 msg/s | 2,32 msg/s |
| **Latência Média** | 13.040,48 ms | 13.975,97 ms |
| **Latência Mínima** | 2.706,05 ms | 1.847,26 ms |

> **Nota Arquitetural:** Os tempos médios de latência na casa dos 13 segundos refletem o *queue wait time* (tempo de permanência na fila). Como o consumidor local operou de forma sequencial (*single-thread*) e a gravação ocorreu em um banco SQLite (que possui restrições de *file locking*), gerou-se um enfileiramento progressivo. Essa limitação da PoC justifica a evolução futura da pesquisa para um modelo de telemetria distribuída (OpenTelemetry).

## 🛠️ Tecnologias Utilizadas
* **Linguagem:** PHP 8.3 (CLI)
* **Framework:** Laravel 11.x
* **Infraestrutura/Nuvem:** Amazon Web Services (AWS SQS - região `us-east-1`)
* **Integração:** AWS SDK for PHP
* **Armazenamento Local:** SQLite
* **Ambiente:** Docker e Docker Compose

---

## Pré-requisitos
Para rodar este projeto localmente, você precisará ter instalado em sua máquina:
* [Docker](https://www.docker.com/get-started) e [Docker Compose](https://docs.docker.com/compose/install/)
* Git

**Configuração na AWS:**
Você precisará de uma conta AWS com credenciais (Access Key e Secret Key) com permissões para o SQS. É necessário criar duas filas previamente no console da AWS:
1. Uma fila do tipo **Standard**.
2. Uma fila do tipo **FIFO** (com a opção *Content-based deduplication* ativada).

---

## Como Executar o Projeto

**1. Clone o repositório**
```bash
git clone [https://github.com/seu-usuario/seu-repositorio.git](https://github.com/seu-usuario/seu-repositorio.git)
cd seu-repositorio
```

2. Configure as variáveis de ambiente
Faça uma cópia do arquivo de exemplo e insira suas credenciais da AWS e as URLs das filas criadas:

```bash
cp .env.example .env
```

No arquivo .env, preencha as seguintes chaves:

```
AWS_ACCESS_KEY_ID=sua_access_key
AWS_SECRET_ACCESS_KEY=sua_secret_key
AWS_DEFAULT_REGION=us-east-1
SQS_STANDARD_QUEUE_URL=[https://sqs.us-east-1.amazonaws.com/](https://sqs.us-east-1.amazonaws.com/)...
SQS_FIFO_QUEUE_URL=[https://sqs.us-east-1.amazonaws.com/](https://sqs.us-east-1.amazonaws.com/)...
```

3. Suba o ambiente Docker

```bash
docker build -t tcc-laravel-sqs .  
docker run -d --name tcc-laravel-sqs -v "$(pwd):/var/www" tcc-laravel-sqs
```

4. Instale as dependências e prepare o banco de dados
Acesse o contêiner da aplicação para instalar os pacotes do Composer e rodar as migrations do banco SQLite:

```bash
docker exec -it tcc-laravel-sqs bash      

## dentro do container docker 
composer install 
php artisan migrate
```

5. Executando os Experimentos
Para disparar as mensagens (Produtor), utilize o comando:

```bash
## dentro do container docker -> docker exec -it tcc-laravel-sqs bash 

php artisan sqs:benchmark standard --total=100
php artisan sqs:benchmark fifo --total=100

```

Para processar as mensagens (Consumidor/Worker) e gravar as métricas no banco de dados, inicie o worker do Laravel:

```bash
## dentro do container docker -> docker exec -it tcc-laravel-sqs bash 

php artisan queue:work sqs --queue=tcc-fila-standard
php artisan queue:work sqs --queue=tcc-fila-fifo.fifo
```

## Estrutura de Dados
Ao final do processamento, as métricas de tempo de envio, recebimento, latência em milissegundos e ordem de processamento estarão salvas no arquivo de banco de dados localizado em database/database.sqlite.

Autora: Jéssica Aparecida Colombo

Orientadora: Prof.ª Mestra Daniele Aparecida Cicillini Pimenta

Instituição: USP ESALQ - Pecege (MBA em Engenharia de Software)
