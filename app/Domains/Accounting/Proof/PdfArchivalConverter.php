<?php

namespace App\Domains\Accounting\Proof;

use DOMDocument;
use DOMXPath;
use RuntimeException;

class PdfArchivalConverter
{
    public function __construct(private readonly ProcessRunner $runner) {}

    /** @return array<string, mixed> */
    public function convert(string $source, string $destination): array
    {
        EvidenceFiles::required($source);
        if (file_exists($destination) || realpath($source) === realpath($destination)) {
            throw new RuntimeException('PDF/A conversion must create a new file, preserving the source.');
        }
        $icc = EvidenceFiles::required((string) config('archiving.pdfa.icc_profile'));
        $workspace = EvidenceFiles::temporary('gaeld-pdfa-');
        if (! unlink($workspace) || ! mkdir($workspace, 0700)) {
            throw new RuntimeException('Unable to create an isolated PDF/A conversion directory.');
        }
        $temporary = $workspace.'/converted.pdf';
        $definition = $workspace.'/profile.ps';
        $escapedIcc = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $icc);
        $postscript = "%!\n/ICCProfile ($escapedIcc) def\n[/_objdef {icc_PDFA} /type /stream /OBJ pdfmark\n[{icc_PDFA} << /N 3 >> /PUT pdfmark\n[{icc_PDFA} ICCProfile (r) file /PUT pdfmark\n[/_objdef {OutputIntent_PDFA} /type /dict /OBJ pdfmark\n[{OutputIntent_PDFA} << /Type /OutputIntent /S /GTS_PDFA1 /DestOutputProfile {icc_PDFA} /OutputConditionIdentifier (sRGB) >> /PUT pdfmark\n[{Catalog} << /OutputIntents [ {OutputIntent_PDFA} ] >> /PUT pdfmark\n";
        EvidenceFiles::writeOnce($definition, $postscript);
        try {
            $this->runner->run([(string) config('archiving.pdfa.ghostscript'), '-dSAFER', '-dBATCH', '-dNOPAUSE', '-dPDFA=2', '-dPDFACompatibilityPolicy=2', '-sDEVICE=pdfwrite', '-sColorConversionStrategy=RGB', '--permit-file-read='.$icc, '-sOutputFile='.$temporary, $definition, $source]);
            $report = $this->validate($temporary);
            EvidenceFiles::writeOnce($destination, EvidenceFiles::read($temporary));

            return ['profile' => 'PDF/A-2b', 'valid' => true, 'validator' => 'veraPDF', 'validation_report' => $report, 'source_sha256' => EvidenceFiles::hash($source), 'sha256' => EvidenceFiles::hash($destination)];
        } finally {
            @unlink($temporary);
            @unlink($definition);
            @rmdir($workspace);
        }
    }

    public function validate(string $path): string
    {
        $report = $this->runner->run([(string) config('archiving.pdfa.verapdf'), '--format', 'xml', '--flavour', '2b', EvidenceFiles::required($path)]);
        $xml = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $xml->loadXML($report, LIBXML_NONET) || $xml->doctype !== null) {
                throw new RuntimeException('veraPDF did not return a valid validation report.');
            }
            $reports = (new DOMXPath($xml))->query('//*[local-name()="validationReport"]');
            $validation = $reports === false ? null : $reports->item(0);
            if ($reports === false || $reports->length !== 1 || ! $validation instanceof \DOMElement || $validation->getAttribute('isCompliant') !== 'true' || ! str_contains(strtoupper($validation->getAttribute('profileName')), 'PDF/A-2B')) {
                throw new RuntimeException('The accounting report did not pass PDF/A-2b validation.');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $report;
    }
}
