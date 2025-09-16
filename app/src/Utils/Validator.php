<?php
namespace VeriBits\Utils;

class Validator {
    private array $errors = [];
    private array $data = [];

    public function __construct(array $data) {
        $this->data = $data;
    }

    public function required(string $field, string $message = null): self {
        if (!isset($this->data[$field]) || $this->data[$field] === '' || $this->data[$field] === null) {
            $this->errors[$field][] = $message ?? "The $field field is required";
        }
        return $this;
    }

    public function email(string $field, string $message = null): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field][] = $message ?? "The $field must be a valid email address";
        }
        return $this;
    }

    public function string(string $field, int $min = 0, int $max = 255, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!is_string($value)) {
                $this->errors[$field][] = $message ?? "The $field must be a string";
            } elseif (strlen($value) < $min) {
                $this->errors[$field][] = $message ?? "The $field must be at least $min characters";
            } elseif (strlen($value) > $max) {
                $this->errors[$field][] = $message ?? "The $field must not exceed $max characters";
            }
        }
        return $this;
    }

    public function url(string $field, string $message = null): self {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_URL)) {
            $this->errors[$field][] = $message ?? "The $field must be a valid URL";
        }
        return $this;
    }

    public function sha256(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!preg_match('/^[a-f0-9]{64}$/i', $value)) {
                $this->errors[$field][] = $message ?? "The $field must be a valid SHA256 hash";
            }
        }
        return $this;
    }

    public function alphanumeric(string $field, string $message = null): self {
        if (isset($this->data[$field])) {
            $value = $this->data[$field];
            if (!preg_match('/^[a-zA-Z0-9]+$/', $value)) {
                $this->errors[$field][] = $message ?? "The $field must contain only letters and numbers";
            }
        }
        return $this;
    }

    public function in(string $field, array $values, string $message = null): self {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $values)) {
            $allowed = implode(', ', $values);
            $this->errors[$field][] = $message ?? "The $field must be one of: $allowed";
        }
        return $this;
    }

    public function sanitize(string $field): string {
        return htmlspecialchars(trim($this->data[$field] ?? ''), ENT_QUOTES, 'UTF-8');
    }

    public function isValid(): bool {
        return empty($this->errors);
    }

    public function getErrors(): array {
        return $this->errors;
    }

    public function getFirstError(): string {
        if (empty($this->errors)) return '';
        $firstField = array_key_first($this->errors);
        return $this->errors[$firstField][0];
    }
}