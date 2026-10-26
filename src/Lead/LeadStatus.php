<?php

declare(strict_types=1);

namespace Prospec\Lead;

/**
 * Funnel stages. Keys are the values stored in `leads.status`; labels are shown in the UI.
 */
final class LeadStatus
{
    public const NEW = 'novo';
    public const CONTACTED = 'contatado';
    public const DISCARDED = 'descartado';

    public const LABELS = [
        'novo' => 'Novo',
        'contatado' => 'Contatado',
        'respondeu' => 'Respondeu',
        'reuniao' => 'Reunião',
        'fechado' => 'Fechado',
        'perdido' => 'Perdido',
        'nao_contatar' => 'Não contatar',
        'descartado' => 'Descartado',
    ];

    /** Statuses that count as "approached" in the funnel report. */
    public const APPROACHED = ['contatado', 'respondeu', 'reuniao', 'fechado', 'perdido'];

    public static function isValid(string $status): bool
    {
        return isset(self::LABELS[$status]);
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
