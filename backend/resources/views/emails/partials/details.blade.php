{{-- Label/value details table. Pass $rows (label => value) and optionally $mb (bottom margin, px). --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
  style="margin:0 0 {{ $mb ?? 28 }}px; border:1px solid #E8E4DC; border-radius:8px; border-collapse:separate;">
  @foreach ($rows as $label => $value)
    <tr>
      <td style="padding:10px 14px; font-size:13px; line-height:20px; color:#79746A; width:120px;
        {{ $loop->last ? '' : 'border-bottom:1px solid #E8E4DC;' }}">
        {{ $label }}
      </td>
      <td style="padding:10px 14px; font-size:14px; line-height:20px; color:#24231F; font-weight:bold;
        {{ $loop->last ? '' : 'border-bottom:1px solid #E8E4DC;' }}">
        {{ $value }}
      </td>
    </tr>
  @endforeach
</table>
