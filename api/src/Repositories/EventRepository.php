<?php

namespace App\Repositories;

use App\Database\Connection;
use PDO;

class EventRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Connection::getInstance();
    }

    public function create(string $emailId, string $event, ?string $workerId = null, array $payload = []): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO queue_events (email_id, event, worker_id, payload, created_at)
            VALUES (:email_id, :event, :worker_id, :payload, NOW())
        ");

        $stmt->execute([
            'email_id'  => $emailId,
            'event'     => $event,
            'worker_id' => $workerId,
            'payload'   => json_encode($payload),
        ]);
    }

    public function latest(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT qe.*, e.recipient, e.subject
            FROM queue_events qe
            JOIN emails e ON e.id = qe.email_id
            ORDER BY qe.created_at DESC
            LIMIT :limit
        ");
        $stmt->execute(['limit' => $limit]);
        return $stmt->fetchAll();
    }
}
