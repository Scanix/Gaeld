@props(['url'])
<tr>
<td class="header" style="padding: 25px 0; text-align: center;">
<a href="{{ $url }}" style="color: #171717; font-size: 19px; font-weight: bold; text-decoration: none; display: inline-block;">
@if (is_file(public_path('logo-square.svg')))
<img src="{{ asset('logo-square.svg') }}" class="logo" width="48" height="48" alt="{{ config('app.name') }}" style="height: 48px; width: 48px; max-height: 48px; border-radius: 8px; margin-bottom: 10px; display: block; margin-left: auto; margin-right: auto;">
@endif
{{ $slot }}
</a>
</td>
</tr>
