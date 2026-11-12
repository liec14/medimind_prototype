@extends('layouts.app')
@section('title', 'Log in')
@section('content')
    <form method="POST" action="{{ route('login') }}" class="mx-auto max-w-md space-y-4 rounded-lg bg-white p-6 shadow-sm">
        @csrf
        <h1 class="text-2xl font-bold">Log in</h1>
        <label class="block">Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </label>
        <label class="block">Password
            <input type="password" name="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
        </label>
        <label class="flex items-center gap-2"><input type="checkbox" name="remember" class="size-5"> Remember me</label>
        <button class="w-full rounded bg-teal-700 py-3 font-semibold text-white hover:bg-teal-800">Log in</button>
        <p class="text-center text-base text-slate-600">New here? <a href="{{ route('register') }}" class="text-teal-700 underline">Create an account</a></p>
    </form>
@endsection
