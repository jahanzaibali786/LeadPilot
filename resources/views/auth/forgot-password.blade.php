@extends('layouts.auth')
@section('title','Reset password · LeadPilot PK')
@section('content')
<form method="post" action="{{route('password.email')}}">@csrf
    <h2 class="fw-bold">Reset your password</h2><p class="text-secondary">We will send a secure reset link to your email address.</p>
    @if(session('status'))<div class="alert alert-success">{{session('status')}}</div>@endif @error('email')<div class="alert alert-danger">{{$message}}</div>@enderror
    <input class="form-control mb-3" type="email" name="email" required autofocus placeholder="you@example.com"><button class="btn btn-brand w-100">Email reset link</button><a class="d-block text-center mt-4 small" href="{{route('login')}}">Back to sign in</a>
</form>
@endsection
