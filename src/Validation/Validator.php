<?php

declare(strict_types=1);

namespace Jb\Validation;

class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /**
     * Validate data against pipe-separated rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string> $rules
     */
    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleSet) {
            foreach (explode('|', $ruleSet) as $rule) {
                $this->apply($field, $data[$field] ?? null, $rule, array_key_exists($field, $data));
            }
        }

        return $this->errors === [];
    }

    /**
     * Return validation errors grouped by field.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    private function apply(string $field, mixed $value, string $rule, bool $present): void
    {
        [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

        if ($name === 'nullable' && ($value === null || $value === '')) {
            return;
        }

        $valid = match ($name) {
            'required' => $present && $value !== null && $value !== '',
            'email' => $value === null || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'string' => $value === null || is_string($value),
            'integer' => $value === null || filter_var($value, FILTER_VALIDATE_INT) !== false,
            'boolean' => $value === null || is_bool($value) || in_array($value, [0, 1, '0', '1'], true),
            'min' => $value === null || strlen((string) $value) >= (int) $parameter,
            'max' => $value === null || strlen((string) $value) <= (int) $parameter,
            'in' => $value === null || in_array((string) $value, explode(',', (string) $parameter), true),
            default => true,
        };

        if (!$valid) {
            $this->errors[$field][] = $this->message($field, $name, $parameter);
        }
    }

    private function message(string $field, string $rule, ?string $parameter): string
    {
        return match ($rule) {
            'required' => "El campo $field es obligatorio.",
            'email' => "El campo $field debe ser un email valido.",
            'min' => "El campo $field debe tener al menos $parameter caracteres.",
            'max' => "El campo $field no debe exceder $parameter caracteres.",
            default => "El campo $field no cumple la regla $rule.",
        };
    }
}
