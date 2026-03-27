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

    // X positions
    public const ORG_INFO_X = 120;

    public const ORG_INFO_WIDTH = 75;

    // Y positions
    public const CUSTOMER_INFO_Y = 45;

    public const INVOICE_TITLE_Y = 80;

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

    // Logo
    public const LOGO_X = 150;

    public const LOGO_Y = 15;

    public const LOGO_WIDTH = 30;

    // Language mapping for QR bill
    public const QR_LANGUAGE_MAP = ['en' => 'en', 'de' => 'de', 'fr' => 'fr', 'it' => 'it'];
}
