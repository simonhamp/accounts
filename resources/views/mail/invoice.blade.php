{!! $greeting !!}

{!! $intro !!}

@foreach ($details as $label => $value)
{!! $label !!}: {!! $value !!}
@endforeach

{!! $issuedBy !!}
