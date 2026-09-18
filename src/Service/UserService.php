<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\TokenRepository;
use Befit\Repository\UserRepository;
use Befit\Support\InputValidator;
use Befit\Support\UserPresenter;
use PDOException;

final class UserService
{
    public function __construct(
        private readonly Database $database,
        private readonly UserRepository $users,
        private readonly TokenRepository $tokens,
        private readonly ActivityLogRepository $activity
    ) {
    }

    public function get(int $id): array
    {
        $user = $this->users->findById($id);
        if (!$user) {
            throw ApiException::notFound('User not found.');
        }
        return UserPresenter::one($user);
    }

    public function paginate(array $query): array
    {
        $page = max(1, (int) ($query['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($query['per_page'] ?? 25)));

        $filters = [
            'search' => trim((string) ($query['search'] ?? '')),
            'role' => $query['role'] ?? null,
            'status' => $query['status'] ?? null,
        ];

        if ($filters['role'] && !in_array($filters['role'], ['member', 'admin'], true)) {
            throw ApiException::validation(['role' => ['Invalid role.']]);
        }
        if ($filters['status'] && !in_array($filters['status'], ['active', 'inactive'], true)) {
            throw ApiException::validation(['status' => ['Invalid status.']]);
        }

        $result = $this->users->paginate($filters, $page, $perPage);

        return [
            'items' => array_map([UserPresenter::class, 'one'], $result['items']),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'pages' => (int) ceil($result['total'] / $perPage),
            ],
        ];
    }

    public function create(array $input, int $actorUserId): array
    {
        $validator = new InputValidator($input);
        $validator
            ->requiredString('first_name', 1, 100)
            ->requiredString('last_name', 1, 100)
            ->optionalEmail('email')
            ->optionalString('phone', 3, 40)
            ->requiredString('password', 8, 255)
            ->oneOf('role', ['member', 'admin'])
            ->oneOf('status', ['active', 'inactive'])
            ->optionalBool('must_change_password');

        if (empty($input['email']) && empty($input['phone'])) {
            $validator->addError('email', 'Either email or phone is required.');
            $validator->addError('phone', 'Either phone or email is required.');
        }
        $validator->throwIfInvalid();

        $data = [
            'role' => $input['role'] ?? 'member',
            'first_name' => trim($input['first_name']),
            'last_name' => trim($input['last_name']),
            'email' => $this->nullableTrim($input['email'] ?? null),
            'phone' => $this->nullableTrim($input['phone'] ?? null),
            'password_hash' => password_hash($input['password'], PASSWORD_DEFAULT),
            'status' => $input['status'] ?? 'active',
            'must_change_password' => array_key_exists('must_change_password', $input) ? (filter_var($input['must_change_password'], FILTER_VALIDATE_BOOL) ? 1 : 0) : 1,
        ];

        try {
            $id = $this->database->transaction(function () use ($data, $actorUserId): int {
                $id = $this->users->create($data);
                $this->activity->log(
                    $actorUserId,
                    'user.create',
                    'user',
                    $id,
                    ['role' => $data['role']]
                );
                return $id;
            });
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'A user with that email or phone already exists.'
                );
            }
            throw $exception;
        }

        return $this->get($id);
    }

    public function updateAdmin(int $id, array $input, int $actorUserId): array
    {
        $existing = $this->users->findById($id);
        if (!$existing) {
            throw ApiException::notFound('User not found.');
        }

        $validator = new InputValidator($input);
        $validator
            ->optionalString('first_name', 1, 100, false)
            ->optionalString('last_name', 1, 100, false)
            ->optionalEmail('email')
            ->optionalString('phone', 3, 40)
            ->oneOf('role', ['member', 'admin'])
            ->oneOf('status', ['active', 'inactive'])
            ->optionalString('password', 8, 255)
            ->optionalBool('must_change_password');
        $validator->throwIfInvalid();

        if (
            $id === $actorUserId
            && (
                ($input['status'] ?? null) === 'inactive'
                || ($input['role'] ?? null) === 'member'
            )
        ) {
            throw ApiException::conflict(
                'You cannot deactivate or demote your own administrator account.'
            );
        }

        $data = $this->normalizeUserUpdate($input);

        $nextEmail = array_key_exists('email', $data) ? $data['email'] : $existing['email'];
        $nextPhone = array_key_exists('phone', $data) ? $data['phone'] : $existing['phone'];
        if (!$nextEmail && !$nextPhone) {
            throw ApiException::validation([
                'contact' => ['At least one of email or phone must remain available.']
            ]);
        }

        try {
            $this->database->transaction(function () use (
                $id,
                $data,
                $input,
                $actorUserId,
                $existing
            ): void {
                $this->users->update($id, $data);

                if (isset($input['password']) && $input['password'] !== '') {
                    $this->users->updatePassword(
                        $id,
                        password_hash($input['password'], PASSWORD_DEFAULT)
                    );
                    $mustChange = array_key_exists('must_change_password', $input)
                        ? filter_var($input['must_change_password'], FILTER_VALIDATE_BOOL)
                        : false;
                    $this->users->setMustChangePassword($id, $mustChange);
                    $this->tokens->deleteForUser($id);
                }

                if (
                    ($data['status'] ?? $existing['status']) !== 'active'
                    || ($data['role'] ?? $existing['role']) !== $existing['role']
                ) {
                    $this->tokens->deleteForUser($id);
                }

                $this->activity->log(
                    $actorUserId,
                    'user.update',
                    'user',
                    $id,
                    ['fields' => array_keys($input)]
                );
            });
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'A user with that email or phone already exists.'
                );
            }
            throw $exception;
        }

        return $this->get($id);
    }

    public function updateProfile(int $id, array $input): array
    {
        $validator = new InputValidator($input);
        $validator
            ->optionalString('first_name', 1, 100, false)
            ->optionalString('last_name', 1, 100, false)
            ->optionalEmail('email')
            ->optionalString('phone', 3, 40);
        $validator->throwIfInvalid();

        $data = [];

        foreach (['first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }
        foreach (['email', 'phone'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->nullableTrim($input[$field]);
            }
        }

        $existing = $this->users->findById($id);
        if (!$existing) {
            throw ApiException::notFound('User not found.');
        }

        $nextEmail = array_key_exists('email', $data)
            ? $data['email']
            : $existing['email'];
        $nextPhone = array_key_exists('phone', $data)
            ? $data['phone']
            : $existing['phone'];

        if (!$nextEmail && !$nextPhone) {
            throw ApiException::validation([
                'contact' => [
                    'At least one of email or phone must remain available.'
                ]
            ]);
        }

        try {
            $this->database->transaction(function () use ($id, $data): void {
                $this->users->update($id, $data);
                $this->activity->log(
                    $id,
                    'profile.update',
                    'user',
                    $id,
                    ['fields' => array_keys($data)]
                );
            });
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw ApiException::conflict(
                    'That email or phone is already being used.'
                );
            }
            throw $exception;
        }

        return $this->get($id);
    }

    public function changePassword(int $id, array $input): void
    {
        (new InputValidator($input))
            ->requiredString('current_password', 8, 255)
            ->requiredString('new_password', 8, 255)
            ->throwIfInvalid();

        $user = $this->users->findById($id);
        if (!$user) {
            throw ApiException::notFound('User not found.');
        }

        if (!password_verify($input['current_password'], $user['password_hash'])) {
            throw ApiException::validation([
                'current_password' => ['Current password is incorrect.']
            ]);
        }

        if ($input['current_password'] === $input['new_password']) {
            throw ApiException::validation([
                'new_password' => [
                    'New password must be different from the current password.'
                ]
            ]);
        }

        $this->database->transaction(function () use ($id, $input): void {
            $this->users->updatePassword(
                $id,
                password_hash($input['new_password'], PASSWORD_DEFAULT)
            );
            $this->users->setMustChangePassword($id, false);
            $this->tokens->deleteForUser($id);
            $this->activity->log(
                $id,
                'profile.password_change',
                'user',
                $id
            );
        });
    }

    private function normalizeUserUpdate(array $input): array
    {
        $data = [];

        foreach (['role', 'status'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $input[$field];
            }
        }
        foreach (['first_name', 'last_name'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }
        foreach (['email', 'phone'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = $this->nullableTrim($input[$field]);
            }
        }
        if (array_key_exists('must_change_password', $input)) {
            $data['must_change_password'] = filter_var($input['must_change_password'], FILTER_VALIDATE_BOOL) ? 1 : 0;
        }

        return $data;
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
