@extends('layouts.app')
@section('title', 'Compare prices')
@section('content')
    <h1 class="text-2xl font-bold">Compare pharmacy prices</h1>
    <p class="mt-2 text-slate-600">See the same medicine across the pharmacies registered in MediMind. Prices are set by each pharmacy and may change. Confirm with the pharmacy before you go.</p>

    <form method="GET" class="mt-4 flex flex-wrap items-end gap-3">
        <label class="block">Medicine
            <select name="medicine_id" class="mt-1 block min-w-64 rounded border border-slate-300 bg-white px-3 py-2">
                <option value="">Choose a medicine</option>
                @foreach($catalog as $m)
                    <option value="{{ $m->id }}" @selected($selected?->id === $m->id)>{{ $m->generic_name }} {{ $m->strength }} ({{ $m->brand_names }})</option>
                @endforeach
            </select>
        </label>
        <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Compare</button>
    </form>

    @if($selected)
        <h2 class="mt-8 text-xl font-semibold">{{ $selected->generic_name }} <span class="text-base font-normal text-slate-500">{{ $selected->strength }} {{ $selected->form }}</span></h2>
        <ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
            @forelse($rows as $row)
                <li class="flex flex-wrap items-center justify-between gap-3 p-4 {{ $row->in_stock ? '' : 'opacity-60' }}">
                    <div>
                        <div class="font-semibold">{{ $row->pharmacy->name }}
                            @if($cheapest && $cheapest->is($row))<span class="ml-1 rounded bg-teal-100 px-2 py-0.5 text-sm text-teal-800">Lowest price</span>@endif
                        </div>
                        <div class="text-base text-slate-500">{{ $row->pharmacy->address }}@if($row->pharmacy->contact) · {{ $row->pharmacy->contact }}@endif</div>
                        <div class="text-sm {{ $row->isStale() ? 'text-amber-700' : 'text-slate-500' }}">
                            Price updated {{ $row->updated_at->diffForHumans() }}@if($row->isStale()) · may be out of date @endif
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-xl font-bold">₱{{ number_format($row->price, 2) }}</div>
                        <div class="text-base {{ $row->in_stock ? 'text-teal-700' : 'text-red-700' }}">{{ $row->in_stock ? 'In stock' : 'Out of stock' }}</div>
                    </div>
                </li>
            @empty
                <li class="p-4 text-slate-500">No registered pharmacy has listed this medicine yet.</li>
            @endforelse
        </ul>
        <p class="mt-3 text-sm text-slate-500">Only pharmacies registered in MediMind are compared. Ordering and delivery are not available here.</p>
    @endif
@endsection
