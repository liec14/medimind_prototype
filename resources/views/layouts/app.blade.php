<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'MediMind') · MediMind</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-lg text-slate-800 antialiased">
@php
    $user = auth()->user();
    $links = match ($user?->role?->value) {
        'patient' => ['patient.dashboard' => 'Today', 'patient.medicines.index' => 'My medicines', 'patient.history' => 'History', 'patient.info.index' => 'Medicine info', 'patient.otc' => 'OTC help', 'patient.compare' => 'Compare prices', 'patient.doctors' => 'My doctors', 'patient.contacts' => 'Trusted contacts', 'patient.pharmacies' => 'Pharmacies'],
        'doctor' => ['doctor.dashboard' => 'My patients'],
        'pharmacy' => ['pharmacy.dashboard' => 'Overview', 'pharmacy.refills' => 'Refills', 'pharmacy.questions' => 'Questions', 'pharmacy.prices' => 'Price list'],
        'admin' => ['admin.approvals' => 'Approvals'],
        default => [],
    };
@endphp
<header class="border-b border-slate-200 bg-white">
    <nav class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-6 gap-y-2 px-4 py-3">
        <a href="{{ route('home') }}" class="text-xl font-bold text-teal-700">MediMind</a>
        @if($user && $user->isApproved())
            @foreach($links as $route => $label)
                <a href="{{ route($route) }}" class="rounded px-2 py-1 hover:bg-teal-50 {{ request()->routeIs($route) ? 'font-semibold text-teal-700' : '' }}">{{ $label }}@if($route === 'pharmacy.questions' && $user->pharmacy && ($open = $user->pharmacy->questions()->whereNull('answered_at')->count()))<span class="ml-1 rounded-full bg-red-600 px-2 py-0.5 text-sm text-white" aria-label="{{ $open }} unanswered">{{ $open }}</span>@endif</a>
            @endforeach
        @endif
        <div class="ml-auto flex items-center gap-3 text-base">
            @if($user && $user->isApproved() && $user->hasRole(\App\Enums\Role::Patient, \App\Enums\Role::Doctor))
                @php
                    $unread = $user->unreadNotifications()->count();
                    $inboxLabel = $user->hasRole(\App\Enums\Role::Doctor) ? 'Alerts' : 'Reminders';
                    $inboxRoute = $user->hasRole(\App\Enums\Role::Doctor) ? 'doctor.notifications' : 'patient.notifications';
                @endphp
                <a href="{{ route($inboxRoute) }}" class="rounded px-2 py-1 hover:bg-teal-50" aria-label="{{ $inboxLabel }}, {{ $unread }} unread">
                    {{ $inboxLabel }} @if($unread)<span class="ml-1 rounded-full bg-red-600 px-2 py-0.5 text-sm text-white">{{ $unread }}</span>@endif
                </a>
            @endif
            @auth
                <span class="text-slate-500">{{ $user->name }} · {{ $user->role->label() }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button class="rounded border border-slate-300 px-3 py-1.5 hover:bg-slate-100">Log out</button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded px-3 py-1.5 hover:bg-slate-100">Log in</a>
                <a href="{{ route('register') }}" class="rounded bg-teal-700 px-3 py-1.5 text-white hover:bg-teal-800">Sign up</a>
            @endauth
        </div>
    </nav>
</header>

<main class="mx-auto max-w-5xl px-4 py-8">
    @if(session('status'))
        <div class="mb-6 rounded border border-teal-200 bg-teal-50 px-4 py-3 text-teal-900" role="status">{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div class="mb-6 rounded border border-red-200 bg-red-50 px-4 py-3 text-red-900" role="alert">
            <ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif
    @yield('content')
</main>

<footer class="mx-auto max-w-5xl px-4 pb-8 text-sm text-slate-500">{{ config('medimind.disclaimer') }}</footer>
</body>
</html>
