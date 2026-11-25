<?php

use App\Domains\Accounting\Proof\PdfArchivalConverter;
use App\Domains\Accounting\Proof\ProcessRunner;
use Illuminate\Contracts\Console\Kernel;

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$directory = sys_get_temp_dir().'/gaeld-image-proof-'.bin2hex(random_bytes(12));
mkdir($directory, 0700);
$pdf = new Dompdf\Dompdf;
$pdf->loadHtml('<h1>R3 image acceptance</h1><p>CHF 100000.00</p>');
$pdf->render();
file_put_contents($directory.'/source.pdf', $pdf->output());
$converter = new PdfArchivalConverter(new ProcessRunner);
$result = $converter->convert($directory.'/source.pdf', $directory.'/archival.pdf');
if ($result['valid'] !== true || $result['profile'] !== 'PDF/A-2b') {
    exit(1);
}
echo "Real PDF/A-2b image conversion validated\n";
