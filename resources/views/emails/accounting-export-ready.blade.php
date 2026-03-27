<x-mail::message>
# {{ __('mail.accounting_export_greeting', ['name' => $user->name]) }}

{{ __('mail.accounting_export_body', ['year' => $fiscalYear]) }}

<x-mail::button :url="$downloadUrl">
{{ __('mail.accounting_export_button') }}
</x-mail::button>

{{ __('mail.accounting_export_expiry') }}

{{ config('app.name') }}
</x-mail::message>
