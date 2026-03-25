<x-mail::message>
# {{ __('mail.data_export_greeting', ['name' => $user->name]) }}

{{ __('mail.data_export_body') }}

<x-mail::button :url="$downloadUrl">
{{ __('mail.data_export_button') }}
</x-mail::button>

{{ __('mail.data_export_expiry') }}

{{ __('mail.data_export_regards') }}<br>
{{ config('app.name') }}
</x-mail::message>
