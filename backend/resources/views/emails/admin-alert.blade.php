@extends('emails.layout')

{{-- Internal heads-up to super admins (AdminNewEnquiry, AdminNewOwnerSignup). --}}
@section('title', $subject)
@section('tag', 'ADMIN ALERT')
@section('pill', $pill)

@section('headline')
  {{ $headlineLead }}<br>
  <span style="color:#C05C36;">{{ $headlineAccent }}</span>
@endsection

@section('body')
  <p style="margin:0 0 20px; font-size:16px; line-height:26px; color:#4F4C45;">
    {{ $intro }}
  </p>

  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
    style="margin:0 0 28px; border:1px solid #E8E4DC; border-radius:8px; border-collapse:separate;">
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
@endsection

@section('closing', 'Follow up from the admin back office.')
@section('reason', "You're receiving this because you're a Roofly super admin.")
