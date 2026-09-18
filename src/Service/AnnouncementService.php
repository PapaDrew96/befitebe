<?php

declare(strict_types=1);

namespace Befit\Service;

use Befit\Database\Database;
use Befit\Exception\ApiException;
use Befit\Repository\ActivityLogRepository;
use Befit\Repository\AnnouncementRepository;
use Befit\Support\InputValidator;
use DateTimeImmutable;
use DateTimeZone;

final class AnnouncementService
{
    private DateTimeZone $timezone;

    public function __construct(
        private readonly Database $database,
        private readonly AnnouncementRepository $announcements,
        private readonly ActivityLogRepository $activity,
        string $timezone
    ) {
        $this->timezone = new DateTimeZone($timezone);
    }

    public function active(): array
    {
        $now = (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s');
        return array_map([$this, 'normalize'], $this->announcements->active($now));
    }

    public function all(): array
    {
        return array_map([$this, 'normalize'], $this->announcements->all());
    }

    public function get(int $id): array
    {
        $row = $this->announcements->find($id);
        if (!$row) {
            throw ApiException::notFound('Announcement not found.');
        }
        return $this->normalize($row);
    }

    public function create(array $input, int $actorUserId): array
    {
        (new InputValidator($input))
            ->requiredString('title', 1, 180)
            ->requiredString('body', 1, 5000)
            ->optionalString('starts_at', 16, 19)
            ->optionalString('expires_at', 16, 19)
            ->optionalBool('is_active')
            ->throwIfInvalid();

        $data = $this->normalizeInput($input, true);
        $id = $this->database->transaction(function () use ($data, $actorUserId): int {
            $id = $this->announcements->create($data + ['created_by' => $actorUserId]);
            $this->activity->log($actorUserId, 'announcement.create', 'announcement', $id);
            return $id;
        });

        return $this->get($id);
    }

    public function update(int $id, array $input, int $actorUserId): array
    {
        if (!$this->announcements->find($id)) {
            throw ApiException::notFound('Announcement not found.');
        }

        (new InputValidator($input))
            ->optionalString('title', 1, 180, false)
            ->optionalString('body', 1, 5000, false)
            ->optionalString('starts_at', 16, 19)
            ->optionalString('expires_at', 16, 19)
            ->optionalBool('is_active')
            ->throwIfInvalid();

        $existing = $this->announcements->find($id);
        $data = $this->normalizeInput($input, false);

        $nextStarts = $data['starts_at'] ?? $existing['starts_at'];
        $nextExpires = array_key_exists('expires_at', $data)
            ? $data['expires_at']
            : $existing['expires_at'];
        if ($nextExpires !== null && $nextExpires < $nextStarts) {
            throw ApiException::validation(['expires_at' => ['Must be after starts_at.']]);
        }

        $this->database->transaction(function () use ($id, $data, $actorUserId): void {
            $this->announcements->update($id, $data);
            $this->activity->log(
                $actorUserId,
                'announcement.update',
                'announcement',
                $id,
                ['fields' => array_keys($data)]
            );
        });

        return $this->get($id);
    }

    public function delete(int $id, int $actorUserId): void
    {
        if (!$this->announcements->find($id)) {
            throw ApiException::notFound('Announcement not found.');
        }

        $this->database->transaction(function () use ($id, $actorUserId): void {
            $this->announcements->delete($id);
            $this->activity->log($actorUserId, 'announcement.delete', 'announcement', $id);
        });
    }

    private function normalizeInput(array $input, bool $creating): array
    {
        $data = [];
        foreach (['title', 'body'] as $field) {
            if (array_key_exists($field, $input)) {
                $data[$field] = trim((string) $input[$field]);
            }
        }

        if ($creating && !array_key_exists('starts_at', $input)) {
            $data['starts_at'] = (new DateTimeImmutable('now', $this->timezone))->format('Y-m-d H:i:s');
        } elseif (array_key_exists('starts_at', $input)) {
            $data['starts_at'] = $this->parseDateTime($input['starts_at'], 'starts_at');
        }

        if (array_key_exists('expires_at', $input)) {
            $data['expires_at'] = ($input['expires_at'] === null || $input['expires_at'] === '')
                ? null
                : $this->parseDateTime($input['expires_at'], 'expires_at');
        } elseif ($creating) {
            $data['expires_at'] = null;
        }

        if (array_key_exists('is_active', $input)) {
            $data['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOL) ? 1 : 0;
        } elseif ($creating) {
            $data['is_active'] = 1;
        }

        $starts = $data['starts_at'] ?? null;
        $expires = $data['expires_at'] ?? null;
        if ($starts && $expires && $expires < $starts) {
            throw ApiException::validation(['expires_at' => ['Must be after starts_at.']]);
        }

        return $data;
    }

    private function parseDateTime(mixed $value, string $field): string
    {
        if (!is_string($value)) {
            throw ApiException::validation([$field => ['Must be a valid date/time.']]);
        }
        $value = trim($value);
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', DATE_ATOM] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value, $this->timezone);
            if ($date !== false) {
                return $date->setTimezone($this->timezone)->format('Y-m-d H:i:s');
            }
        }
        throw ApiException::validation([$field => ['Use YYYY-MM-DD HH:MM[:SS] or ISO-8601 format.']]);
    }

    private function normalize(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'title' => $row['title'],
            'body' => $row['body'],
            'starts_at' => $row['starts_at'],
            'expires_at' => $row['expires_at'],
            'is_active' => (bool) $row['is_active'],
            'created_by' => isset($row['created_by']) ? (int) $row['created_by'] : null,
            'author_name' => $row['author_name'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
