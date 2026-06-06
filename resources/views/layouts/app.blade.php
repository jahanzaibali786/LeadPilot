<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LeadPilot PK') · AI Lead Generator</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}"><span class="brand-mark">LP</span><span>LeadPilot <b>PK</b><small>AI opportunity CRM</small></span></a>
    <nav>
        @php($nav=[['dashboard','bi-grid','Dashboard'],['campaigns.index','bi-bullseye','Campaigns'],['leads.index','bi-people','Lead table'],['leads.board','bi-kanban','Pipeline'],['services.index','bi-briefcase','Services'],['blacklists.index','bi-shield-x','Blacklist'],['settings.index','bi-sliders','Settings']])
        @foreach($nav as [$route,$icon,$label]) <a class="{{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'active':'' }}" href="{{ route($route) }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a> @endforeach
        @role('Super Admin')<a class="{{request()->routeIs('admin.*')?'active':''}}" href="{{route('admin.index')}}"><i class="bi bi-person-gear"></i>Administration</a>@endrole
    </nav>
    <div class="sidebar-foot"><div class="avatar">{{ strtoupper(substr(auth()->user()->name,0,1)) }}</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->getRoleNames()->first() ?: 'User' }}</small></div><form method="post" action="{{ route('logout') }}">@csrf<button title="Logout"><i class="bi bi-box-arrow-right"></i></button></form></div>
</aside>
<main class="main">
    <header class="topbar"><div><p class="eyebrow">@yield('eyebrow','Lead intelligence')</p><h1>@yield('title','Dashboard')</h1></div><div class="top-actions"><span class="compliance"><i class="bi bi-shield-check"></i> Compliant API sources</span><a href="{{ route('campaigns.index') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New campaign</a></div></header>
    @if(session('success'))<div class="alert alert-success toast-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger toast-alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Please fix the following:</strong> {{ $errors->first() }}</div>@endif
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body></html>
