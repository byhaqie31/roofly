@extends('emails.layout')

@section('title', 'Your Roofly invitation is here')
@section('tag', 'WELCOME ABOARD')
@section('pill', 'YOUR INVITATION HAS ARRIVED')

@section('headline')
  Your Roofly<br>
  invitation is <span style="color:#C05C36;">here.</span>
@endsection

@section('body')
  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Hi there,
  </p>

  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Congratulations! As part of our waitlist, you're now invited
    to sign up for Roofly.
  </p>

  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Thank you for your patience and for being with us from
    the beginning. We're excited to welcome you!
  </p>

  <p style="margin:0 0 28px; font-size:14px; line-height:22px; color:#79746A;">
    Tahniah! Sebagai sebahagian daripada senarai menunggu kami, anda kini
    dijemput untuk mendaftar di Roofly.
  </p>
@endsection

@section('closing', 'We look forward to having you on board.')
@section('reason', "You're receiving this email because you joined the Roofly waitlist.")
