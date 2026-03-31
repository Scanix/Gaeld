<x-mail::message>
# {{ __('mail.chart_export_greeting', ['name' => $user->name]) }}

{{ __('mail.chart_export_body') }}

<x-mail::button :url="$downloadUrl">
{{ __('mail.chart_export_button') }}
</x-mail::button>

{{ __('mail.chart_export_expiry') }}

{{ config('app.name') }}
</x-mail::message>
