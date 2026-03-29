@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
@php
$buttonColors = match($color) {
    'success', 'green' => '#16a34a',
    'error', 'red' => '#dc2626',
    default => '#33cc66',
};
$textColor = match($color) {
    'success', 'green', 'error', 'red' => '#ffffff',
    default => '#141414',
};
@endphp
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 30px auto; padding: 0; text-align: center; width: 100%;">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td style="background-color: {{ $buttonColors }}; border-radius: 6px;">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="-webkit-text-size-adjust: none; color: {{ $textColor }}; text-decoration: none; background-color: {{ $buttonColors }}; border-bottom: 8px solid {{ $buttonColors }}; border-left: 18px solid {{ $buttonColors }}; border-right: 18px solid {{ $buttonColors }}; border-top: 8px solid {{ $buttonColors }}; border-radius: 6px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold;">{!! $slot !!}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
