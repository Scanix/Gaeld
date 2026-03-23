@props(['url'])
<tr>
<td class="header" style="padding: 25px 0; text-align: center;">
<a href="{{ $url }}" style="color: #18181b; font-size: 19px; font-weight: bold; text-decoration: none;">
{!! $slot !!}
</a>
</td>
</tr>
