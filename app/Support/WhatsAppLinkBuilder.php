<?php

namespace App\Support;

/**
 * Construction des liens « Click-to-Chat » WhatsApp (CDC §6) :
 * seul un lien prérempli est ouvert, jamais un message envoyé à la place
 * du client.
 */
class WhatsAppLinkBuilder
{
    public static function number(): string
    {
        return preg_replace('/\D+/', '', (string) config('catalog.whatsapp.number')) ?? '';
    }

    public static function defaultMessage(): string
    {
        return (string) config('catalog.whatsapp.default_message');
    }

    /**
     * @param  list<string>  $lines
     */
    public static function link(array $lines = [], ?string $number = null): string
    {
        $text = implode("\n", array_filter($lines, fn (string $line) => $line !== ''));

        if ($text === '') {
            $text = self::defaultMessage();
        }

        return self::linkForText($text, $number);
    }

    public static function linkForText(string $text, ?string $number = null): string
    {
        $number = preg_replace('/\D+/', '', $number ?? self::number()) ?? '';

        return config('catalog.whatsapp.base_url').$number.'?text='.rawurlencode($text);
    }
}
