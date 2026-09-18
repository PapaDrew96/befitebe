<?php

declare(strict_types=1);

namespace Befit\Support;

use Befit\Exception\ApiException;
use DateTimeImmutable;

final class InputValidator
{
    private array $errors = [];

    public function __construct(private readonly array $data)
    {
    }

    public function requiredString(string $field, int $min = 1, int $max = 255): self
    {
        if (!array_key_exists($field, $this->data) || !is_string($this->data[$field])) {
            $this->errors[$field][] = 'This field is required.';
            return $this;
        }

        $length = mb_strlen(trim($this->data[$field]));
        if ($length < $min || $length > $max) {
            $this->errors[$field][] = "Must contain between {$min} and {$max} characters.";
        }
        return $this;
    }

    public function optionalString(string $field, int $min = 0, int $max = 255, bool $nullable = true): self
    {
        if (!array_key_exists($field, $this->data)) {
            return $this;
        }
        if ($this->data[$field] === null && $nullable) {
            return $this;
        }
        if (!is_string($this->data[$field])) {
            $this->errors[$field][] = 'Must be a string.';
            return $this;
        }

        $length = mb_strlen(trim($this->data[$field]));
        if ($length < $min || $length > $max) {
            $this->errors[$field][] = "Must contain between {$min} and {$max} characters.";
        }
        return $this;
    }

    public function optionalEmail(string $field): self
    {
        if (!array_key_exists($field, $this->data) || $this->data[$field] === null || $this->data[$field] === '') {
            return $this;
        }
        if (!is_string($this->data[$field]) || filter_var($this->data[$field], FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field][] = 'Must be a valid email address.';
        }
        return $this;
    }

    public function requiredInt(string $field, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): self
    {
        if (!array_key_exists($field, $this->data) || filter_var($this->data[$field], FILTER_VALIDATE_INT) === false) {
            $this->errors[$field][] = 'Must be an integer.';
            return $this;
        }
        $value = (int) $this->data[$field];
        if ($value < $min || $value > $max) {
            $this->errors[$field][] = "Must be between {$min} and {$max}.";
        }
        return $this;
    }

    public function optionalInt(string $field, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): self
    {
        if (!array_key_exists($field, $this->data) || $this->data[$field] === null || $this->data[$field] === '') {
            return $this;
        }
        return $this->requiredInt($field, $min, $max);
    }

    public function requiredDate(string $field): self
    {
        if (!isset($this->data[$field]) || !is_string($this->data[$field]) || !$this->isExactDate($this->data[$field])) {
            $this->errors[$field][] = 'Must be a date in YYYY-MM-DD format.';
        }
        return $this;
    }

    public function optionalDate(string $field): self
    {
        if (!array_key_exists($field, $this->data) || $this->data[$field] === null || $this->data[$field] === '') {
            return $this;
        }
        return $this->requiredDate($field);
    }

    public function requiredTime(string $field): self
    {
        if (!isset($this->data[$field]) || !is_string($this->data[$field]) ||
            !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $this->data[$field])) {
            $this->errors[$field][] = 'Must be a time in HH:MM or HH:MM:SS format.';
        }
        return $this;
    }

    public function optionalTime(string $field): self
    {
        if (!array_key_exists($field, $this->data) || $this->data[$field] === null || $this->data[$field] === '') {
            return $this;
        }
        return $this->requiredTime($field);
    }

    public function oneOf(string $field, array $allowed, bool $required = false): self
    {
        if (!array_key_exists($field, $this->data)) {
            if ($required) {
                $this->errors[$field][] = 'This field is required.';
            }
            return $this;
        }
        if (!in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field][] = 'Invalid value.';
        }
        return $this;
    }

    public function optionalBool(string $field): self
    {
        if (!array_key_exists($field, $this->data)) {
            return $this;
        }
        $value = $this->data[$field];
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
            $this->errors[$field][] = 'Must be a boolean.';
        }
        return $this;
    }

    public function addError(string $field, string $message): self
    {
        $this->errors[$field][] = $message;
        return $this;
    }

    public function throwIfInvalid(): void
    {
        if ($this->errors !== []) {
            throw ApiException::validation($this->errors);
        }
    }

    private function isExactDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        return $parsed !== false && $parsed->format('Y-m-d') === $date;
    }
}
