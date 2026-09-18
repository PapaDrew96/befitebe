<?php

declare(strict_types=1);

namespace Befit\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public function findByLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users WHERE email = :email OR phone = :phone LIMIT 1'
        );
        $stmt->execute(['email' => $login, 'phone' => $login]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO users
                (role, first_name, last_name, email, phone, password_hash, status, must_change_password)
             VALUES
                (:role, :first_name, :last_name, :email, :phone, :password_hash, :status, :must_change_password)'
        );
        $stmt->execute($data);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $allowed = ['role', 'first_name', 'last_name', 'email', 'phone', 'status', 'must_change_password'];
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

        $stmt = $this->pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $stmt->execute($params);
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = :password_hash WHERE id = :id');
        $stmt->execute(['password_hash' => $passwordHash, 'id' => $id]);
    }


    public function setMustChangePassword(int $id, bool $value): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET must_change_password = :value WHERE id = :id');
        $stmt->execute(['value' => $value ? 1 : 0, 'id' => $id]);
    }

    public function updateLastLogin(int $id, string $dateTime): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = :last_login_at WHERE id = :id');
        $stmt->execute(['last_login_at' => $dateTime, 'id' => $id]);
    }

    public function paginate(array $filters, int $page, int $perPage): array
    {
        $where = [];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR phone LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }
        if (!empty($filters['role'])) {
            $where[] = 'role = :role';
            $params['role'] = $filters['role'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM users' . $whereSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT * FROM users' . $whereSql . ' ORDER BY last_name, first_name LIMIT :limit OFFSET :offset'
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['items' => $stmt->fetchAll(), 'total' => $total];
    }
}
