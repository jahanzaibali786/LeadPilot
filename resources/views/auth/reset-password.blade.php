@extends('layouts.auth')
@section('title','Choose password · LeadPilot PK')
@section('content')
<form method="post" action="{{route('password.update')}}">@csrf
    <input type="hidden" name="token" value="{{$token}}"><h2 class="fw-bold">Choose a new password</h2>
    @if($errors->any())<div class="alert alert-danger">{{$errors->first()}}</div>@endif
    <input class="form-control mb-3" type="email" name="email" value="{{$email}}" required><input class="form-control mb-3" type="password" name="password" placeholder="New password" required><input class="form-control mb-3" type="password" name="password_confirmation" placeholder="Confirm password" required><button class="btn btn-brand w-100">Reset password</button>
</form>
@endsection
