<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;

/**
 * Compose le texte WhatsApp d'une demande.
 *
 * Le message reprend exactement les choix enregistrés en base : le client
 * reste maître de l'envoi, le serveur ne fait que préparer le brouillon.
 */
class OrderMessageBuilder
{
    public static function forOrder(Order $order): string
    {
        $currency = config('catalog.currency.label');
        $lines = [
            'Bonjour Stive Clothing,',
            '',
            'Je souhaite commander les pièces suivantes :',
        ];

        foreach ($order->items as $item) {
            $lines[] = '• '.self::itemLine($item, $currency);
        }

        $lines[] = '';
        $lines[] = 'Total indicatif : '.number_format($order->total_estimate, 0, ',', ' ').' '.$currency;

        foreach ($order->items as $item) {
            if ($item->custom_size && $item->measurements->isNotEmpty()) {
                $lines[] = '';
                $lines[] = 'Mensurations ('.$item->product_snapshot['name'].') :';

                foreach ($item->measurements->sortBy('id') as $measurement) {
                    $lines[] = '- '.$measurement->label.' : '.self::formatValue($measurement->value).' '.$measurement->unit;
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Référence : '.$order->reference;
        $lines[] = 'Nom : '.$order->customer_name;
        $lines[] = 'Téléphone : '.$order->customer_phone;

        if ($order->customer_city) {
            $lines[] = 'Ville : '.$order->customer_city;
        }

        if ($order->customer_note) {
            $lines[] = 'Précisions : '.$order->customer_note;
        }

        $lines[] = '';
        $lines[] = 'Merci de me confirmer le prix final, le délai de confection et les modalités de livraison.';

        return implode("\n", $lines);
    }

    private static function itemLine(OrderItem $item, string $currency): string
    {
        $snapshot = $item->product_snapshot ?? [];
        $variants = array_filter([
            $snapshot['color'] ?? null,
            $snapshot['fabric'] ?? null,
            $snapshot['size'] ?? null,
        ]);

        $line = ($snapshot['name'] ?? 'Modèle');

        if ($variants !== []) {
            $line .= ' — '.implode(' · ', $variants);
        }

        $line .= ' ('.number_format($item->unit_price, 0, ',', ' ').' '.$currency.')';

        if ($item->quantity > 1) {
            $line .= ' x'.$item->quantity;
        }

        return $line;
    }

    /**
     * Affiche 98 au lieu de 98.0, mais conserve 98.5 si la mesure est décimale.
     */
    public static function formatValue(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 1, ',', ''), '0'), ',');
    }
}
