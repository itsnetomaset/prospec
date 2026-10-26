<?php

declare(strict_types=1);

namespace Prospec\Outreach;

/**
 * A message ready to be opened in WhatsApp through a wa.me link.
 */
final class WhatsAppMessage
{
    private function __construct(
        public readonly string $url,
        public readonly string $text,
    ) {
    }

    /**
     * @param string $nationalNumber number returned by PhoneNumber::toWhatsApp()
     */
    public static function to(string $nationalNumber, string $text): self
    {
        return new self('https://wa.me/55' . $nationalNumber . '?text=' . rawurlencode($text), $text);
    }

    /** Link that lets the user pick the recipient inside WhatsApp. */
    public static function share(string $text): self
    {
        return new self('https://wa.me/?text=' . rawurlencode($text), $text);
    }
}
