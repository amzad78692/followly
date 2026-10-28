<!DOCTYPE html>
<html lang="{{ $lang }}" dir="{{ $direction }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ $title ?? setting("general.website_name.$lang") }}</title>

    <script>
        window.lang = "{{ $lang }}";
    </script>

    <!-- Favicon -->
    <link rel="apple-touch-icon" href="{{ asset('storage/' . setting('general.favicon')) }}">
    <link rel="shortcut icon" href="{{ asset('storage/' . setting('general.favicon')) }}" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-white font-sans text-zinc-900 antialiased" @if ($lang == 'ar') dir="rtl" @endif>
    {{ $slot }}
    <script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
    <script>
        window.addEventListener('show-toast', event => {
            const {
                message,
                type = 'success'
            } = event.detail?.at(0);
            const htmlNode = document.createElement('span');
            htmlNode.innerHTML = message;
            Toastify({
                node: htmlNode,
                duration: 3000,
                close: true,
                gravity: "top",
                position: "right",
                backgroundColor: type === 'success' ? "green" : "red",
            }).showToast();
        });
    </script>
    @livewireScripts
    @fluxScripts
</body>

</html>
