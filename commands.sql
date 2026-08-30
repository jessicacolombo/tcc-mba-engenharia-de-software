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
