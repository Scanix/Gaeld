<?php

namespace App\Domains\Payroll\Mail;

use App\Domains\Payroll\Models\SalarySlip;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SalarySlipReadyMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public readonly SalarySlip $slip,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.salary_slip_subject', ['period' => $this->slip->month_label]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.salary-slip-ready',
            with: [
                'slip' => $this->slip,
                'employeeSnapshot' => $this->slip->employeeDocumentData(),
            ],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $slip = $this->slip;
        $employeeData = $slip->employeeDocumentData();

        $pdf = Pdf::loadView('exports.salary-slip', [
            'slip' => $slip,
            'employeeData' => $employeeData,
        ])
            ->setPaper('A4', 'portrait')
            ->output();

        return [
            Attachment::fromData(
                fn (): string => $pdf,
                "salary-slip-{$employeeData['last_name']}-{$slip->period_year}-{$slip->period_month}.pdf",
            )->withMime('application/pdf'),
        ];
    }
}
