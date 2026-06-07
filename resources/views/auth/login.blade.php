@extends('layouts.auth')
@section('title', 'Login - LeadPilot PK')
@section('art-eyebrow', 'AI website opportunity intelligence')
@section('art-title', 'Find the businesses that need you most.')
@section('art-copy', 'Build compliant Google Places campaigns, qualify website opportunities and manage outreach in one focused sales workspace.')
@section('content')
<form method="post" action="{{ route('login') }}">
    @csrf
    <div class="login-heading mb-4">
        <span class="login-mark-wrap">
            <img class="login-mark login-mark-light" src="{{ asset('logos/light image.png') }}" alt="LeadPilot">
            <img class="login-mark login-mark-dark" src="{{ asset('logos/dark image.png') }}" alt="LeadPilot">
        </span>
        <div><h2 class="fw-bold mb-1">Welcome back</h2><p class="text-secondary mb-0">Sign in to your lead workspace.</p></div>
    </div>
    @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
    @error('email')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <label class="form-label">Email address</label><input class="form-control mb-3" type="email" name="email" value="{{ old('email') }}" required autofocus>
    <div class="d-flex justify-content-between"><label class="form-label">Password</label><a class="small" href="{{ route('password.request') }}">Forgot password?</a></div>
    <input class="form-control mb-3" type="password" name="password" required><label class="mb-4 small"><input type="checkbox" name="remember"> Remember me</label>
    <button class="btn btn-brand w-100 py-2">Sign in</button><p class="text-center mt-4 small">New here? <a href="{{ route('register') }}">Create an account</a></p>
</form>
@endsection