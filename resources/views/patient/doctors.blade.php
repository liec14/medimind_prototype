@extends('layouts.app')
@section('title', 'My doctors')
@section('content')
    <h1 class="text-2xl font-bold">My doctors</h1>
    <p class="mt-2 text-slate-600">A doctor can only see your records after you invite them and they accept.</p>

    <form method="POST" action="{{ route('patient.doctors.store') }}" class="mt-6 flex flex-wrap gap-3 rounded-lg bg-white p-4 shadow-sm">
        @csrf
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Doctor's email" required
               class="min-w-0 flex-1 rounded border border-slate-300 px-3 py-2">
        <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Send invitation</button>
    </form>

    <ul class="mt-6 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($doctors as $doctor)
            <li class="flex items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-semibold">{{ $doctor->name }}</div>
                    <div class="text-base text-slate-500">{{ ucfirst($doctor->pivot->status) }}</div>
                </div>
                <form method="POST" action="{{ route('patient.doctors.destroy', $doctor) }}">@csrf @method('DELETE')
                    <button class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Remove</button>
                </form>
            </li>
        @empty
            <li class="p-4 text-slate-500">No doctors linked yet.</li>
        @endforelse
    </ul>
@endsection
