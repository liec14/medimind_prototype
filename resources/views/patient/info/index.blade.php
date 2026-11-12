@extends('layouts.app')
@section('title', 'Medicine information')
@section('content')
    <h1 class="text-2xl font-bold">Medicine information</h1>
    <p class="mt-2 text-slate-600">Find out what a medicine is for, how it is usually taken, and what to watch out for.</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block text-base">Search by name or brand
            <input name="q" value="{{ $filters['q'] }}" placeholder="e.g. Biogesic" class="mt-1 block rounded border border-slate-300 px-3 py-2">
        </label>
        <label class="block text-base">Category
            <select name="category_id" class="mt-1 block rounded border border-slate-300 bg-white px-3 py-2">
                <option value="">All</option>
                @foreach($categories as $c)<option value="{{ $c->id }}" @selected((string) $filters['category_id'] === (string) $c->id)>{{ $c->name }}</option>@endforeach
            </select>
        </label>
        <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Search</button>
    </form>

    <ul class="mt-6 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($medicines as $m)
            <li>
                <a href="{{ route('patient.info.show', $m) }}" class="block p-4 hover:bg-teal-50">
                    <span class="font-semibold">{{ $m->generic_name }}</span>
                    <span class="text-slate-500">{{ $m->strength }} {{ $m->form }}</span>
                    @if($m->is_otc)<span class="ml-1 rounded bg-teal-100 px-2 py-0.5 text-sm text-teal-800">Over the counter</span>@endif
                    <div class="text-base text-slate-500">{{ $m->brand_names }} · {{ $m->category?->name }}</div>
                </a>
            </li>
        @empty
            <li class="p-4 text-slate-500">No medicines match your search. Ask your pharmacist about medicines that are not listed.</li>
        @endforelse
    </ul>
@endsection
