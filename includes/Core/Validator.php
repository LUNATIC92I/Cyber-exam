<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Small declarative validator.
 *   $v = Validator::make($data, ['email' => 'required|email|max:191']);
 *   if ($v->fails()) { $v->errors(); }
 */
final class Validator
{
    private array $errors = [];

    private function __construct(private array $data, private array $rules)
    {
        $this->run();
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? '';
            $value = is_string($value) ? $value : '';
            $label = ucfirst(str_replace('_', ' ', $field));
            foreach (explode('|', $ruleString) as $rule) {
                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);
                $error = $this->check($name, $param, $value, $label, $field);
                if ($error !== null) {
                    $this->errors[$field] = $error;
                    break;
                }
            }
        }
    }

    private function check(string $rule, ?string $param, string $value, string $label, string $field): ?string
    {
        if ($rule !== 'required' && $value === '') {
            return null;
        }
        return match ($rule) {
            'required' => $value === '' ? "{$label} is required." : null,
            'email'    => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'Please enter a valid email address.',
            'min'      => mb_strlen($value) < (int) $param ? "{$label} must be at least {$param} characters." : null,
            'max'      => mb_strlen($value) > (int) $param ? "{$label} must not exceed {$param} characters." : null,
            'name'     => preg_match("/^[\p{L}\p{M}][\p{L}\p{M}' .-]*$/u", $value) ? null : "{$label} contains invalid characters.",
            'country'  => array_key_exists($value, countries()) ? null : 'Please select a valid country.',
            'same'     => $value === (string) ($this->data[$param] ?? '') ? null : 'The password confirmation does not match.',
            'password' => self::strongPassword($value) ? null
                : 'Password must be at least 10 characters and include upper and lower case letters, a number and a symbol.',
            'int'      => preg_match('/^-?\d+$/', $value) ? null : "{$label} must be a whole number.",
            'between'  => (function () use ($value, $param, $label) {
                [$lo, $hi] = array_map('intval', explode(',', (string) $param));
                return (!preg_match('/^-?\d+$/', $value) || (int) $value < $lo || (int) $value > $hi)
                    ? "{$label} must be between {$lo} and {$hi}." : null;
            })(),
            'in'       => in_array($value, explode(',', (string) $param), true) ? null : "{$label} is invalid.",
            'regex'    => preg_match((string) $param, $value) ? null : "{$label} format is invalid.",
            default    => throw new \InvalidArgumentException("Unknown validation rule {$rule} for {$field}"),
        };
    }

    public static function strongPassword(string $p): bool
    {
        return mb_strlen($p) >= 10 && mb_strlen($p) <= 128
            && preg_match('/[a-z]/', $p) && preg_match('/[A-Z]/', $p)
            && preg_match('/\d/', $p) && preg_match('/[^A-Za-z0-9]/', $p);
    }
}
