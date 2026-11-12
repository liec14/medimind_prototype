@php
    $input = 'mt-1 rounded border border-slate-300 px-3 py-2';
    $selectedDays = collect(old('days', $days))->map(fn ($d) => (int) $d)->all();
@endphp
<form method="POST" action="{{ $action }}" class="max-w-xl space-y-5 rounded-lg bg-white p-6 shadow-sm">
    @csrf @method('PUT')

    <div>
        <div class="font-semibold">{{ $medicine->name }} <span class="font-normal text-slate-500">· {{ $medicine->dosage }}</span></div>
        <div class="text-base text-slate-500">
            From {{ $medicine->start_date->format('M j, Y') }}@if($medicine->end_date) to {{ $medicine->end_date->format('M j, Y') }}@else, no end date @endif
        </div>
    </div>

    <label class="block">How often?
        <select name="frequency" class="{{ $input }} w-full"
                onchange="window.location = '{{ $frequencyUrl }}?frequency=' + this.value">
            @foreach(\App\Models\Schedule::FREQUENCIES as $key => $f)
                <option value="{{ $key }}" @selected(old('frequency', $frequency) === $key)>{{ $f['label'] }}</option>
            @endforeach
        </select>
        <span class="text-base text-slate-500">Changing this fills in times from your <a href="{{ route('patient.routine.edit') }}" class="underline">daily routine</a>. You can adjust them.</span>
    </label>

    <fieldset>
        <legend>Times</legend>
        <div class="mt-1 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach(old('times', $times) as $i => $time)
                <input type="time" name="times[]" value="{{ $time }}" aria-label="Dose time {{ $i + 1 }}" class="{{ $input }}">
            @endforeach
        </div>
        <span class="text-base text-slate-500">Leave unused boxes empty.</span>
    </fieldset>

    <fieldset>
        <legend>Days</legend>
        <div class="mt-1 flex flex-wrap gap-3">
            @foreach(\App\Models\Schedule::DAY_NAMES as $n => $name)
                <label class="flex items-center gap-1.5"><input type="checkbox" name="days[]" value="{{ $n }}" class="size-5" @checked(in_array($n, $selectedDays, true))> {{ $name }}</label>
            @endforeach
        </div>
        <span class="text-base text-slate-500">Leave all unticked to take it every day.</span>
    </fieldset>

    <div class="flex gap-3">
        <button class="rounded bg-teal-700 px-6 py-3 font-semibold text-white hover:bg-teal-800">Save schedule</button>
        <a href="{{ $cancel }}" class="rounded border border-slate-300 px-6 py-3 hover:bg-slate-100">Cancel</a>
    </div>
</form>
