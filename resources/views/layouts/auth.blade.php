<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta http-equiv="X-UA-Compatible" content="ie=edge"/>
    <title>@yield('title', __('Login')) - {{ \App\Support\Brand::name() }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    @stack('styles')
</head>
<body class="d-flex flex-column">
@include('layouts._splash')
    <script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js" defer></script>
    <main class="page page-center">
        <div class="container container-tight py-4">
            <div class="text-center mb-4">
                <a href="{{ url('/') }}" class="text-decoration-none">
                    <img src="{{ \App\Support\Brand::platformLogo() }}" alt="{{ \App\Support\Brand::platformName() }}"
                         style="max-height:150px;max-width:260px;">
                </a>
            </div>
            @yield('content')

            @include('layouts._platform-footer')
        </div>
    </main>
    @stack('scripts')
</body>
</html>
