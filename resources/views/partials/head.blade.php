@php $seoTitle = isset($title) ? $title.' · '.config('app.name') : config('app.name').' · Follow Once. Track Everywhere.'; @endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{{ $seoTitle }}</title>

@include('partials.seo')
@include('partials.analytics')

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=dm-sans:400,500,600,700|space-grotesk:600,700" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
