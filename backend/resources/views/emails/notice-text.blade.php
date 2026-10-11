@if ($greeting)
Hi {!! $greeting !!},

@endif
@foreach ($paragraphs as $paragraph)
{!! $paragraph !!}

@endforeach
@if ($rows)
@foreach ($rows as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach

@endif
@if ($note)
{!! $note['label'] !!}: "{!! $note['text'] !!}"

@endif
@if ($ctaUrl)
{!! $ctaLabel !!}: {!! $ctaUrl !!}

@endif
@if ($malay)
{!! $malay !!}

@endif
@if ($closing)
{!! $closing !!}

@endif
Warm regards,
The Roofly Team
@foreach ($fineprint as $line)

{!! $line !!}
@endforeach

--
Roofly.my · Property management, reimagined.
{!! $reason !!}
@include('emails.partials.legal-footer-text')
