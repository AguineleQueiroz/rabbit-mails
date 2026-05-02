<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class EmailRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function create(array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO emails (recipient, subject, body, status, queued_at, created_at, updated_at)
            VALUES (:recipient, :subject, :body, 'pending', NOW(), NOW(), NOW())
            RETURNING *
        ");

        $stmt->execute([
            'recipient' => $data['recipient'],
            'subject'   => $data['subject'],
            'body'      => $data['body'],
        ]);

        return $stmt->fetch();
    }

    public function updateStatus(string $id, string $status, ?string $error = null): void
    {
        /*
         * The `processed_at` attribute is only populated in terminal states.
         * The condition is resolved in PHP to avoid the double use of :status in the same query,
         * which generates a type conflict in PostgreSQL (text vs varchar).
         * */
        $processedAt = in_array($status, ['sent', 'failed', 'dead'], true)
            ? 'NOW()'
            : 'processed_at';

        $stmt = $this->db->prepare("
            UPDATE emails
            SET status        = :status,
                error_message = :error,
                attempts      = attempts + 1,
                processed_at  = $processedAt,
                updated_at    = NOW()
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id, 'status' => $status, 'error' => $error]);
    }

    public function findById(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM emails WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function paginate(string $status = '', int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $where  = $status ? "WHERE status = :status" : "";

        $stmt = $this->db->prepare("
            SELECT * FROM emails $where
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset
        ");

        $params = ['limit' => $perPage, 'offset' => $offset];
        if ($status) {
            $params['status'] = $status;
        }

        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM emails $where");
        $countStmt->execute($status ? ['status' => $status] : []);
        $total = (int)$countStmt->fetchColumn();

        return [
            'data'        => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    public function resetForRequeue(string $id): void
    {
        $stmt = $this->db->prepare("
            UPDATE emails
            SET status        = 'pending',
                attempts      = 0,
                error_message = NULL,
                updated_at    = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
    }

    public function getStats(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) FILTER (WHERE status = 'queued')                                          AS queued,
                COUNT(*) FILTER (WHERE status = 'processing')                                      AS processing,
                COUNT(*) FILTER (WHERE status = 'sent'   AND created_at >= NOW() - INTERVAL '1 day') AS sent_today,
                COUNT(*) FILTER (WHERE status = 'failed' AND created_at >= NOW() - INTERVAL '1 day') AS failed_today,
                COUNT(*) FILTER (WHERE status = 'dead')                                            AS dead,
                COUNT(*)                                                                            AS total
            FROM emails
        ");

        return $stmt->fetch();
    }

    public function getChartData(): array
    {
        $stmt = $this->db->query("
            SELECT
                DATE_TRUNC('hour', processed_at)              AS hour,
                COUNT(*) FILTER (WHERE status = 'sent')       AS sent,
                COUNT(*) FILTER (WHERE status = 'failed')     AS failed
            FROM emails
            WHERE processed_at >= NOW() - INTERVAL '24 hours'
            GROUP BY hour
            ORDER BY hour
        ");

        return $stmt->fetchAll();
    }
}
