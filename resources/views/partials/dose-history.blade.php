{{-- Expects $doses (paginator), $medicines, $filters, $summary from DoseHistoryService::build() --}}
<form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
    <label class="block text-base">Medicine
        <select name="medicine_id" class="mt-1 block rounded border border-slate-300 bg-white px-3 py-2">
            <option value="">All</option>
            @foreach($medicines as $m)<option value="{{ $m->id }}" @selected((string) $filters['medicine_id'] === (string) $m->id)>{{ $m->name }}</option>@endforeach
        </select>
    </label>
    <label class="block text-base">Status
        <select name="status" class="mt-1 block rounded border border-slate-300 bg-white px-3 py-2">
            <option value="">All</option>
            @foreach(['taken' => 'Taken', 'missed' => 'Missed', 'pending' => 'Pending'] as $v => $l)<option value="{{ $v }}" @selected($filters['status'] === $v)>{{ $l }}</option>@endforeach
        </select>
    </label>
    <label class="block text-base">Period
        <select name="range" class="mt-1 block rounded border border-slate-300 bg-white px-3 py-2">
            @foreach(['7' => 'Last 7 days', '30' => 'Last 30 days', 'all' => 'All time'] as $v => $l)<option value="{{ $v }}" @selected($filters['range'] === $v)>{{ $l }}</option>@endforeach
        </select>
    </label>
    <button class="rounded bg-teal-700 px-5 py-2 font-semibold text-white hover:bg-teal-800">Apply</button>
</form>

<p class="mt-4 text-slate-600">
    In this period: <strong>{{ $summary['taken'] }}</strong> taken, <strong>{{ $summary['missed'] }}</strong> missed
    @if($summary['rate'] !== null) ({{ rtrim(rtrim(number_format($summary['rate'], 1), '0'), '.') }}% taken)@endif.
</p>

<ul class="mt-3 divide-y divide-slate-200 rounded-lg bg-white shadow-sm">
    @forelse($doses as $dose)
        <li class="flex items-center justify-between gap-3 p-4">
            <div>
                <div class="font-semibold">{{ $dose->medicine->name }} <span class="font-normal text-slate-500">({{ $dose->medicine->dosage }})</span></div>
                <div class="text-base text-slate-500">
                    {{ $dose->scheduled_at->format('D, M j · g:i A') }}@if($dose->confirmed_at) · taken at {{ $dose->confirmed_at->format('g:i A') }}@endif
                </div>
            </div>
            <span class="rounded px-2 py-0.5 text-base {{ match($dose->status) { 'taken' => 'bg-teal-100 text-teal-800', 'missed' => 'bg-red-100 text-red-800', default => 'bg-slate-100 text-slate-700' } }}">{{ ucfirst($dose->status) }}</span>
        </li>
    @empty
        <li class="p-4 text-slate-500">No doses match these filters.</li>
    @endforelse
</ul>
<div class="mt-4">{{ $doses->links() }}</div>
