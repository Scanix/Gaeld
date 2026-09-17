@component('mail::message')
# {{ __('mail.reminder_greeting', ['name' => $invoice->customer->name]) }}

{{ __('mail.reminder_body', [
    'number' => $invoice->number,
    'organization' => $organization->name,
    'amount' => number_format((float) $amountDue, 2, '.', "'"),
    'currency' => $invoice->currency,
    'due_date' => $invoice->due_date->format('d.m.Y'),
    'days_overdue' => $daysOverdue,
]) }}

@component('mail::table')
| | |
|:---|---:|
| **{{ __('mail.reminder_invoice_number') }}** | {{ $invoice->number }} |
| **{{ __('mail.reminder_amount') }}** | {{ $invoice->currency }} {{ number_format((float) $amountDue, 2, '.', "'") }} |
| **{{ __('mail.reminder_due_date') }}** | {{ $invoice->due_date->format('d.m.Y') }} |
| **{{ __('mail.reminder_days_overdue') }}** | {{ $daysOverdue }} |
@endcomponent

{{ __('mail.reminder_closing') }}

@if($organization->contact_email)
{{ __('mail.reminder_contact', ['email' => $organization->contact_email]) }}
@endif

{{ __('mail.reminder_regards') }},<br>
{{ $organization->name }}
@endcomponent
