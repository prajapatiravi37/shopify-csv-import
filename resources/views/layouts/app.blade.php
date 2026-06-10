<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Shopify CSV Import') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8">
            <a href="{{ route('home') }}" class="text-lg font-semibold text-indigo-600">
                Shopify CSV Import
            </a>
            <div class="flex items-center gap-4 text-sm font-medium">
                <a href="{{ route('home') }}" class="{{ request()->routeIs('home') ? 'text-indigo-600' : 'text-slate-600 hover:text-indigo-600' }}">
                    Upload
                </a>
                <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'text-indigo-600' : 'text-slate-600 hover:text-indigo-600' }}">
                    Dashboard
                </a>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        @yield('content')
    </main>
</body>
</html>
