{{--
    Search and share tags. Pages pass these through Livewire's layoutData():
    description, image (an absolute URL), canonical, ogType and jsonLd (an array).
--}}
@php
    $seoTitle ??= config('app.name');
    $description ??= 'Follow your favourite creators once and see their posts from YouTube, Instagram, TikTok and X in one feed.';
    $canonical ??= url()->current();
@endphp

<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">

<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
@if ($image ?? null)
    <meta property="og:image" content="{{ $image }}">
@endif

<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $description }}">
@if ($image ?? null)
    <meta name="twitter:image" content="{{ $image }}">
@endif

@if ($jsonLd ?? null)
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endif
