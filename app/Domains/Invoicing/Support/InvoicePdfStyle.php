<?php

namespace App\Domains\Invoicing\Support;

/**
 * Layout and style constants for the Swiss QR invoice PDF renderer.
 */
final class InvoicePdfStyle
{
    // Margins
    public const MARGIN_LEFT = 15;

    public const MARGIN_TOP = 15;

    public const MARGIN_RIGHT = 15;

    // Column widths (in mm)
    public const COL_DESCRIPTION = 80;

    public const COL_QUANTITY = 20;

    public const COL_UNIT_PRICE = 30;

    public const COL_VAT = 25;

    public const COL_AMOUNT = 25;

    public const COL_TOTAL_WIDTH = self::COL_DESCRIPTION + self::COL_QUANTITY + self::COL_UNIT_PRICE + self::COL_VAT + self::COL_AMOUNT; // 180

    // X and Y positions
    public const ORGANIZATION_X = 15;

    public const ORGANIZATION_WIDTH = 85;

    public const CUSTOMER_X = 120;

    public const CUSTOMER_INFO_Y = 50;

    public const CUSTOMER_WIDTH = 75;

    public const INVOICE_TITLE_Y = 100;

    public const FOLD_MARK_X = 5;

    public const FOLD_MARK_LENGTH = 3;

    public const FOLD_MARK_TOP_Y = 105;

    public const PUNCH_MARK_Y = 148.5;

    public const FOLD_MARK_BOTTOM_Y = 210;

    public const TOTALS_LABEL_WIDTH = 155;

    // Legacy aliases kept for callers using the original style names.
    public const ORG_INFO_X = self::CUSTOMER_X;

    public const ORG_INFO_WIDTH = self::CUSTOMER_WIDTH;

    // Font sizes
    public const FONT_ORG_NAME = 10;

    public const FONT_ORG_DETAIL = 8;

    public const FONT_CUSTOMER_NAME = 10;

    public const FONT_CUSTOMER_DETAIL = 9;

    public const FONT_INVOICE_TITLE = 16;

    public const FONT_INVOICE_META = 9;

    public const FONT_TABLE_HEADER = 8;

    public const FONT_TABLE_ROW = 8;

    public const FONT_TOTALS = 9;

    public const FONT_TOTALS_GRAND = 11;

    public const FONT_NOTES = 8;

    public const FONT_HEADER_FOOTER_TEXT = 8;

    // Colors (RGB)
    public const COLOR_GRAY = [100, 100, 100];

    public const COLOR_DARK_GRAY = [80, 80, 80];

    public const COLOR_BLACK = [0, 0, 0];

    public const COLOR_FILL = [245, 245, 245];

    public const COLOR_RULE = [211, 220, 214];

    public const COLOR_ACCENT = [31, 76, 53];

    public const COLOR_LIGHT = [150, 160, 153];

    // Logo
    public const LOGO_X = 15;

    public const LOGO_Y = 15;

    public const LOGO_WIDTH = 28;

    // Language mapping for QR bill
    public const QR_LANGUAGE_MAP = ['en' => 'en', 'de' => 'de', 'fr' => 'fr', 'it' => 'it'];
}
