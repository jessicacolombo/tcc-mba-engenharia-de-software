SELECT
    batch_id,
    queue_type,
    COUNT(*) as total_mensagens,
    AVG(latency_ms) as latencia_media_ms,
    MIN(latency_ms) as latencia_minima_ms,
    MAX(latency_ms) as latencia_maxima_ms
FROM queue_metrics
WHERE latency_ms IS NOT NULL
GROUP BY batch_id, queue_type
ORDER BY batch_id, queue_type;


-- Resumo do ciclo de vida por lote
SELECT
    runs.batch_id,
    runs.queue_type,
    runs.expected_messages AS mensagens_enviadas,
    COALESCE(SUM(metrics.status = 'processed'), 0) AS mensagens_processadas,
    runs.expected_messages - COUNT(metrics.sequence_id) AS mensagens_pendentes,
    COALESCE(SUM(metrics.status = 'failed'), 0) AS mensagens_com_falha,
    COALESCE(SUM(GREATEST(metrics.attempts - 1, 0)), 0) AS retries,
    COALESCE(SUM(metrics.duplicate_count), 0) AS reentregas_apos_processamento,
    runs.started_at AS inicio_envio,
    MAX(COALESCE(metrics.processed_at, metrics.failed_at, metrics.received_timestamp, runs.dispatched_at, runs.started_at)) AS fim_processamento,
    MAX(COALESCE(metrics.processed_at, metrics.failed_at, metrics.received_timestamp, runs.dispatched_at, runs.started_at)) - runs.started_at AS tempo_total_segundos
FROM queue_benchmark_runs AS runs
LEFT JOIN queue_metrics AS metrics
    ON metrics.batch_id = runs.batch_id
    AND metrics.queue_type = runs.queue_type
GROUP BY runs.batch_id, runs.queue_type, runs.expected_messages, runs.started_at
ORDER BY runs.batch_id, runs.queue_type;


-- Lotes cujo envio ainda não terminou
SELECT
    batch_id,
    queue_type,
    expected_messages,
    status,
    started_at,
    dispatched_at
FROM queue_benchmark_runs
WHERE status <> 'dispatched';


-- Detalhamento de todas as tentativas, incluindo retries e falhas
SELECT
    batch_id,
    queue_type,
    sequence_id,
    attempt,
    status,
    started_at,
    finished_at,
    error_message
FROM queue_metric_attempts
ORDER BY batch_id, queue_type, sequence_id, attempt;


-- Mensagens processadas mais de uma vez
SELECT
    batch_id,
    queue_type,
    sequence_id,
    processing_count,
    duplicate_count,
    attempts
FROM queue_metrics
WHERE duplicate_count > 0
ORDER BY batch_id, queue_type, sequence_id;


SELECT
    batch_id,
    queue_type,
    sequence_id,
    sqs_message_id,
    created_at
FROM queue_metrics
ORDER BY batch_id, queue_type, created_at ASC;



SELECT *
FROM queue_metrics
ORDER BY batch_id, created_at;

-- Query: Detectar sequence_id quebrados (próximo != atual + 1) na ordem de recebimento
SELECT
    batch_id,
    queue_type,
    COUNT(*) as total_registros_com_gap,
    ROUND(COUNT(*) * 100.0 / MAX(total_recebidas), 2) as percentual_com_gap
FROM (
    SELECT
        batch_id,
        queue_type,
        sequence_id,
        LEAD(sequence_id) OVER (
            PARTITION BY batch_id, queue_type
            ORDER BY created_at
        ) as proximo_sequence_id,
        COUNT(*) OVER (PARTITION BY batch_id, queue_type) as total_recebidas,
        CASE
            WHEN LEAD(sequence_id) OVER (
                PARTITION BY batch_id, queue_type
                ORDER BY created_at
            ) IS NOT NULL
            AND LEAD(sequence_id) OVER (
                PARTITION BY batch_id, queue_type
                ORDER BY created_at
            ) != sequence_id + 1
            THEN 1
            ELSE 0
        END as tem_gap
    FROM queue_metrics
) gaps
WHERE tem_gap = 1
GROUP BY batch_id, queue_type
ORDER BY batch_id, queue_type;
