@extends('emails.layout')

{{-- Transactional emails (agreements, payments, invites, password reset, account notices).
     Built by App\Support\NoticeMail — see it for the data keys. --}}
@section('title', $subject)
@section('tag', $tag)
@if ($pill)
  @section('pill', $pill)
@endif

@section('headline')
  {{ $headlineLead }}<br>
  <span style="color:#C05C36;">{{ $headlineAccent }}</span>
@endsection

@section('body')
  @if ($greeting)
    <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
      Hi {{ $greeting }},
    </p>
  @endif

  @foreach ($paragraphs as $paragraph)
    <p style="margin:0 0 16px; font-size:16px; line-height:26px; color:#4F4C45;">
      {{ $paragraph }}
    </p>
  @endforeach

  @if ($rows)
    @include('emails.partials.details', ['rows' => $rows, 'mb' => 16])
  @endif

  @if ($note)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
      <tr>
        <td style="background:#F8F6EF; border-left:3px solid #DD7047; border-radius:0 8px 8px 0; padding:12px 16px;">
          <div style="font-size:10px; font-weight:bold; letter-spacing:1.5px; color:#79746A;">
            {{ strtoupper($note['label']) }}
          </div>
          <div style="margin-top:4px; font-size:15px; line-height:24px; color:#24231F;">
            {!! nl2br(e($note['text'])) !!}
          </div>
        </td>
      </tr>
    </table>
  @endif

  @if ($malay)
    <p style="margin:0 0 16px; font-size:14px; line-height:22px; color:#79746A;">
      {{ $malay }}
    </p>
  @endif

  @if ($ctaUrl)
    {{-- Tops the last block's 16px up to the 28px the branded emails leave above the button. --}}
    <div style="height:12px; line-height:12px; font-size:12px;">&nbsp;</div>
  @endif
@endsection

@if ($closing)
  @section('closing', $closing)
@endif

@if ($fineprint)
  @section('fineprint')
    @foreach ($fineprint as $line)
      <p style="margin:0 0 10px;">{{ $line }}</p>
    @endforeach
  @endsection
@endif

@section('reason', $reason)
