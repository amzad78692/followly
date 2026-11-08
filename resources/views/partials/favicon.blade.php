@if ($favicon = setting('general.favicon'))
    <link rel="icon" href="{{ asset('storage/'.$favicon) }}">
    <link rel="apple-touch-icon" href="{{ asset('storage/'.$favicon) }}">
@endif
