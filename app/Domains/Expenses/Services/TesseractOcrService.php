<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\DTOs\ReceiptOcrResult;
use App\Domains\Expenses\Exceptions\OcrProcessException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Receipt OCR implementation using the Tesseract binary.
 *
 * Extracts text from receipt images and attempts to parse
 * vendor name, date, total amount, and currency.
 */
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
        $processedPath = $this->preprocessImage($imagePath);

        try {
            ['text' => $rawText, 'confidence' => $confidence] = $this->runTesseract($processedPath ?? $imagePath);
        } finally {
            if ($processedPath !== null && file_exists($processedPath)) {
                @unlink($processedPath);
            }
        }

        if ($rawText === '') {
            return new ReceiptOcrResult(rawText: '');
        }

        $normalized = $this->normalizeText($rawText);

        return new ReceiptOcrResult(
            rawText: $rawText,
            amount: $this->extractAmount($normalized),
            date: $this->extractDate($normalized),
            vendor: $this->extractVendor($normalized),
            vat: $this->extractVat($normalized),
            confidence: $confidence,
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Image Preprocessing
    // ──────────────────────────────────────────────────────────────

    /**
     * Pre-process the image for better OCR accuracy.
     * Scales down large images, converts to grayscale, and boosts contrast.
     * Returns path to the temp file, or null on failure (graceful degradation).
     */
    private function preprocessImage(string $imagePath): ?string
    {
        if (! function_exists('imagecreatefromjpeg')) {
            return null;
        }

        try {
            $info = @getimagesize($imagePath);
            if ($info === false) {
                return null;
            }

            $image = match ($info['mime']) {
                'image/jpeg' => @imagecreatefromjpeg($imagePath),
                'image/png' => @imagecreatefrompng($imagePath),
                'image/gif' => @imagecreatefromgif($imagePath),
                default => false,
            };

            if ($image === false) {
                return null;
            }

            // Scale down to max 1800px on the longest side to keep Tesseract fast
            $w = imagesx($image);
            $h = imagesy($image);
            $maxSide = 1800;
            if ($w > $maxSide || $h > $maxSide) {
                $scale = $maxSide / max($w, $h);
                $image = imagescale($image, (int) ($w * $scale), (int) ($h * $scale));
                if ($image === false) {
                    return null;
                }
            }

            imagefilter($image, IMG_FILTER_GRAYSCALE);
            imagefilter($image, IMG_FILTER_CONTRAST, -25);

            $tmpPath = sys_get_temp_dir().'/gaeld_ocr_'.uniqid().'.png';
            if (! imagepng($image, $tmpPath)) {
                imagedestroy($image);

                return null;
            }

            imagedestroy($image);

            return $tmpPath;
        } catch (\Throwable) {
            return null;
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Tesseract Process
    // ──────────────────────────────────────────────────────────────

    /**
     * Run Tesseract with TSV output to obtain both the extracted text and
     * per-word confidence scores.
     *
     * @return array{text: string, confidence: ?float}
     */
    private function runTesseract(string $imagePath): array
    {
        $result = Process::run([
            $this->binary,
            $imagePath,
            'stdout',
            '-l', $this->lang,
            '--oem', '3',
            '--psm', '4',
            'tsv',
        ]);

        if (! $result->successful()) {
            Log::warning('Tesseract OCR failed', [
                'exitCode' => $result->exitCode(),
                'error' => $result->errorOutput(),
            ]);

            throw OcrProcessException::processFailed(
                $result->exitCode(),
                $result->errorOutput(),
            );
        }

        return $this->parseTsvOutput(trim($result->output()));
    }

    /**
     * Parse Tesseract TSV output into reconstructed text and average confidence.
     *
     * TSV columns (0-indexed): level, page_num, block_num, par_num, line_num,
     *   word_num, left, top, width, height, conf, text
     * Level 5 rows are words; conf is -1 for structural rows.
     *
     * @return array{text: string, confidence: ?float}
     */
    private function parseTsvOutput(string $tsv): array
    {
        $lines = explode("\n", $tsv);
        array_shift($lines); // discard header row

        /** @var array<string, list<string>> $wordsByLine key = "block-par-line" */
        $wordsByLine = [];
        $confidences = [];
        $lastBlock = null;
        $lineKeys = []; // ordered list to detect block boundaries

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $cols = explode("\t", $line);
            if (count($cols) < 12 || (int) $cols[0] !== 5) {
                continue; // only word-level rows
            }

            $blockNum = $cols[2];
            $parNum = $cols[3];
            $lineNum = $cols[4];
            $conf = (int) $cols[10];
            $word = rtrim($cols[11]);

            if ($word === '') {
                continue;
            }

            $key = "{$blockNum}-{$parNum}-{$lineNum}";

            if (! isset($wordsByLine[$key])) {
                $wordsByLine[$key] = [];
                $lineKeys[] = ['key' => $key, 'block' => $blockNum];
            }

            $wordsByLine[$key][] = $word;

            if ($conf >= 0) {
                $confidences[] = $conf;
            }
        }

        // Reconstruct text, inserting blank lines between blocks
        $textParts = [];
        foreach ($lineKeys as $i => $meta) {
            if ($i > 0 && $meta['block'] !== $lineKeys[$i - 1]['block']) {
                $textParts[] = '';
            }
            $textParts[] = implode(' ', $wordsByLine[$meta['key']]);
        }

        $text = implode("\n", $textParts);
        $confidence = count($confidences) > 0
            ? round(array_sum($confidences) / count($confidences) / 100, 4)
            : null;

        return ['text' => $text, 'confidence' => $confidence];
    }

    // ──────────────────────────────────────────────────────────────
    //  Text Normalization
    // ──────────────────────────────────────────────────────────────

    /**
     * Collapse spaced-out characters that Tesseract sometimes produces.
     * e.g. "M I G R O S" → "MIGROS"
     */
    private function normalizeText(string $text): string
    {
        $lines = explode("\n", $text);

        $normalized = array_map(function (string $line): string {
            $tokens = preg_split('/\s+/', trim($line));
            if ($tokens === false) {
                return $line;
            }
            $tokens = array_filter($tokens, fn ($t) => $t !== '');

            // If every token is a single character and there are at least 3, collapse
            if (count($tokens) >= 3 && array_reduce($tokens, fn ($carry, $t) => $carry && mb_strlen($t) === 1, true)) {
                return implode('', $tokens);
            }

            return $line;
        }, $lines);

        return implode("\n", $normalized);
    }

    // ──────────────────────────────────────────────────────────────
    //  Text Parsing
    // ──────────────────────────────────────────────────────────────

    public function extractAmount(string $text): ?float
    {
        // Look for total-like lines with amounts (CHF, EUR, Total, etc.)
        $patterns = [
            // "Total CHF 45.90" or "Total: 45.90" or "TOTAL 45,90"
            // Also matches Swiss/French: "à payer", "zu zahlen", "net à payer"
            '/\b(?:total|totale|gesamt|summe|betrag|montant|à payer|a payer|net à payer|zu zahlen|à régler)\s*:?\s*(?:CHF|EUR|USD)?\s{0,3}(\d{1,6}[.,]\d{2})\b/iu',
            // "CHF 45.90" anywhere
            '/\b(?:CHF|EUR|USD)\s{0,3}(\d{1,6}[.,]\d{2})\b/i',
            // "45.90 CHF" anywhere
            '/\b(\d{1,6}[.,]\d{2})\s{0,3}(?:CHF|EUR|USD)\b/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                // Take the last match (totals are usually at the bottom)
                $amountStr = end($matches[1]);
                $amountStr = str_replace(',', '.', $amountStr);

                return (float) $amountStr;
            }
        }

        // Fallback: find the last number with 2 decimal places in the text
        // (end() = last = closest to bottom = most likely to be the total)
        if (preg_match_all('/\b(\d{1,6}[.,]\d{2})\b/', $text, $matches)) {
            if (count($matches[1]) > 0) {
                $lastMatch = end($matches[1]);

                return (float) str_replace(',', '.', $lastMatch);
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
            '/\b(\d{2})[.\/-](\d{2})[.\/-](\d{2})\b/' => fn ($m) => '20'."{$m[3]}-{$m[2]}-{$m[1]}",
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
            // Skip lines made entirely of digits, spaces, and symbols (barcodes/totals)
            if (preg_match('/^[\d\s\W]+$/u', $line)) {
                continue;
            }

            // Clean up the line (remove excess whitespace)
            return preg_replace('/\s+/', ' ', $line);
        }

        return null;
    }

    public function extractVat(string $text): ?float
    {
        // Match VAT/MwSt/TVA/IVA lines followed by (or preceded by) an amount.
        // Covers patterns like:
        //   "MwSt 7.7% CHF 3.25"   "TVA 8.1%: 4,10"   "VAT: 3.25"
        //   "3.25 MwSt"            "MWST 2.5% 1.50"
        $patterns = [
            // Keyword then optional rate then optional currency then amount
            '/\b(?:mwst|mwst\.|mehrwertsteuer|tva|tva\.|tvac|iva|vat|gst)\s*(?:\d{1,2}[.,]\d{1,2}\s*%\s*)?:?\s*(?:CHF|EUR|USD)?\s{0,3}(\d{1,5}[.,]\d{2})\b/iu',
            // Amount then keyword
            '/\b(\d{1,5}[.,]\d{2})\s{0,3}(?:CHF|EUR|USD)?\s{0,3}(?:mwst|tva|iva|vat|gst)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                $amountStr = end($matches[1]);

                return (float) str_replace(',', '.', $amountStr);
            }
        }

        return null;
    }
}
