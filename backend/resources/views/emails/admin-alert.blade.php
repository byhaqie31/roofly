@extends('emails.layout')

{{-- Internal heads-up to super admins (AdminNewEnquiry, AdminNewOwnerSignup, AdminNewSupportEnquiry). --}}
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

  @include('emails.partials.details', ['rows' => $rows])
@endsection

@section('closing', 'Follow up from the admin back office.')
@section('reason', "You're receiving this because you're a Roofly super admin.")
