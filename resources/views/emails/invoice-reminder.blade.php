@component('mail::message')
# {{ __('mail.reminder_greeting') }}

{{ __('mail.reminder_body', [
    'number' => $invoice->number,
    'total' => number_format((float) $invoice->total, 2, '.', "'"),
    'currency' => $invoice->currency,
    'due_date' => $invoice->due_date->format('d.m.Y'),
    'days_overdue' => $daysOverdue,
]) }}

@component('mail::table')
| | |
|:---|---:|
| **{{ __('mail.reminder_invoice_number') }}** | {{ $invoice->number }} |
| **{{ __('mail.reminder_amount') }}** | {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2, '.', "'") }} |
| **{{ __('mail.reminder_due_date') }}** | {{ $invoice->due_date->format('d.m.Y') }} |
| **{{ __('mail.reminder_days_overdue') }}** | {{ $daysOverdue }} |
@endcomponent

{{ __('mail.reminder_closing') }}

{{ __('mail.reminder_regards') }},<br>
{{ config('app.name') }}
@endcomponent
