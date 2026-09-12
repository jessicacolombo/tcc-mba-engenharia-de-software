-- Consultas de consolidação dos experimentos SQS.
-- Fonte: queue_benchmark_runs, queue_metrics e queue_metric_attempts.
-- Todos os campos *_at / *_timestamp são epoch em segundos (microtime(true)).
--
-- As consultas seguem uma ordem lógica: primeiro valida-se a completude de
-- cada lote (1), depois calcula-se latência/throughput por lote (2) e o
-- consolidado médio por cenário (3) — esta última é a fonte da tabela de
-- resultados do README. Em seguida, o mesmo padrão se repete para ordenação
-- (4 e 5) e para tentativas/retries (6). As duas últimas consultas (7 e 8)
-- são de apoio, para inspecionar mensagens individuais.


-- 1) Completude de cada lote: compara o volume esperado (expected_messages)
-- com os registros efetivamente persistidos e processados em queue_metrics.
-- Um lote só é "completo" se o envio terminou (status = 'dispatched') e
-- todas as mensagens esperadas foram processadas.
SELECT
    r.batch_id,
    r.queue_type,
    r.expected_messages,
    r.status AS status_envio,
    COUNT(m.sequence_id) AS registros_metricas,
    SUM(m.status = 'processed') AS processadas,
    SUM(m.status = 'failed') AS falhas,
    SUM(m.status IN ('dispatched', 'retrying')) AS pendentes,
    CASE
        WHEN r.status = 'dispatched'
            AND COUNT(m.sequence_id) = r.expected_messages
            AND SUM(m.status = 'processed') = r.expected_messages
            THEN 'completo'
        ELSE 'incompleto'
        END AS situacao
FROM queue_benchmark_runs AS r
         LEFT JOIN queue_metrics AS m
                   ON m.batch_id = r.batch_id
                       AND m.queue_type = r.queue_type
GROUP BY r.batch_id, r.queue_type, r.expected_messages, r.status
ORDER BY r.started_at;


-- 2) Latência e throughput de cada lote finalizado.
-- O fim do lote é derivado do maior processed_at/failed_at entre suas
-- mensagens (finished_at foi removido de queue_benchmark_runs).
WITH fim_lote AS (
    SELECT
        batch_id,
        queue_type,
        SUM(status = 'processed') AS mensagens_processadas,
        ROUND(AVG(latency_ms), 2) AS latencia_media_ms,
        ROUND(MIN(latency_ms), 2) AS latencia_minima_ms,
        ROUND(MAX(latency_ms), 2) AS latencia_maxima_ms,
        MAX(COALESCE(processed_at, failed_at)) AS processamento_final_at
    FROM queue_metrics
    GROUP BY batch_id, queue_type
)
SELECT
    r.batch_id,
    r.queue_type,
    r.expected_messages,
    f.mensagens_processadas,
    f.latencia_media_ms,
    f.latencia_minima_ms,
    f.latencia_maxima_ms,
    ROUND(f.processamento_final_at - r.started_at, 3) AS tempo_total_s,
    ROUND(r.expected_messages / NULLIF(f.processamento_final_at - r.started_at, 0), 2)
                                                      AS throughput_total_msg_s,
    ROUND(f.mensagens_processadas / NULLIF(f.processamento_final_at - r.dispatched_at, 0), 2)
                                                      AS throughput_processamento_msg_s
FROM queue_benchmark_runs AS r
         JOIN fim_lote AS f
              ON f.batch_id = r.batch_id
                  AND f.queue_type = r.queue_type
WHERE r.status = 'dispatched'
ORDER BY r.queue_type, r.expected_messages, r.started_at;


-- 3) Consolidado por tipo de fila e volume: média entre todas as repetições
-- do mesmo cenário. Reaproveita a lógica da consulta 2 por lote.
-- Esta é a consulta que alimenta a tabela de resultados do README.
WITH fim_lote AS (
    SELECT
        batch_id,
        queue_type,
        SUM(status = 'processed') AS mensagens_processadas,
        AVG(latency_ms) AS latencia_media_ms,
        MIN(latency_ms) AS latencia_minima_ms,
        MAX(latency_ms) AS latencia_maxima_ms,
        MAX(COALESCE(processed_at, failed_at)) AS processamento_final_at
    FROM queue_metrics
    GROUP BY batch_id, queue_type
),
     por_lote AS (
         SELECT
             r.queue_type,
             r.expected_messages,
             f.latencia_media_ms,
             f.latencia_minima_ms,
             f.latencia_maxima_ms,
             f.processamento_final_at - r.started_at AS tempo_total_s,
             r.expected_messages / NULLIF(f.processamento_final_at - r.started_at, 0)
                                                     AS throughput_total_msg_s,
             f.mensagens_processadas / NULLIF(f.processamento_final_at - r.dispatched_at, 0)
                                                     AS throughput_processamento_msg_s
         FROM queue_benchmark_runs AS r
                  JOIN fim_lote AS f
                       ON f.batch_id = r.batch_id
                           AND f.queue_type = r.queue_type
         WHERE r.status = 'dispatched'
     )
SELECT
    queue_type,
    expected_messages,
    COUNT(*) AS lotes_avaliados,
    ROUND(AVG(latencia_media_ms), 2) AS latencia_media_ms,
    ROUND(AVG(latencia_minima_ms), 2) AS latencia_minima_ms,
    ROUND(AVG(latencia_maxima_ms), 2) AS latencia_maxima_ms,
    ROUND(AVG(tempo_total_s), 3) AS tempo_total_s_medio,
    ROUND(AVG(throughput_total_msg_s), 2) AS throughput_total_msg_s_medio,
    ROUND(AVG(throughput_processamento_msg_s), 2) AS throughput_processamento_msg_s_medio
FROM por_lote
GROUP BY queue_type, expected_messages
ORDER BY queue_type, expected_messages;


-- 4) Inversões de ordem por lote.
-- sequence_id é a ordem de publicação. Reconstrói-se a ordem de
-- processamento por processed_at (com sequence_id como desempate) e conta-se
-- cada par (a, b) em que a foi processada antes de b, mas
-- sequence_id(a) > sequence_id(b).
WITH ordenadas AS (
    SELECT
        batch_id,
        queue_type,
        sequence_id,
        ROW_NUMBER() OVER (
            PARTITION BY batch_id, queue_type
            ORDER BY processed_at, sequence_id
            ) AS ordem_processamento
    FROM queue_metrics
    WHERE status = 'processed'
),
     inversoes AS (
         SELECT
             a.batch_id,
             a.queue_type,
             COUNT(*) AS quantidade_inversoes
         FROM ordenadas AS a
                  JOIN ordenadas AS b
                       ON b.batch_id = a.batch_id
                           AND b.queue_type = a.queue_type
                           AND b.ordem_processamento > a.ordem_processamento
                           AND b.sequence_id < a.sequence_id
         GROUP BY a.batch_id, a.queue_type
     )
SELECT
    r.batch_id,
    r.queue_type,
    r.expected_messages,
    COALESCE(i.quantidade_inversoes, 0) AS inversoes,
    -- percentual sobre o total de pares possíveis: n * (n - 1) / 2.
    -- Não dividir apenas por n: uma mesma mensagem pode aparecer em várias
    -- inversões, então essa base é a que permite comparar volumes diferentes.
    ROUND(COALESCE(i.quantidade_inversoes, 0) * 100.0 /
          NULLIF(r.expected_messages * (r.expected_messages - 1) / 2, 0), 4)
                                        AS percentual_inversoes_sobre_pares
FROM queue_benchmark_runs AS r
         LEFT JOIN inversoes AS i
                   ON i.batch_id = r.batch_id
                       AND i.queue_type = r.queue_type
ORDER BY r.queue_type, r.expected_messages, r.started_at;


-- 5) Inversões de ordem consolidadas por tipo de fila e volume: média entre
-- as repetições do mesmo cenário. Reaproveita a lógica da consulta 4.
WITH ordenadas AS (
    SELECT
        batch_id,
        queue_type,
        sequence_id,
        ROW_NUMBER() OVER (
            PARTITION BY batch_id, queue_type
            ORDER BY processed_at, sequence_id
            ) AS ordem_processamento
    FROM queue_metrics
    WHERE status = 'processed'
),
     inversoes AS (
         SELECT
             a.batch_id,
             a.queue_type,
             COUNT(*) AS quantidade_inversoes
         FROM ordenadas AS a
                  JOIN ordenadas AS b
                       ON b.batch_id = a.batch_id
                           AND b.queue_type = a.queue_type
                           AND b.ordem_processamento > a.ordem_processamento
                           AND b.sequence_id < a.sequence_id
         GROUP BY a.batch_id, a.queue_type
     ),
     por_lote AS (
         SELECT
             r.queue_type,
             r.expected_messages,
             COALESCE(i.quantidade_inversoes, 0) AS inversoes,
             COALESCE(i.quantidade_inversoes, 0) * 100.0 /
             NULLIF(r.expected_messages * (r.expected_messages - 1) / 2, 0)
                                                 AS percentual_inversoes_sobre_pares
         FROM queue_benchmark_runs AS r
                  LEFT JOIN inversoes AS i
                            ON i.batch_id = r.batch_id
                                AND i.queue_type = r.queue_type
         WHERE r.status = 'dispatched'
     )
SELECT
    queue_type,
    expected_messages,
    COUNT(*) AS lotes_avaliados,
    ROUND(AVG(inversoes), 2) AS inversoes_media,
    ROUND(AVG(percentual_inversoes_sobre_pares), 4) AS percentual_inversoes_medio
FROM por_lote
GROUP BY queue_type, expected_messages
ORDER BY queue_type, expected_messages;


-- 6) Tentativas, retries e reentregas.
-- queue_metric_attempts tem uma linha por tentativa de entrega; em
-- queue_metrics, attempts > 1 indica retry e duplicate_count > 0 indica
-- reentrega após o processamento já ter sido concluído.
SELECT
    a.batch_id,
    a.queue_type,
    COUNT(*) AS total_tentativas,
    SUM(a.attempt > 1) AS tentativas_de_retry,
    SUM(a.status = 'failed') AS tentativas_com_falha,
    SUM(m.attempts > 1) AS mensagens_com_retry,
    SUM(m.duplicate_count) AS total_reentregas_apos_processamento
FROM queue_metric_attempts AS a
         JOIN queue_metrics AS m
              ON m.batch_id = a.batch_id
                  AND m.queue_type = a.queue_type
                  AND m.sequence_id = a.sequence_id
GROUP BY a.batch_id, a.queue_type
ORDER BY a.queue_type, a.batch_id;


-- 7) Detalhamento de cada mensagem (uso pontual, para depuração).
SELECT
    batch_id,
    queue_type,
    sequence_id,
    sqs_message_id,
    status,
    attempts,
    processing_count,
    duplicate_count,
    ROUND((received_timestamp - sent_timestamp) * 1000, 2) AS latencia_calculada_ms,
    ROUND(latency_ms, 2) AS latencia_persistida_ms,
    sent_timestamp,
    received_timestamp,
    processed_at,
    failed_at,
    error_message
FROM queue_metrics
ORDER BY batch_id, queue_type, sequence_id;


-- 8) Apenas mensagens com retry, reentrega ou falha (uso pontual).
SELECT
    batch_id,
    queue_type,
    sequence_id,
    status,
    attempts,
    processing_count,
    duplicate_count,
    error_message
FROM queue_metrics
WHERE attempts > 1
   OR processing_count > 1
   OR duplicate_count > 0
   OR status = 'failed'
ORDER BY batch_id, queue_type, sequence_id;
