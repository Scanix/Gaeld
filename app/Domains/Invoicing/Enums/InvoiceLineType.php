<?php

namespace App\Domains\Invoicing\Enums;

enum InvoiceLineType: string
{
    case Item = 'item';
    case Discount = 'discount';
    case Text = 'text';

    public function label(): string
    {
        return match ($this) {
            self::Item => __('app.line_type_item'),
            self::Discount => __('app.line_type_discount'),
            self::Text => __('app.line_type_text'),
        };
    }

    public function hasAmount(): bool
    {
        return $this !== self::Text;
    }
}
