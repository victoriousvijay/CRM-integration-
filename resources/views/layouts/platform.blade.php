<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Platform')) - {{ \App\Support\Brand::platformName() }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/css/tabler.min.css">
    <style>
        /* The console is the product speaking as itself, so it is deliberately
           not the client-facing CRM chrome: darker, narrower, no CRM navigation. */
        .pa-header { background: #0b1118; color: #e8eef7; }
        .pa-header a { color: #e8eef7; }
        .pa-header .nav-link { color: #93a3b8; font-weight: 600; }
        .pa-header .nav-link:hover, .pa-header .nav-link.active { color: #fff; }
        .pa-badge { font-size: .6875rem; letter-spacing: .08em; text-transform: uppercase; }
    </style>
    @stack('styles')
</head>
<body class="layout-fluid">
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@1.0.0-beta20/dist/js/tabler.min.js" defer></script>

<header class="navbar navbar-expand-md pa-header d-print-none">
    <div class="container-xl">
        <h1 class="navbar-brand mb-0 me-3">
            <a href="{{ route('platform-admin.tenants.index') }}" class="d-flex align-items-center gap-2 text-decoration-none">
                <img src="{{ \App\Support\Brand::platformMark() }}" alt="" style="height:26px;width:auto;">
                <span style="font-weight:700;letter-spacing:-.02em;">{{ \App\Support\Brand::platformName() }}</span>
            </a>
        </h1>
        <span class="badge bg-azure-lt pa-badge me-3">{{ __('Platform Console') }}</span>

        <ul class="navbar-nav flex-row gap-2 d-none d-md-flex">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('platform-admin.tenants.*') ? 'active' : '' }}"
                   href="{{ route('platform-admin.tenants.index') }}">{{ __('Clients') }}</a>
            </li>
        </ul>

        <div class="ms-auto d-flex align-items-center gap-2">
            {{-- Jump straight into any client's CRM, from anywhere in the console. --}}
            @include('platform-admin._client-switcher')

            <div class="dropdown">
                <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                    <span class="avatar avatar-sm bg-azure text-white me-2">
                        {{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                    </span>
                    <span class="d-none d-lg-inline">{{ auth()->user()->name }}</span>
                </a>
                <div class="dropdown-menu dropdown-menu-end">
                    <span class="dropdown-header">{{ auth()->user()->email }}</span>
                    <a class="dropdown-item" href="{{ route('dashboard') }}">{{ __('My own CRM') }}</a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">{{ __('Sign out') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="page-wrapper">
    <div class="page-body">
        <div class="container-xl">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            @yield('content')
        </div>
    </div>

    <footer class="footer footer-transparent d-print-none">
        <div class="container-xl text-center text-secondary">
            &copy; {{ date('Y') }} {{ \App\Support\Brand::platformName() }}. {{ __('All rights reserved.') }}
        </div>
    </footer>
</div>
</body>
</html>
