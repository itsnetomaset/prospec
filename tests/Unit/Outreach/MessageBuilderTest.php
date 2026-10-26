<?php

declare(strict_types=1);

namespace Prospec\Tests\Unit\Outreach;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Prospec\Lead\Contact;
use Prospec\Lead\Lead;
use Prospec\Lead\LeadProfile;
use Prospec\Outreach\MessageBuilder;

final class MessageBuilderTest extends TestCase
{
    private MessageBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MessageBuilder('Giuseppe', 'NEMAX Digital', hour: 10);
    }

    /** @return array<string, array{int, string}> */
    public static function hours(): array
    {
        return [
            'late night' => [4, 'boa noite'],
            'morning starts' => [5, 'bom dia'],
            'before noon' => [11, 'bom dia'],
            'noon' => [12, 'boa tarde'],
            'evening starts' => [18, 'boa noite'],
        ];
    }

    #[DataProvider('hours')]
    public function testGreetingFollowsTheTimeOfDay(int $hour, string $expected): void
    {
        self::assertSame($expected, MessageBuilder::greeting($hour));
    }

    public function testFirstMessageForBusinessWithoutWebsite(): void
    {
        $message = $this->builder->first(self::lead(LeadProfile::NO_WEBSITE, id: 3));

        self::assertNotNull($message);
        self::assertSame(
            'Bom dia! Aqui é Giuseppe, da NEMAX Digital. Procurei a Pet Feliz no Google e vi que o perfil não tem site. Com quem posso falar sobre isso?',
            $message->text,
        );
        self::assertStringStartsWith('https://wa.me/5516991234567?text=Bom%20dia%21', $message->url);
    }

    public function testOpeningVariesByLeadButIsStable(): void
    {
        $a = $this->builder->first(self::lead(LeadProfile::NO_WEBSITE, id: 4));
        $b = $this->builder->first(self::lead(LeadProfile::NO_WEBSITE, id: 4));
        $c = $this->builder->first(self::lead(LeadProfile::NO_WEBSITE, id: 5));

        self::assertSame($a?->text, $b?->text);
        self::assertStringStartsWith('Oi, bom dia! Sou Giuseppe', (string)$a?->text);
        self::assertStringStartsWith('Olá, bom dia! Aqui é Giuseppe', (string)$c?->text);
    }

    public function testSocialOnlyMessageMentionsSocialMedia(): void
    {
        $message = $this->builder->first(self::lead(LeadProfile::SOCIAL_ONLY));

        self::assertStringContainsString('leva direto para as redes sociais', (string)$message?->text);
    }

    public function testWeakWebsiteMessageUsesTheFirstKnownIssue(): void
    {
        $lead = self::lead(LeadProfile::WEAK_WEBSITE, reasons: ['carrega devagar', 'sem HTTPS']);

        self::assertStringContainsString('Abri o site da Pet Feliz e ele demora para abrir.', (string)$this->builder->first($lead)?->text);
    }

    public function testWeakWebsiteMessageFallsBackToGenericText(): void
    {
        $lead = self::lead(LeadProfile::WEAK_WEBSITE, reasons: ['motivo desconhecido']);

        self::assertStringContainsString('vi alguns pontos que podem estar custando clientes', (string)$this->builder->first($lead)?->text);
    }

    public function testNoMessageWithoutWhatsAppOrWithUnknownProfile(): void
    {
        self::assertNull($this->builder->first(self::lead(LeadProfile::NO_WEBSITE, phone: '0800 123 4567')));
        self::assertNull($this->builder->first(self::lead('outro')));
    }

    public function testFollowUpDoesNotGreetAgain(): void
    {
        $message = $this->builder->followUp(self::lead(LeadProfile::NO_WEBSITE));

        self::assertStringStartsWith('Quando o cliente da Pet Feliz pesquisa no Google', (string)$message?->text);
        self::assertStringContainsString('https://nemaxdigital.com.br', (string)$message?->text);
    }

    public function testMessageToDecisionMakerGoesToTheirNumber(): void
    {
        $message = $this->builder->toContact(self::lead(LeadProfile::SOCIAL_ONLY), new Contact('Dra. Ana', '+55 16 99111-2222'));

        self::assertStringStartsWith('https://wa.me/5516991112222?', (string)$message?->url);
        self::assertStringStartsWith('Bom dia, Dra. Ana! Aqui é Giuseppe, da NEMAX Digital. Falei com o pessoal da Pet Feliz', (string)$message?->text);
        self::assertNull($this->builder->toContact(self::lead(LeadProfile::SOCIAL_ONLY), new Contact('', '123')));
    }

    public function testAuditSummaryListsTheTopThreeFindings(): void
    {
        $finding = fn (string $title): array => ['severity' => 'critico', 'title' => $title, 'detail' => '', 'impact' => 'impacto ' . $title];
        $audit = ['host' => 'pet.com.br', 'findings' => [$finding('A'), $finding('B'), $finding('C'), $finding('D')]];

        $summary = $this->builder->auditSummary($audit, 'Pet Feliz');

        self::assertStringStartsWith("Analisei o site da Pet Feliz (pet.com.br) e separei os pontos principais:\n- A: impacto A\n- B: impacto B\n- C: impacto C", $summary);
        self::assertStringNotContainsString('- D', $summary);
        self::assertStringEndsWith('Giuseppe, NEMAX Digital.', $summary);
    }

    public function testAuditSummaryWithoutFindings(): void
    {
        self::assertStringContainsString('não encontrei problemas relevantes', $this->builder->auditSummary(['host' => 'x.com', 'findings' => []], ''));
    }

    /** @param list<string> $reasons */
    private static function lead(string $profile, int $id = 3, string $phone = '(16) 99123-4567', array $reasons = []): Lead
    {
        return new Lead(id: $id, name: 'Pet Feliz', phone: $phone, profile: $profile, reasons: $reasons);
    }
}
