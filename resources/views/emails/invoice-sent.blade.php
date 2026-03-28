@component('mail::message')
# {{ __('mail.invoice_greeting') }}

@if($body)
{{ $body }}
@else
{{ __('mail.invoice_body', [
    'number' => $invoice->number,
    'total' => number_format((float) $invoice->total, 2, '.', "'"),
    'currency' => $invoice->currency,
    'due_date' => $invoice->due_date->format('d.m.Y'),
]) }}
@endif

@component('mail::table')
| | |
|:---|---:|
| **{{ __('mail.invoice_number_label') }}** | {{ $invoice->number }} |
| **{{ __('mail.invoice_amount_label') }}** | {{ $invoice->currency }} {{ number_format((float) $invoice->total, 2, '.', "'") }} |
| **{{ __('mail.invoice_due_date_label') }}** | {{ $invoice->due_date->format('d.m.Y') }} |
@endcomponent

{{ __('mail.invoice_closing') }}

{{ __('mail.invoice_regards') }},<br>
{{ $organization->name }}
@endcomponent
