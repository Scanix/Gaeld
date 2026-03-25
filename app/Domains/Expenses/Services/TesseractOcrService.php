<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\DTOs\ReceiptOcrResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class TesseractOcrService implements ReceiptOcrInterface
{
    private string $binary;

    private string $lang;

    public function __construct()
    {
        $this->binary = config('services.ocr.tesseract_binary', 'tesseract');
        $this->lang = config('services.ocr.tesseract_lang', 'deu+fra+eng');
    }

    public function extract(string $imagePath): ReceiptOcrResult
    {
        $rawText = $this->runTesseract($imagePath);

        if ($rawText === '') {
            return new ReceiptOcrResult(rawText: '');
        }

        return new ReceiptOcrResult(
            rawText: $rawText,
            amount: $this->extractAmount($rawText),
            date: $this->extractDate($rawText),
            vendor: $this->extractVendor($rawText),
            confidence: null,
        );
    }

    private function runTesseract(string $imagePath): string
    {
        $result = Process::run([
            $this->binary,
            $imagePath,
            'stdout',
            '-l', $this->lang,
            '--psm', '6',
        ]);

        if (! $result->successful()) {
            Log::warning('Tesseract OCR failed', [
                'exitCode' => $result->exitCode(),
                'error' => $result->errorOutput(),
            ]);

            return '';
        }

        return trim($result->output());
    }

    public function extractAmount(string $text): ?float
    {
        // Look for total-like lines with amounts (CHF, EUR, Total, etc.)
        $patterns = [
            // "Total CHF 45.90" or "Total: 45.90" or "TOTAL 45,90"
            '/\b(?:total|totale|gesamt|summe|betrag|montant)\s*:?\s*(?:CHF|EUR|USD)?\s*(\d{1,6}[.,]\d{2})\b/iu',
            // "CHF 45.90" anywhere
            '/\b(?:CHF|EUR|USD)\s*(\d{1,6}[.,]\d{2})\b/i',
            // "45.90 CHF" anywhere
            '/\b(\d{1,6}[.,]\d{2})\s*(?:CHF|EUR|USD)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                // Take the last match (totals are usually at the bottom)
                $amountStr = end($matches[1]);
                $amountStr = str_replace(',', '.', $amountStr);

                return (float) $amountStr;
            }
        }

        // Fallback: find the largest number with 2 decimal places in the text
        if (preg_match_all('/\b(\d{1,6}[.,]\d{2})\b/', $text, $matches)) {
            $amounts = array_map(fn ($m) => (float) str_replace(',', '.', $m), $matches[1]);
            if (count($amounts) > 0) {
                return max($amounts);
            }
        }

        return null;
    }

    public function extractDate(string $text): ?string
    {
        $patterns = [
            // dd.mm.yyyy or dd/mm/yyyy
            '/\b(\d{2})[.\/-](\d{2})[.\/-](20\d{2})\b/' => fn ($m) => "{$m[3]}-{$m[2]}-{$m[1]}",
            // yyyy-mm-dd
            '/\b(20\d{2})-(\d{2})-(\d{2})\b/' => fn ($m) => "{$m[1]}-{$m[2]}-{$m[3]}",
            // dd.mm.yy or dd/mm/yy
            '/\b(\d{2})[.\/-](\d{2})[.\/-](\d{2})\b/' => fn ($m) => '20' . "{$m[3]}-{$m[2]}-{$m[1]}",
        ];

        foreach ($patterns as $pattern => $formatter) {
            if (preg_match($pattern, $text, $match)) {
                $date = $formatter($match);

                // Validate the date
                $parts = explode('-', $date);
                if (count($parts) === 3 && checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0])) {
                    return $date;
                }
            }
        }

        return null;
    }

    public function extractVendor(string $text): ?string
    {
        $lines = array_filter(
            array_map('trim', explode("\n", $text)),
            fn ($line) => $line !== ''
        );

        if (count($lines) === 0) {
            return null;
        }

        // Take the first non-empty line that looks like a name (not a number/date)
        foreach (array_slice($lines, 0, 5) as $line) {
            // Skip lines that are purely numeric, dates, or very short
            if (preg_match('/^\d+$/', $line)) {
                continue;
            }
            if (preg_match('/^\d{2}[.\/-]\d{2}[.\/-]\d{2,4}$/', $line)) {
                continue;
            }
            if (mb_strlen($line) < 3) {
                continue;
            }

            // Clean up the line (remove excess whitespace)
            return preg_replace('/\s+/', ' ', $line);
        }

        return null;
    }
}
