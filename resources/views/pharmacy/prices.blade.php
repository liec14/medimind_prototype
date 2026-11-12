@extends('layouts.app')
@section('title', 'Price list')
@section('content')
    @php $input = 'rounded border border-slate-300 px-3 py-2'; @endphp
    <h1 class="text-2xl font-bold">{{ $pharmacy->name }}</h1>
    <p class="mt-2 text-slate-600">Keep your prices and stock current. Patients use this list to compare pharmacies.</p>

    <form method="POST" action="{{ route('pharmacy.stock.store') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-lg bg-white p-4 shadow-sm">
        @csrf
        <label class="block text-base">Add a medicine
            <select name="catalog_medicine_id" required class="mt-1 block min-w-56 bg-white {{ $input }}">
                <option value="">Choose…</option>
                @foreach($available as $m)<option value="{{ $m->id }}">{{ $m->generic_name }} {{ $m->strength }}</option>@endforeach
            </select>
        </label>
        <label class="block text-base">Price (₱)
            <input type="number" name="price" step="0.01" min="0" max="99999.99" required class="mt-1 block w-28 {{ $input }}">
        </label>
        <label class="flex items-center gap-2 pb-2"><input type="checkbox" name="in_stock" value="1" checked class="size-5"> In stock</label>
        <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Add</button>
    </form>

    <h2 class="mt-8 text-xl font-semibold">Your price list</h2>
    <ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
        @forelse($stock as $row)
            <li class="p-4">
                <form method="POST" action="{{ route('pharmacy.stock.update', $row) }}" class="flex flex-wrap items-center gap-3">
                    @csrf @method('PUT')
                    <div class="min-w-48 flex-1">
                        <div class="font-semibold">{{ $row->catalogMedicine->generic_name }} <span class="font-normal text-slate-500">{{ $row->catalogMedicine->strength }}</span></div>
                        <div class="text-sm {{ $row->isStale() ? 'text-amber-700' : 'text-slate-500' }}">Updated {{ $row->updated_at->diffForHumans() }}</div>
                    </div>
                    <label class="text-base">₱ <input type="number" name="price" step="0.01" min="0" max="99999.99" value="{{ $row->price }}" required class="w-28 {{ $input }}" aria-label="Price for {{ $row->catalogMedicine->generic_name }}"></label>
                    <label class="flex items-center gap-2 text-base"><input type="checkbox" name="in_stock" value="1" @checked($row->in_stock) class="size-5"> In stock</label>
                    <button class="rounded border border-slate-300 px-3 py-1.5 text-base hover:bg-slate-100">Save</button>
                </form>
                <form method="POST" action="{{ route('pharmacy.stock.destroy', $row) }}" class="mt-2" onsubmit="return confirm('Remove this medicine from your list?')">
                    @csrf @method('DELETE')
                    <button class="text-sm text-red-700 underline">Remove</button>
                </form>
            </li>
        @empty
            <li class="p-4 text-slate-500">Nothing listed yet. Add the medicines you carry above.</li>
        @endforelse
    </ul>
@endsection
