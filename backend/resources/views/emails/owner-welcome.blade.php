@extends('emails.layout')

@section('title', 'Welcome to Roofly')
@section('tag', 'WELCOME ABOARD')
@section('pill', 'YOUR ACCOUNT IS READY')

@section('headline')
  Welcome to<br>
  <span style="color:#C05C36;">Roofly.</span>
@endsection

@section('body')
  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Hi {{ $firstName }},
  </p>

  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Your Roofly account is set up and ready to go. Thank you for signing up!
  </p>

  <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
    Add your first property, invite your tenants and start collecting rent,
    with agreements, payments and maintenance all in one place.
  </p>

  <p style="margin:0 0 28px; font-size:14px; line-height:22px; color:#79746A;">
    Akaun Roofly anda sudah sedia. Tambah hartanah pertama anda, jemput penyewa
    dan mula kutip sewa, semuanya di satu tempat.
  </p>
@endsection

@section('closing', "We're glad to have you on board.")
@section('reason', "You're receiving this email because you created a Roofly account.")
