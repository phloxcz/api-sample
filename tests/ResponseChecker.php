<?php

declare(strict_types=1);

/**
 * Checks real responses against the generated documents, so the documentation
 * can't drift from what the API returns.
 *
 * It knows the JSON Schema keywords the generators write: $ref, allOf, anyOf,
 * type, enum, properties, required, items and additionalProperties.
 */
final class ResponseChecker
{
    /** @var list<string> */
    public array $errors = [];

    public int $checked = 0;

    /**
     * @param array<string, mixed> $openApi
     * @param array<string, mixed> $openRpc
     */
    public function __construct(
        private readonly array $openApi,
        private readonly array $openRpc,
        private readonly string $prefix,
    ) {
    }

    /**
     * @param array<string, string> $headers
     */
    public function checkRest(string $method, string $url, int $status, array $headers, string $text): void
    {
        $path = substr((string) parse_url($url, PHP_URL_PATH), strlen($this->prefix));
        $where = "{$method} {$url} {$status}";

        $template = $this->findPath($path);
        if ($template === null) {
            return; // not of this API, such as JSON-RPC
        }

        $operation = $this->openApi['paths'][$template][strtolower($method)] ?? null;
        if ($operation === null) {
            $this->errors[] = "{$where}: {$template} has no {$method}";
            return;
        }

        $response = $operation['responses'][(string) $status] ?? null;
        if ($response === null) {
            $this->errors[] = "{$where}: the status is not documented, only " . implode(', ', array_keys($operation['responses']));
            return;
        }

        if (!str_contains($headers['content-type'] ?? '', 'application/json')) {
            return;
        }

        $schema = $response['content']['application/json']['schema'] ?? null;
        if ($schema === null) {
            $this->errors[] = "{$where}: JSON came, none is documented";
            return;
        }

        $this->checked++;
        array_push($this->errors, ...self::validate(json_decode($text, true), $schema, $this->openApi, $where));
    }

    public function checkRpc(string $name, mixed $result): void
    {
        foreach ($this->openRpc['methods'] as $method) {
            if ($method['name'] === $name) {
                $this->checked++;
                array_push($this->errors, ...self::validate($result, $method['result']['schema'], $this->openRpc, "rpc {$name}"));
                return;
            }
        }

        $this->errors[] = "rpc {$name}: not documented";
    }

    /**
     * The template matching a path, preferring the one with the fewest parameters,
     * so `/tasks/summary` is not taken for `/tasks/{id}`.
     */
    private function findPath(string $path): ?string
    {
        $found = null;
        foreach (array_keys($this->openApi['paths']) as $template) {
            $pattern = '~^' . preg_replace('~\\\\\{[^/]+?\\\\\}~', '[^/]+', preg_quote($template, '~')) . '$~';
            if (preg_match($pattern, $path) && ($found === null || substr_count($template, '{') < substr_count($found, '{'))) {
                $found = $template;
            }
        }

        return $found;
    }

    /**
     * @param array<string, mixed> $root The document, for $ref
     * @return list<string>
     */
    public static function validate(mixed $value, mixed $schema, array $root, string $where): array
    {
        if (!is_array($schema) || $schema === []) {
            return []; // anything
        }

        if (isset($schema['$ref'])) {
            $target = $root;
            foreach (explode('/', substr($schema['$ref'], 2)) as $part) {
                $target = $target[$part] ?? null;
            }
            if ($target === null) {
                return ["{$where}: {$schema['$ref']} does not exist"];
            }
            return self::validate($value, $target, $root, $where);
        }

        $errors = [];

        foreach ($schema['allOf'] ?? [] as $part) {
            array_push($errors, ...self::validate($value, $part, $root, $where));
        }

        if (isset($schema['anyOf'])) {
            $matches = array_filter($schema['anyOf'], static fn(mixed $option): bool => self::validate($value, $option, $root, $where) === []);
            if ($matches === []) {
                $errors[] = "{$where}: " . json_encode($value) . ' matches none of anyOf';
            }
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'], true)) {
            $errors[] = "{$where}: " . json_encode($value) . ' is not one of ' . json_encode($schema['enum']);
        }

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];
            $actual = self::typeOf($value);
            $fits = array_filter($types, static fn(string $type): bool => in_array($type, $actual, true));
            if ($fits === []) {
                return [...$errors, "{$where}: " . json_encode($value) . ' is not ' . implode('|', $types)];
            }
        }

        if (is_array($value) && (!array_is_list($value) || $value === [])) {
            foreach ($schema['required'] ?? [] as $name) {
                if (!array_key_exists($name, $value)) {
                    $errors[] = "{$where}: '{$name}' is missing";
                }
            }
            foreach ($value as $name => $item) {
                $property = $schema['properties'][$name] ?? $schema['additionalProperties'] ?? null;
                if ($property !== null && $property !== true) {
                    array_push($errors, ...self::validate($item, $property, $root, "{$where}.{$name}"));
                }
            }
        }

        if (is_array($value) && array_is_list($value) && isset($schema['items'])) {
            foreach ($value as $index => $item) {
                array_push($errors, ...self::validate($item, $schema['items'], $root, "{$where}[{$index}]"));
            }
        }

        return $errors;
    }

    /**
     * @return list<string> JSON types the value can be, an empty array being both a list and an object
     */
    private static function typeOf(mixed $value): array
    {
        return match (true) {
            $value === null => ['null'],
            is_bool($value) => ['boolean'],
            is_int($value) => ['integer', 'number'],
            is_float($value) => ['number'],
            is_string($value) => ['string'],
            $value === [] => ['array', 'object'],
            array_is_list($value) => ['array'],
            default => ['object'],
        };
    }
}
