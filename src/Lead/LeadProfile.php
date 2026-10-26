<?php

declare(strict_types=1);

namespace Prospec\Lead;

/**
 * Why a business is a prospect. Keys are the values stored in `leads.perfil`.
 */
final class LeadProfile
{
    public const NO_WEBSITE = 'sem_site';
    public const SOCIAL_ONLY = 'so_rede';
    public const WEAK_WEBSITE = 'site_ruim';

    public const LABELS = [
        'sem_site' => 'Sem site',
        'so_rede' => 'Só rede social',
        'site_ruim' => 'Site fraco',
    ];

    public static function isValid(string $profile): bool
    {
        return isset(self::LABELS[$profile]);
    }

    public static function label(string $profile): string
    {
        return self::LABELS[$profile] ?? $profile;
    }
}
