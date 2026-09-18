<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class ScheduleRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function templates(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM schedule_templates';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY weekday, start_time';
        return $this->pdo->query($sql)->fetchAll();
    }

    public function findTemplate(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM schedule_templates WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createTemplate(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schedule_templates (weekday, start_time, end_time, capacity, is_active)
             VALUES (:weekday, :start_time, :end_time, :capacity, :is_active)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateTemplate(int $id, array $data): void
    {
        $this->updateWhitelisted('schedule_templates', $id, $data, [
            'weekday', 'start_time', 'end_time', 'capacity', 'is_active'
        ]);
    }

    public function deactivateTemplate(int $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE schedule_templates SET is_active = 0 WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function createFromTemplateIfMissing(array $template, string $date): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT IGNORE INTO gym_sessions
                (template_id, session_date, start_time, end_time, capacity, status)
             VALUES
                (:template_id, :session_date, :start_time, :end_time, :capacity, 'open')"
        );
        $stmt->execute([
            'template_id' => $template['id'],
            'session_date' => $date,
            'start_time' => $template['start_time'],
            'end_time' => $template['end_time'],
            'capacity' => $template['capacity'],
        ]);
    }

    public function sessionsBetween(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.*, t.weekday AS template_weekday
             FROM gym_sessions s
             LEFT JOIN schedule_templates t ON t.id = s.template_id
             WHERE s.session_date BETWEEN :from_date AND :to_date
             ORDER BY s.session_date, s.start_time'
        );
        $stmt->execute(['from_date' => $from, 'to_date' => $to]);
        return $stmt->fetchAll();
    }

    public function findSession(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gym_sessions WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findSessionForUpdate(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM gym_sessions WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createManualSession(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO gym_sessions
                (template_id, session_date, start_time, end_time, capacity, status, note, created_by)
             VALUES
                (NULL, :session_date, :start_time, :end_time, :capacity, :status, :note, :created_by)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateSession(int $id, array $data): void
    {
        $this->updateWhitelisted('gym_sessions', $id, $data, [
            'session_date', 'start_time', 'end_time', 'capacity', 'status', 'note'
        ]);
    }

    public function closuresBetween(string $from, string $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM schedule_closures
             WHERE closure_date BETWEEN :from_date AND :to_date
             ORDER BY closure_date'
        );
        $stmt->execute(['from_date' => $from, 'to_date' => $to]);
        return $stmt->fetchAll();
    }

    public function findClosureByDate(string $date): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM schedule_closures WHERE closure_date = :closure_date LIMIT 1'
        );
        $stmt->execute(['closure_date' => $date]);
        return $stmt->fetch() ?: null;
    }

    public function findClosure(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM schedule_closures WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function createClosure(string $date, ?string $reason, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO schedule_closures (closure_date, reason, created_by)
             VALUES (:closure_date, :reason, :created_by)'
        );
        $stmt->execute([
            'closure_date' => $date,
            'reason' => $reason,
            'created_by' => $actorUserId,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function deleteClosure(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM schedule_closures WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function updateWhitelisted(string $table, int $id, array $data, array $allowed): void
    {
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

        $sql = "UPDATE {$table} SET " . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}
