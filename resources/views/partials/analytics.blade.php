@php $measurementId = setting('general.google_analytics_id'); @endphp

{{-- Not on password reset: that URL holds the reset token and email, which must not reach Google. --}}
@if ($measurementId && ! request()->routeIs('password.reset'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $measurementId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @js($measurementId));
    </script>
@endif
