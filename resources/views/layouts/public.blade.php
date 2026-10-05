<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>@yield('title', 'Track your air cargo') &middot; {{ config('aircargo.company_name') }}</title>
<meta name="description" content="Enter your air waybill number to see the latest cargo status and full tracking history.">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('tracking') }}">
            <span class="brand-mark">&#9992;</span>
            <span>{{ config('aircargo.company_short') }}
                <small>Cargo tracking</small>
            </span>
        </a>
        <nav class="nav">
            <a href="{{ route('tracking') }}" class="{{ request()->routeIs('tracking*') ? 'active' : '' }}">Track AWB</a>
            @auth
                <a href="{{ route('dashboard') }}">Dashboard</a>
            @else
                <a href="{{ route('login') }}">Staff sign in</a>
            @endauth
        </nav>
    </div>
</header>

@yield('content')

<footer class="site-foot">
    {{ config('aircargo.company_name') }}
    <span class="sep">|</span>
    Tracking information is supplied by the operating carrier and our own operations records.
</footer>
</body>
</html>
