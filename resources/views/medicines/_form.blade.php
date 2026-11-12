@php $input = 'mt-1 w-full rounded border border-slate-300 px-3 py-2'; @endphp
<form method="POST" action="{{ $action }}" class="max-w-xl space-y-4 rounded-lg bg-white p-6 shadow-sm">
    @csrf
    @if($medicine->exists) @method('PUT') @endif

    <label class="block">Medicine name
        <input name="name" list="catalog-names" value="{{ old('name', $medicine->name) }}" required autocomplete="off" class="{{ $input }}">
        <datalist id="catalog-names">
            @foreach($catalog as $item)
                <option value="{{ $item->generic_name }}">{{ $item->brand_names }}</option>
            @endforeach
        </datalist>
        <span class="text-base text-slate-500">Start typing to pick from the list, or enter your own.</span>
    </label>
    <label class="block">Dosage <span class="text-base text-slate-500">(for example 500 mg, 1 tablet)</span>
        <input name="dosage" value="{{ old('dosage', $medicine->dosage) }}" required class="{{ $input }}">
    </label>
    <label class="block">What is it for?
        <input name="purpose" value="{{ old('purpose', $medicine->purpose) }}" class="{{ $input }}">
    </label>
    <label class="block">Category
        <select name="category_id" class="{{ $input }}">
            <option value="">Choose automatically or leave blank</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) old('category_id', $medicine->category_id) === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
    </label>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="block">Start date
            <input type="date" name="start_date" value="{{ old('start_date', $medicine->start_date?->toDateString()) }}" required class="{{ $input }}">
        </label>
        <label class="block">End date <span class="text-base text-slate-500">(optional)</span>
            <input type="date" name="end_date" value="{{ old('end_date', $medicine->end_date?->toDateString()) }}" class="{{ $input }}">
        </label>
    </div>
    <label class="block">My supply runs out on <span class="text-base text-slate-500">(optional)</span>
        <input type="date" name="supply_until" value="{{ old('supply_until', $medicine->supply_until?->toDateString()) }}" class="{{ $input }}">
        <span class="text-base text-slate-500">Lets a pharmacy you choose remind you to refill in time.</span>
    </label>
    <label class="block">Special instructions
        <textarea name="instructions" rows="3" class="{{ $input }}">{{ old('instructions', $medicine->instructions) }}</textarea>
    </label>
    <label class="flex items-center gap-2">
        <input type="checkbox" name="active" value="1" class="size-5" @checked(old('active', $medicine->exists ? $medicine->active : true))>
        Currently taking this medicine
    </label>

    <div class="flex gap-3">
        <button class="rounded bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800">Save</button>
        <a href="{{ $cancel }}" class="rounded border border-slate-300 px-6 py-3 hover:bg-slate-100">Cancel</a>
    </div>
</form>
