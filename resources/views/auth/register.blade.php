@extends('layouts.app')
@section('title', 'Sign up')
@section('content')
    @php $input = 'mt-1 w-full rounded border border-slate-300 px-3 py-2'; @endphp
    <form method="POST" action="{{ route('register') }}" class="mx-auto max-w-lg space-y-4 rounded-lg bg-white p-6 shadow-sm">
        @csrf
        <h1 class="text-2xl font-bold">Create your account</h1>

        <label class="block">I am a
            <select name="role" id="role" class="{{ $input }}" onchange="document.querySelectorAll('[data-for]').forEach(e => e.hidden = !e.dataset.for.split(' ').includes(this.value))">
                @foreach($roles as $role)
                    <option value="{{ $role->value }}" @selected(old('role', 'patient') === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">Full name <input name="name" value="{{ old('name') }}" required class="{{ $input }}"></label>
        <label class="block">Email <input type="email" name="email" value="{{ old('email') }}" required class="{{ $input }}"></label>
        <label class="block">Mobile number <input name="phone" value="{{ old('phone') }}" class="{{ $input }}"></label>

        <label class="block" data-for="patient doctor pharmacy">Birthdate <input type="date" name="birthdate" value="{{ old('birthdate') }}" class="{{ $input }}"></label>

        <div data-for="doctor pharmacy" @hidden(old('role', 'patient') === 'patient') class="space-y-4">
            <label class="block">PRC license number <input name="license_no" value="{{ old('license_no') }}" class="{{ $input }}"></label>
            <p class="text-base text-slate-500">Doctor and pharmacy accounts are reviewed by an administrator before use.</p>
        </div>
        <div data-for="pharmacy" @hidden(old('role', 'patient') !== 'pharmacy') class="space-y-4">
            <label class="block">Pharmacy name <input name="pharmacy_name" value="{{ old('pharmacy_name') }}" class="{{ $input }}"></label>
            <label class="block">Pharmacy address <input name="pharmacy_address" value="{{ old('pharmacy_address') }}" class="{{ $input }}"></label>
        </div>

        <label class="block">Password <input type="password" name="password" required autocomplete="new-password" class="{{ $input }}"></label>
        <label class="block">Confirm password <input type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $input }}"></label>
        <button class="w-full rounded bg-teal-700 py-3 font-semibold text-white hover:bg-teal-800">Sign up</button>
    </form>
@endsection
