<?php

namespace App\Support;

use App\Models\Property;
use App\Models\User;

class PropertyShare
{
    /**
     * The message a broker forwards to a client about a property.
     *
     * Deliberately not a link to the portal: the portal is behind a login the
     * client does not have, so a link would be a dead end for them. The details
     * plus the map link are the useful part, and they paste into WhatsApp, SMS
     * or email unchanged.
     */
    public static function message(Property $property, User $sharer): string
    {
        $price = $property->list_price ?: $property->asking_price ?: $property->estimated_value;

        $specs = collect([
            $property->bedrooms
                ? trans_choice(':count bed|:count beds', $property->bedrooms, ['count' => $property->bedrooms])
                : null,
            $property->bathrooms
                ? trans_choice(':count bath|:count baths', $property->bathrooms, ['count' => $property->bathrooms])
                : null,
            $property->square_footage
                ? number_format($property->square_footage).' '.__('sq ft')
                : null,
        ])->filter()->implode(' · ');

        $lines = [
            $property->address,
            trim("{$property->city}, {$property->state} {$property->zip_code}", ', '),
            '',
            $specs ?: null,
            $price ? __('Price').': '.number_format((float) $price) : null,
            '',
            __('Location').': '.$property->map_link,
            '',
            __('Shared by :name, :company', [
                'name' => $sharer->name,
                'company' => Brand::name(),
            ]),
        ];

        // Drop the blank separators around sections that turned out empty, so a
        // property with no specs and no price doesn't send three blank lines.
        return trim(preg_replace(
            "/\n{3,}/",
            "\n\n",
            implode("\n", array_filter($lines, fn ($line) => $line !== null))
        ));
    }
}
