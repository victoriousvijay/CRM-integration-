<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Properties')) - {{ \App\Support\Brand::name() }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
</head>
<body class="d-flex flex-column">
@include('layouts._splash')
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js" defer></script>

<header class="navbar navbar-expand-md navbar-light d-print-none border-bottom bg-white">
    <div class="container-xl">
        <h1 class="navbar-brand navbar-brand-autodark mb-0 me-3">
            <a href="{{ route('broker.index') }}" class="text-decoration-none">
                @include('layouts._brand', ['size' => '1.4rem'])
            </a>
        </h1>

        <nav class="d-flex gap-1 ms-2">
            <a href="{{ route('broker.index') }}"
               class="btn btn-sm {{ request()->routeIs('broker.index') || request()->routeIs('broker.show') ? 'btn-primary' : 'btn-ghost-secondary' }}">
                {{ __('Properties') }}
            </a>
            <a href="{{ route('broker.leads.index') }}"
               class="btn btn-sm {{ request()->routeIs('broker.leads.*') ? 'btn-primary' : 'btn-ghost-secondary' }}">
                {{ __('My Enquiries') }}
            </a>
        </nav>

        <div class="ms-auto d-flex align-items-center gap-3">
            <span class="text-secondary d-none d-sm-inline">{{ auth()->user()->name }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('Sign out') }}</button>
            </form>
        </div>
    </div>
</header>

<div class="page-wrapper">
    <div class="page-body">
        <div class="container-xl">
            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @yield('content')

            @include('layouts._platform-footer')
        </div>
    </div>
</div>
</body>
</html>
