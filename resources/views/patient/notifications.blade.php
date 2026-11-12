@extends('layouts.app')
@php
    $isDoctor = auth()->user()->hasRole(\App\Enums\Role::Doctor);
    $title = $isDoctor ? 'Alerts' : 'Reminders';
@endphp
@section('title', $title)
@section('content')
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">{{ $title }}</h1>
        @if($notifications->whereNull('read_at')->isNotEmpty())
            <form method="POST" action="{{ route($isDoctor ? 'doctor.notifications.read' : 'patient.notifications.read') }}">@csrf
                <button class="rounded border border-slate-300 px-4 py-2 text-base hover:bg-slate-100">Mark all as read</button>
            </form>
        @endif
    </div>
    <ul class="mt-4 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($notifications as $n)
            <li class="p-4 {{ $n->read_at ? '' : 'bg-teal-50' }}">
                <div class="{{ $n->read_at ? '' : 'font-semibold' }}">{{ $n->data['message'] ?? 'Notification' }}</div>
                <div class="text-base text-slate-500">
                    {{ $n->created_at->format('D, M j · g:i A') }}
                    @if(! empty($n->data['url'])) · <a href="{{ $n->data['url'] }}" class="text-teal-700 underline">Open</a>@endif
                </div>
            </li>
        @empty
            <li class="p-4 text-slate-500">{{ $isDoctor ? 'No alerts yet. You will see them here when a patient needs follow-up.' : 'No reminders yet. They appear here when a dose is due.' }}</li>
        @endforelse
    </ul>
    <a href="{{ route($isDoctor ? 'doctor.dashboard' : 'patient.dashboard') }}" class="mt-4 inline-block text-teal-700 underline">{{ $isDoctor ? 'Back to my patients' : "Go to today's doses" }}</a>
@endsection
