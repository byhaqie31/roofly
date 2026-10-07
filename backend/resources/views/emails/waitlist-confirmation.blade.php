@extends('emails.layout')

@section('title', "You're on the Roofly waitlist")
@section('tag', 'WAITLIST CONFIRMED')
@section('pill', "YOU'RE ON THE LIST")

@section('headline')
  You're on the<br>
  Roofly <span style="color:#C05C36;">waitlist.</span>
@endsection

@section('body')
  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Hi there,
  </p>

  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Thanks for joining! Your spot on the Roofly waitlist is confirmed.
  </p>

  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    We're getting things ready and will email you as soon as Roofly is ready
    to welcome you. There's nothing else you need to do for now. In the
    meantime, take the demo for a spin.
  </p>

  <p style="margin:0 0 28px; font-size:14px; line-height:22px; color:#79746A;">
    Anda kini dalam senarai menunggu Roofly. Kami akan e-mel anda sebaik sahaja
    Roofly sedia untuk anda. Buat masa ini, tiada apa-apa lagi yang perlu anda lakukan.
  </p>
@endsection

@section('closing', 'Thanks for being part of our journey from the start.')
@section('reason', "You're receiving this email because you joined the Roofly waitlist.")
