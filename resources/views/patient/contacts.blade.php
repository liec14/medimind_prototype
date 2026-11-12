@extends('layouts.app')
@section('title', 'Trusted contacts')
@section('content')
    <h1 class="text-2xl font-bold">Trusted contacts</h1>
    <p class="mt-2 text-slate-600">
        If you miss {{ $misses }} doses in a row, MediMind sends a short email to the people you choose below, and tells your linked doctors. The email says only that you missed doses. It does not name your medicines.
    </p>
    <p class="mt-2 rounded border border-amber-200 bg-amber-50 px-4 py-3 text-base text-amber-900">
        Only add people who agreed to be contacted. You can turn alerts off or remove someone at any time.
    </p>

    @if($doctors->isNotEmpty())
        <p class="mt-4 text-base text-slate-600">Linked doctors who will also be told: {{ $doctors->pluck('name')->implode(', ') }}.</p>
    @endif

    <form method="POST" action="{{ route('patient.contacts.store') }}" class="mt-6 grid max-w-2xl gap-3 rounded-lg bg-white p-4 shadow-sm sm:grid-cols-4">
        @csrf
        <input name="name" value="{{ old('name') }}" placeholder="Name" required class="rounded border border-slate-300 px-3 py-2" aria-label="Name">
        <input type="email" name="email" value="{{ old('email') }}" placeholder="Email" required class="rounded border border-slate-300 px-3 py-2" aria-label="Email">
        <input name="relationship" value="{{ old('relationship') }}" placeholder="Relationship (optional)" class="rounded border border-slate-300 px-3 py-2" aria-label="Relationship">
        <button class="rounded bg-teal-700 px-4 py-2 font-semibold text-white hover:bg-teal-800">Add contact</button>
        <p class="text-sm text-slate-500 sm:col-span-4">{{ $contacts->count() }} of {{ $max }} contacts used.</p>
    </form>

    <ul class="mt-6 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($contacts as $contact)
            <li class="flex flex-wrap items-center justify-between gap-3 p-4">
                <div>
                    <div class="font-semibold">{{ $contact->name }} <span class="font-normal text-slate-500">{{ $contact->relationship ? '· '.$contact->relationship : '' }}</span></div>
                    <div class="text-base text-slate-500">{{ $contact->email }}</div>
                </div>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('patient.contacts.update', $contact) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <label class="flex items-center gap-2 text-base"><input type="checkbox" name="notify" value="1" class="size-5" @checked($contact->notify)> Send alerts</label>
                        <button class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Save</button>
                    </form>
                    <form method="POST" action="{{ route('patient.contacts.destroy', $contact) }}" onsubmit="return confirm('Remove {{ e($contact->name) }}?')">
                        @csrf @method('DELETE')
                        <button class="rounded border border-slate-300 px-3 py-1.5 text-base text-red-700 hover:bg-red-50">Remove</button>
                    </form>
                </div>
            </li>
        @empty
            <li class="p-4 text-slate-500">No trusted contacts yet.</li>
        @endforelse
    </ul>
@endsection
