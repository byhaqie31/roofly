@foreach (\App\Support\EmailFooter::links() as $label => $href)
{!! $label !!}: {!! \Illuminate\Support\Str::after($href, 'mailto:') !!}
@endforeach
{!! \App\Support\EmailFooter::operatorLine() !!}
