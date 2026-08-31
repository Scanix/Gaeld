<x-mail::message>
# {{ __('mail.salary_slip_greeting', ['name' => $employeeSnapshot['first_name']]) }}

{{ __('mail.salary_slip_body', ['period' => $slip->month_label]) }}

{{ __('mail.salary_slip_attachment') }}

{{ __('mail.salary_slip_regards') }},<br>
{{ $slip->organization->name ?? config('app.name') }}
</x-mail::message>
