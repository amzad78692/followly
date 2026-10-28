@php $seoTitle = isset($title) ? $title.' · '.config('app.name') : config('app.name').' · Follow Once. Track Everywhere.'; @endphp

<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{{ $seoTitle }}</title>

@include('partials.seo')

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />

@vite(['resources/css/app.css', 'resources/js/app.js'])
