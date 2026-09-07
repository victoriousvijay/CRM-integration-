<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Properties')) - {{ \App\Support\Brand::name() }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    {{-- One same-origin stylesheet, no CDN: see the note at the top of it. --}}
    <link rel="stylesheet" href="{{ asset('css/broker.css') }}">
    @include('layouts._pwa')
</head>
<body class="bp">
@include('layouts._splash')

@php
    $onProperties = request()->routeIs('broker.index') || request()->routeIs('broker.show');
    $onEnquiries = request()->routeIs('broker.leads.*');
@endphp

<header class="bp-header bp-print-hide">
    <div class="bp-container bp-header__inner">
        <a href="{{ route('broker.index') }}" class="bp-row">
            @include('layouts._brand', ['size' => '1.3rem'])
        </a>

        <nav class="bp-nav">
            <a href="{{ route('broker.index') }}" class="bp-nav__link {{ $onProperties ? 'is-active' : '' }}">
                @include('broker._icon', ['name' => 'home', 'size' => 16])
                {{ __('Properties') }}
            </a>
            <a href="{{ route('broker.leads.index') }}" class="bp-nav__link {{ $onEnquiries ? 'is-active' : '' }}">
                @include('broker._icon', ['name' => 'users', 'size' => 16])
                {{ __('My Enquiries') }}
            </a>
        </nav>

        <div class="bp-row bp-push">
            <span class="bp-user">
                <span class="bp-avatar">{{ strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
                <span class="bp-hide-sm">{{ auth()->user()->name }}</span>
            </span>
            <form method="POST" action="{{ route('logout') }}" class="bp-signout">
                @csrf
                <button type="submit" class="bp-btn bp-btn--ghost bp-btn--sm">{{ __('Sign out') }}</button>
            </form>
        </div>
    </div>
</header>

<main class="bp-main">
    <div class="bp-container">
        @if(session('success'))
            <div class="bp-alert">
                @include('broker._icon', ['name' => 'check', 'size' => 20])
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @yield('content')

        @include('layouts._platform-footer')
    </div>
</main>

{{-- The whole navigation on a phone, at thumb height. --}}
<nav class="bp-tabbar bp-print-hide">
    <a href="{{ route('broker.index') }}" class="bp-tabbar__link {{ $onProperties ? 'is-active' : '' }}">
        @include('broker._icon', ['name' => 'home', 'size' => 22])
        {{ __('Properties') }}
    </a>
    <a href="{{ route('broker.leads.index') }}" class="bp-tabbar__link {{ $onEnquiries && ! request()->routeIs('broker.leads.create') ? 'is-active' : '' }}">
        @include('broker._icon', ['name' => 'users', 'size' => 22])
        {{ __('Enquiries') }}
    </a>
    <a href="{{ route('broker.leads.create') }}" class="bp-tabbar__link {{ request()->routeIs('broker.leads.create') ? 'is-active' : '' }}">
        @include('broker._icon', ['name' => 'plus', 'size' => 22])
        {{ __('New') }}
    </a>
</nav>
</body>
</html>
