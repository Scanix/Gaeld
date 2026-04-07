<x-mail::message>
# {{ __('mail.org_export_greeting', ['name' => $user->name]) }}

{{ __('mail.org_export_body') }}

<x-mail::button :url="$downloadUrl">
{{ __('mail.org_export_button') }}
</x-mail::button>

{{ __('mail.org_export_expiry') }}

{{ __('mail.org_export_regards') }}<br>
{{ config('app.name') }}
</x-mail::message>
