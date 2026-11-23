<?php

namespace App\Support;

use ZipArchive;

/** Reject DTDs before external documents reach the native XML parser. */
final class ImportedXml
{
    public static function document(string $xml): string
    {
        $encoding = self::byteEncoding($xml);
        $ebcdic = str_starts_with($xml, "\x4c\x6f\xa7\x94");
        $text = $ebcdic ? self::toUtf8($xml, 'IBM037') : $xml;
        if ($encoding !== null) {
            $text = self::toUtf8($xml, $encoding);
        }
        if (str_starts_with($text, "\xef\xbb\xbf")) {
            $text = substr($text, 3);
        }
        if (preg_match('/^<\?xml\s[^?]*?\sencoding\s*=\s*(["\x27])([^"\x27]+)\1/', $text, $declaration)) {
            $declared = strtoupper($declaration[2]);
            if ($encoding === null && ! in_array($declared, ['UTF-8', 'UTF8'], true)) {
                $text = self::toUtf8($xml, $declared);
            } elseif ($encoding !== null && ! in_array($declared, [$encoding, substr($encoding, 0, -2)], true)) {
                throw new InvalidImportedXml('The XML declaration conflicts with the byte encoding.');
            }
            // Keep the bytes inspected below and the bytes the native parser
            // interprets in the same encoding, including legacy bank exports.
            $text = preg_replace('/^(<\?xml\s[^?]*?\sencoding\s*=\s*)(["\x27])([^"\x27]+)\2/', '$1"UTF-8"', $text, 1);
        }
        if (! self::checkedProlog($text, false)) {
            throw new InvalidImportedXml('The XML document has no root element.');
        }

        return $text;
    }

    private static function toUtf8(string $text, string $encoding): string
    {
        // XML encoding names follow the EncName grammar and cannot contain iconv modifiers.
        if (! preg_match('/^[A-Z][A-Z0-9._-]*$/D', $encoding)) {
            throw new InvalidImportedXml('Invalid XML character encoding.');
        }
        $decoded = @iconv($encoding, 'UTF-8', $text);
        if ($decoded === false) {
            throw new InvalidImportedXml('Invalid XML character encoding.');
        }

        return $decoded;
    }

    public static function checkWorkbook(string $file): void
    {
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::RDONLY) !== true) {
            throw new InvalidImportedXml('The workbook could not be opened.');
        }
        try {
            // Workbook relationships can point to parts with arbitrary names.
            // Inspect the prolog of each part without unpacking worksheets or media.
            if ($zip->numFiles > 16384) {
                throw new InvalidImportedXml('The workbook has too many parts.');
            }
            $inspected = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                if (str_ends_with((string) $zip->getNameIndex($index), '/')) {
                    continue;
                }
                $stream = $zip->getStreamIndex($index);
                if ($stream === false) {
                    throw new InvalidImportedXml('A workbook part could not be read.');
                }
                try {
                    $prefix = '';
                    do {
                        $chunk = fread($stream, max(512, strlen($prefix)));
                        if ($chunk === false) {
                            throw new InvalidImportedXml('A workbook part could not be read.');
                        }
                        $inspected += strlen($chunk);
                        if ($inspected > 64 * 1024 * 1024) {
                            throw new InvalidImportedXml('The workbook has too much XML preamble data.');
                        }
                        $prefix .= $chunk;
                        if (self::checkedProlog($prefix, true)) {
                            break;
                        }
                        // Bound expansion of a compressed preamble before the first element.
                        if (strlen($prefix) >= 1048576) {
                            throw new InvalidImportedXml('A workbook XML preamble is too large.');
                        }
                    } while ($chunk !== '' && ! feof($stream));
                } finally {
                    fclose($stream);
                }
            }
        } finally {
            $zip->close();
        }
    }

    private static function byteEncoding(string $bytes): ?string
    {
        return match (true) {
            str_starts_with($bytes, "\x00\x00\xfe\xff"), preg_match('/^\x00\x00\x00[<\x09\x0a\x0d ]/', $bytes) === 1 => 'UTF-32BE',
            str_starts_with($bytes, "\xff\xfe\x00\x00"), preg_match('/^[<\x09\x0a\x0d ]\x00\x00\x00/', $bytes) === 1 => 'UTF-32LE',
            str_starts_with($bytes, "\xfe\xff"), preg_match('/^\x00[<\x09\x0a\x0d ]/', $bytes) === 1 => 'UTF-16BE',
            str_starts_with($bytes, "\xff\xfe"), preg_match('/^[<\x09\x0a\x0d ]\x00/', $bytes) === 1 => 'UTF-16LE',
            default => null,
        };
    }

    /** Return false when more bytes are needed to identify the first element. */
    private static function checkedProlog(string $bytes, bool $allowNonXml): bool
    {
        // XML Appendix F defines these initial Unicode byte signatures. Decode
        // only the inspection copy; the original bytes go to the normal reader.
        $encoding = self::byteEncoding($bytes);
        if (str_starts_with($bytes, "\x4c\x6f\xa7\x94") || str_starts_with($bytes, "\x00\x00\xff\xfe")
            || str_starts_with($bytes, "\xfe\xff\x00\x00") || str_starts_with($bytes, "\x00\x00<\x00") || str_starts_with($bytes, "\x00<\x00\x00")) {
            throw new InvalidImportedXml('Unsupported XML byte encoding.');
        }
        $text = $encoding === null ? $bytes : mb_convert_encoding($bytes, 'UTF-8', $encoding);
        if ($text === false) {
            throw new InvalidImportedXml('Invalid XML character encoding.');
        }
        $offset = str_starts_with($text, "\xef\xbb\xbf") ? 3 : 0;
        $atStart = true;
        while (true) {
            $offset += strspn($text, " \t\r\n", $offset);
            $remaining = strlen($text) - $offset;
            if ($remaining < 2) {
                return false;
            }
            if ($text[$offset] !== '<') {
                if ($allowNonXml && $atStart && $encoding === null) {
                    return true;
                }
                throw new InvalidImportedXml('Invalid XML preamble.');
            }
            $token = $text[$offset + 1];
            if ($token === '?') {
                $end = strpos($text, '?>', $offset + 2);
                if ($end === false) {
                    return false;
                }
                $instruction = substr($text, $offset, $end - $offset);
                if (preg_match('/^<\?xml\s/', $instruction) && preg_match('/\sencoding\s*=\s*(["\x27])([^"\x27]+)\1/', $instruction, $match)) {
                    $declared = strtoupper($match[2]);
                    $compatible = $encoding === null
                        ? preg_match('/^(?:UTF-?8|US-ASCII|ASCII|ISO-8859-[0-9]+|WINDOWS-125[0-8])$/D', $declared) === 1
                        : in_array($declared, [$encoding, substr($encoding, 0, -2)], true);
                    if (! $compatible) {
                        throw new InvalidImportedXml('Unsupported XML declaration encoding.');
                    }
                }
                $offset = $end + 2;
            } elseif ($token === '!') {
                // Comments are the only markup declarations permitted in a DTD-free prolog.
                if ($remaining < 4 && str_starts_with('<!--', substr($text, $offset))) {
                    return false;
                }
                if (substr($text, $offset, 4) !== '<!--') {
                    throw new InvalidImportedXml('Document type declarations are not accepted.');
                }
                $end = strpos($text, '-->', $offset + 4);
                if ($end === false) {
                    return false;
                }
                $offset = $end + 3;
            } else {
                return true; // The native reader handles ordinary element syntax and content.
            }
            $atStart = false;
        }
    }
}
