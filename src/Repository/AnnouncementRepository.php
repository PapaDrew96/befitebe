<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class AnnouncementRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function active(string $now): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM announcements a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.is_active = 1
               AND a.starts_at <= :now_start
               AND (a.expires_at IS NULL OR a.expires_at >= :now_end)
             ORDER BY a.starts_at DESC, a.id DESC"
        );
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        return $stmt->fetchAll();
    }

    public function all(): array
    {
        return $this->pdo->query(
            "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS author_name
             FROM announcements a
             LEFT JOIN users u ON u.id = a.created_by
             ORDER BY a.starts_at DESC, a.id DESC"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM announcements WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO announcements (title, body, starts_at, expires_at, is_active, created_by)
             VALUES (:title, :body, :starts_at, :expires_at, :is_active, :created_by)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['title', 'body', 'starts_at', 'expires_at', 'is_active'];
        $sets = [];
        $params = ['id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if ($sets === []) {
            return;
        }

        $stmt = $this->pdo->prepare('UPDATE announcements SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM announcements WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
