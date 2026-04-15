<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

final readonly class RequestValidator
{
    /**
     * Parse and validate the request body as a JSON object.
     *
     * @return array<string, mixed>
     */
    public function parseBody(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        if (!is_array($body)) {
            throw new ValidationException('Request body must be a valid JSON object');
        }

        return $body;
    }

    /**
     * Extract a required string field, trimmed and length-validated.
     */
    public function requireString(array $body, string $field, int $min = 1, int $max = 255): string
    {
        if (!isset($body[$field]) || !is_string($body[$field])) {
            throw new ValidationException("Field '{$field}' is required and must be a string");
        }

        $value = trim($body[$field]);

        if (mb_strlen($value) < $min || mb_strlen($value) > $max) {
            throw new ValidationException("Field '{$field}' must be {$min}-{$max} characters");
        }

        return $value;
    }

    /**
     * Extract an optional string field, trimmed and length-validated.
     */
    public function optionalString(array $body, string $field, int $max = 255): string
    {
        if (!isset($body[$field])) {
            return '';
        }

        if (!is_string($body[$field])) {
            throw new ValidationException("Field '{$field}' must be a string");
        }

        $value = trim($body[$field]);

        if (mb_strlen($value) > $max) {
            throw new ValidationException("Field '{$field}' must not exceed {$max} characters");
        }

        return $value;
    }

    /**
     * Extract and validate a positive integer query parameter.
     */
    public function queryInt(array $params, string $field, int $default, int $min, int $max): int
    {
        if (!isset($params[$field])) {
            return $default;
        }

        $value = filter_var($params[$field], FILTER_VALIDATE_INT);

        if ($value === false || $value < $min || $value > $max) {
            throw new ValidationException("Parameter '{$field}' must be an integer between {$min} and {$max}");
        }

        return $value;
    }
}
