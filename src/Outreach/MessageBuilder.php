<?php

declare(strict_types=1);

namespace Prospec\Outreach;

use DateTimeImmutable;
use DateTimeZone;
use Prospec\Lead\Contact;
use Prospec\Lead\Lead;
use Prospec\Lead\LeadProfile;

/**
 * Builds the WhatsApp messages sent to prospects. Message copy is in Portuguese
 * because the recipients are Brazilian businesses.
 */
final class MessageBuilder
{
    /** Short, customer-friendly phrase for each website issue detected during qualification. */
    private const ISSUE_PHRASES = [
        'certificado inválido' => 'aparece como "não seguro" no navegador',
        'sem HTTPS' => 'aparece como "não seguro" no navegador',
        'não adaptado ao celular' => 'não se ajusta bem ao celular',
        'carrega devagar' => 'demora para abrir',
        'sem botão de WhatsApp' => 'não tem botão de WhatsApp para o cliente chamar',
        'rodapé desatualizado' => 'está com o ano do rodapé desatualizado',
        'site não abriu' => 'não abriu quando tentei acessar',
        'site fora do ar' => 'não abriu quando tentei acessar',
    ];

    private const PORTFOLIO_URL = 'https://nemaxdigital.com.br';

    /**
     * @param string   $senderName how the salesperson signs the message
     * @param string   $agency     agency name
     * @param int|null $hour       fixed hour of day (for tests); defaults to the current hour in São Paulo
     */
    public function __construct(
        private readonly string $senderName,
        private readonly string $agency,
        private readonly ?int $hour = null,
    ) {
    }

    public static function greeting(int $hour): string
    {
        if ($hour >= 5 && $hour < 12) {
            return 'bom dia';
        }
        if ($hour >= 12 && $hour < 18) {
            return 'boa tarde';
        }
        return 'boa noite';
    }

    /**
     * First message: short, only opens the conversation and asks who to talk to.
     * Returns null when the lead has no WhatsApp-capable number or an unknown profile.
     */
    public function first(Lead $lead): ?WhatsAppMessage
    {
        $number = PhoneNumber::toWhatsApp($lead->phone);
        if ($number === null) {
            return null;
        }
        $greeting = $this->currentGreeting();
        // Stable variation per lead, so the preview always matches what will be sent.
        $opening = [
            ucfirst($greeting) . '! Aqui é ' . $this->senderName . ', da ' . $this->agency . '.',
            'Oi, ' . $greeting . '! Sou ' . $this->senderName . ', da ' . $this->agency . '.',
            'Olá, ' . $greeting . '! Aqui é ' . $this->senderName . ', da ' . $this->agency . '.',
        ][$lead->id % 3];

        $company = $lead->name;
        $body = match ($lead->profile) {
            LeadProfile::NO_WEBSITE => 'Procurei a ' . $company . ' no Google e vi que o perfil não tem site. Com quem posso falar sobre isso?',
            LeadProfile::SOCIAL_ONLY => 'Vi que o perfil da ' . $company . ' no Google leva direto para as redes sociais. Quem cuida disso aí? Queria mostrar uma ideia rápida.',
            LeadProfile::WEAK_WEBSITE => $this->weakWebsiteBody($lead),
            default => null,
        };
        if ($body === null) {
            return null;
        }
        return WhatsAppMessage::to($number, $opening . ' ' . $body);
    }

    /**
     * Second message on the same number: continues the conversation without greeting again.
     */
    public function followUp(Lead $lead): ?WhatsAppMessage
    {
        $number = PhoneNumber::toWhatsApp($lead->phone);
        if ($number === null) {
            return null;
        }
        return WhatsAppMessage::to($number, $this->hook($lead) . "\n\n" . $this->portfolioBlock());
    }

    /**
     * First contact with the decision maker, with greeting and context.
     */
    public function toContact(Lead $lead, Contact $contact): ?WhatsAppMessage
    {
        $number = PhoneNumber::toWhatsApp($contact->phone);
        if ($number === null) {
            return null;
        }
        $who = trim($contact->name);
        $context = [
            LeadProfile::NO_WEBSITE => 'Vi que o perfil não tem site no Google.',
            LeadProfile::SOCIAL_ONLY => 'Vi que o perfil no Google leva direto para as redes sociais.',
            LeadProfile::WEAK_WEBSITE => 'Vi alguns pontos no site que podem estar custando clientes.',
        ][$lead->profile] ?? '';

        $text = ucfirst($this->currentGreeting()) . ($who !== '' ? ', ' . $who : '') . '! Aqui é ' . $this->senderName . ', da ' . $this->agency . '. '
            . 'Falei com o pessoal da ' . $lead->name . ' e me passaram seu contato. ' . $context . "\n\n"
            . $this->hook($lead) . "\n\n" . $this->portfolioBlock();
        return WhatsAppMessage::to($number, $text);
    }

    /**
     * Short text for the business owner with the three most important audit findings.
     *
     * @param array<string, mixed> $audit result of SiteAuditor::evaluate()
     */
    public function auditSummary(array $audit, string $company): string
    {
        $points = [];
        foreach (array_slice($audit['findings'] ?? [], 0, 3) as $finding) {
            $points[] = '- ' . $finding['title'] . ': ' . $finding['impact'];
        }
        $subject = 'Analisei o site' . ($company !== '' ? ' da ' . $company : '') . ' (' . ($audit['host'] ?? '') . ')';
        if (!$points) {
            return $subject . ' e não encontrei problemas relevantes nos itens que verifico. Se quiser, posso mostrar outros pontos de melhoria.';
        }
        return $subject . ' e separei os pontos principais:' . "\n" . implode("\n", $points)
            . "\n\nTenho o relatório completo com o que encontrei e como corrigir. Posso te enviar? " . $this->senderName . ', ' . $this->agency . '.';
    }

    private function weakWebsiteBody(Lead $lead): string
    {
        $phrase = null;
        foreach ($lead->reasons as $reason) {
            if (isset(self::ISSUE_PHRASES[$reason])) {
                $phrase = self::ISSUE_PHRASES[$reason];
                break;
            }
        }
        return $phrase !== null
            ? 'Abri o site da ' . $lead->name . ' e ele ' . $phrase . '. Com quem posso falar sobre isso? Mando um print do que vi.'
            : 'Abri o site da ' . $lead->name . ' e vi alguns pontos que podem estar custando clientes. Com quem posso falar sobre isso?';
    }

    private function hook(Lead $lead): string
    {
        return 'Quando o cliente da ' . $lead->name . ' pesquisa no Google, ele fecha com vocês ou com o concorrente que aparece melhor?';
    }

    private function portfolioBlock(): string
    {
        return "Veja os projetos que já entregamos, com prints reais e o que cada site faz:\n" . self::PORTFOLIO_URL . "\n\n"
            . 'Leva 1 minuto e abre bem no celular. Entra lá e me diz qual deles mais combina com vocês.';
    }

    private function currentGreeting(): string
    {
        $hour = $this->hour ?? (int)(new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('G');
        return self::greeting($hour);
    }
}
