@extends('layouts.app')
@section('title', 'Approvals')
@section('content')
    <h1 class="text-2xl font-bold">Pending approvals</h1>
    <ul class="mt-6 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($pending as $user)
            <li class="flex items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-semibold">{{ $user->name }} <span class="text-base font-normal text-slate-500">· {{ $user->role->label() }}</span></div>
                    <div class="text-base text-slate-500">{{ $user->email }} · License: {{ $user->license_no ?? '—' }}@if($user->pharmacy) · {{ $user->pharmacy->name }}@endif</div>
                </div>
                <form method="POST" action="{{ route('admin.approvals.approve', $user) }}">@csrf @method('PATCH')
                    <button class="rounded bg-teal-700 px-4 py-1.5 text-white hover:bg-teal-800">Approve</button>
                </form>
            </li>
        @empty
            <li class="p-4 text-slate-500">Nothing waiting for approval.</li>
        @endforelse
    </ul>
@endsection
