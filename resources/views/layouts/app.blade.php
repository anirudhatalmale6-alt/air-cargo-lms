<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Dashboard') &middot; {{ config('aircargo.company_short') }}</title>
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
@php($user = auth()->user())
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark">&#9992;</span>
            <span>{{ config('aircargo.company_short') }}
                <small>Air cargo operations</small>
            </span>
        </a>

        <nav class="nav">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('shipments.index', ['direction' => 'export']) }}"
               class="{{ request()->routeIs('shipments.*') && request('direction') === 'export' ? 'active' : '' }}">Exports</a>
            <a href="{{ route('shipments.index', ['direction' => 'import']) }}"
               class="{{ request()->routeIs('shipments.*') && request('direction') === 'import' ? 'active' : '' }}">Imports</a>
            @if ($user->isStaff())
                <a href="{{ route('customers.index') }}" class="{{ request()->routeIs('customers.*') ? 'active' : '' }}">Customers</a>
            @endif
            @if ($user->isAdmin())
                <a href="{{ route('awbs.index') }}" class="{{ request()->routeIs('awbs.*') ? 'active' : '' }}">AWB stock</a>
                <a href="{{ route('airlines.index') }}" class="{{ request()->routeIs('airlines.*') ? 'active' : '' }}">Airlines</a>
                <a href="{{ route('users.index') }}" class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Users</a>
            @endif
            <a href="{{ route('tracking.show') }}">Track AWB</a>
        </nav>

        <div class="userbox">
            <span><strong>{{ $user->name }}</strong><br>{{ $user->role->label() }}</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="btn sm" type="submit">Sign out</button>
            </form>
        </div>
    </div>
</header>

<main class="wrap">
    <x-alerts />
    @yield('content')
</main>

<footer class="site-foot">
    {{ config('aircargo.company_name') }} &middot; Air Cargo Logistics Management System
</footer>
</body>
</html>
