<?php

declare(strict_types=1);

/**
 * Escapes a value for HTML output. Used by the view templates.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Builds a link to the lead list keeping the current filters ("?status=novo&pag=2").
 * Export and dashboard switches are never carried over.
 *
 * @param array<string, mixed> $current current query parameters
 * @param array<string, mixed> $changes parameters to set
 */
function list_url(array $current, array $changes): string
{
    unset($current['csv'], $current['painel']);
    return '?' . http_build_query(array_filter($changes + $current, fn ($v) => $v !== '' && $v !== null));
}
