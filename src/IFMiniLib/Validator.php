<?php

namespace IFMiniLib;

use IFMiniLib\Only;
use IFMiniLib\Clean;

class Validator extends Only
{
    public array $data;
    public array $rules;
    public array $errors;

    public function __construct()
    {
    }

    public function validate(array $data, array $rules): bool
    {
        $this->data = [];
        $this->rules = $rules;
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $value = $data[$field] ?? null;
            $isRequired = (strpos($rules, 'required') !== false);

            if (! $isRequired && empty($value)) {
                $this->data[$field] = null;
                continue;
            }

            foreach (explode('|', $rules) as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }

        return empty($this->errors);
    }

    public function applyRule(string $field, $value, string $rule): void
    {
        if ($rule === 'required') {
            if ($value === null || $value === '') {
                $this->errors[$field][] = 'Required';
            } else {
                $this->data[$field] = $value;
            }
            return ;
        }

        if ($value !== null) {
            if ($rule === 'price') {
                $value = Clean::price($value);
                $this->data[$field] = $value;
                return ;
            }

            if ($rule === 'number') {
                $value = $value === null ? 0 : Clean::number($value);
                $this->data[$field] = $value;
                return ;
            }

            if ($rule === 'int' || $rule === 'integer') {
                $value = (int)$value;
                $this->data[$field] = $value;
                return ;
            }

            if (substr($rule, 0, 6) === 'string') {
                if ($value === null) {
                    $value = '';
                } else {
                    $args = explode(':', $rule);
                    $symbol = '';
                    if (isset($args[1])) {
                        $symbol = $args[1];
                    }
                    $value = Clean::string($value, $symbol);
                }

                $this->data[$field] = $value;
                return ;
            }

            if (substr($rule, 0, 8) === 'string') {
                if ($value === null) {
                    $value = '';
                } else {
                    $value = Clean::string($value);
                }

                $this->data[$field] = $value;
                return ;
            }

            if (substr($rule, 0, 4) === 'html') {
                if ($value === null) {
                    $value = '';
                } else {
                    $args = explode(':', $rule);
                    $symbol = '';
                    if (isset($args[1])) {
                        $symbol = $args[1];
                    }
                    $value = Clean::stringNoStripTags($value, $symbol);
                }

                $this->data[$field] = $value;
                return ;
            }

            if ($rule === 'ip') {
                $value = Clean::ip($value);
                $this->data[$field] = $value;
                return ;
            }
            if ($rule === 'ipnet') {
                $value = Clean::ipnet($value);
                $this->data[$field] = $value;
                return ;
            }

            // Замена символов
            if (preg_match('/^replace:(.+)>(.+)$/', $rule, $matches)) {
                $from = $matches[1];
                $to = $matches[2];
                $value = str_replace($from, $to, $value);
                $this->data[$field] = $value;
                return ;
            }

            if ($rule === 'email') {
                $value = Clean::email($value);
                if ($value > '' && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->data[$field] = $value;
                } else {
                    $this->errors[$field][] = 'Invalid email';
                }
                return ;
            }

            if ($rule === 'url') {
                $value = Clean::url($value);
                if ($value > '' && filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->data[$field] = $value;
                } else {
                    $this->errors[$field][] = 'Invalid URL';
                }
                return ;
            }

            if ($rule === 'domain') {
                $value = Clean::sanitizeDomain($value);
                if ($value > '' && filter_var($value, FILTER_VALIDATE_DOMAIN)) {
                    $this->data[$field] = $value;
                } else {
                    $this->errors[$field][] = 'Invalid domain ' . $this->data[$field];
                }
                return ;
            }

            if (preg_match('/^length:(\d+),(\d+)$/', $rule, $matches)) {
                $min = (int)$matches[1];
                $max = (int)$matches[2];

                if (mb_strlen($value, 'UTF-8') > $max) {
                    $this->errors[$field][] = "Max length is $max";
                } elseif (mb_strlen($value, 'UTF-8') < $min) {
                    $this->errors[$field][] = "Min length is $min";
                } else {
                    $this->data[$field] = $value;
                }
                return ;
            }

            if (preg_match('/^between:(\d+),(\d+)$/', $rule, $matches)) {
                $min = (int)$matches[1];
                $max = (int)$matches[2];
                if ($value >= $min && $value <= $max) {
                    $this->data[$field] = $value;
                } else {
                    if ($value > $max) {
                        $this->errors[$field][] = "Value $value exceeds max $max";
                    }
                    if ($value < $min) {
                        $this->errors[$field][] = "Value $value is less than min $min";
                    }
                }
                return ;
            }

            if (preg_match('/^max:(\d+)$/', $rule, $matches)) {
                $max = (int)$matches[1];
                if ($value > $max) {
                    $this->errors[$field][] = "Max is $max";
                } else {
                    $this->data[$field] = $value;
                }
                return ;
            }

            if (preg_match('/^min:(\d+)$/', $rule, $matches)) {
                $min = (int)$matches[1];
                if ($value < $min) {
                    $this->errors[$field][] = "Min is $min";
                } else {
                    $this->data[$field] = $value;
                }
                return ;
            }

            if (preg_match('/^in:(.+)$/', $rule, $matches)) {
                $allowed = explode(',', $matches[1]);
                if (!in_array($value, $allowed)) {
                    $this->errors[$field][] = 'Invalid value';
                } else {
                    $this->data[$field] = $value;
                }
                return ;
            }

            if (preg_match('/^exists:([\\a-zA-Z]+)$/', $rule, $matches)) {
                $className = $matches[1];
                $object = empty($value) ? null : $className::getById($value);
                if (! $object) {
                    $this->errors[$field][] = "Object $className with id: [$value] not found";
                } else {
                    $this->data[$field] = $value;
                }
                return ;
            }

            $this->errors[$field][] = "unknown rule [$rule]";
        }

        return;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
