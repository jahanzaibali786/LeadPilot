<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LeadPilot') &middot; LeadPilot</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('logos/favicon_io/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('logos/favicon_io/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('logos/favicon_io/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logos/favicon_io/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('logos/favicon_io/site.webmanifest') }}">
    <script>document.documentElement.dataset.bsTheme=localStorage.getItem('leadpilot-theme')||'light';</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    @stack('head')
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="{{ route('dashboard') }}">
        <img class="brand-landscape" src="{{ asset('logos/dark landscape.png') }}" alt="LeadPilot">
    </a>
    <nav>
        @php($nav=[['dashboard','bi-grid','Dashboard'],['campaigns.index','bi-bullseye','Campaigns'],['leads.index','bi-people','Lead table'],['leads.board','bi-kanban','Pipeline'],['services.index','bi-briefcase','Services'],['blacklists.index','bi-shield-x','Blacklist'],['settings.index','bi-sliders','Settings']])
        @foreach($nav as [$route,$icon,$label]) <a class="{{ request()->routeIs(str_replace('.index','.*',$route)) || request()->routeIs($route) ? 'active':'' }}" href="{{ route($route) }}"><i class="bi {{ $icon }}"></i>{{ $label }}</a> @endforeach
        @role('Super Admin')<a class="{{request()->routeIs('admin.*')?'active':''}}" href="{{route('admin.index')}}"><i class="bi bi-person-gear"></i>Administration</a>@endrole
    </nav>
    <div class="sidebar-foot"><a class="sidebar-profile" href="{{ route('profile.show') }}" title="Open profile"><div class="avatar">@if(auth()->user()->profile_photo_path)<img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}" alt="{{ auth()->user()->name }}">@else{{ strtoupper(substr(auth()->user()->name,0,1)) }}@endif</div><div><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->getRoleNames()->first() ?: 'User' }}</small></div></a><form method="post" action="{{ route('logout') }}">@csrf<button title="Logout"><i class="bi bi-box-arrow-right"></i></button></form></div>
</aside>
<main class="main">
    <header class="topbar"><div><p class="eyebrow">@yield('eyebrow','Lead intelligence')</p><h1>@yield('title','Dashboard')</h1></div><div class="top-actions"><span class="compliance"><i class="bi bi-shield-check"></i> Compliant API sources</span><button class="theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme"><i class="bi bi-moon-stars"></i></button><a href="{{ route('campaigns.create') }}" class="btn btn-brand"><i class="bi bi-plus-lg"></i> New campaign</a></div></header>
    @if(session('success'))<div class="alert alert-success toast-alert"><i class="bi bi-check-circle"></i> {{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger toast-alert">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><strong>Please fix the following:</strong> {{ $errors->first() }}</div>@endif
    <div class="main-content">@yield('content')</div>
    <footer class="app-footer">&copy; {{ date('Y') }} LeadPilot. All rights reserved.</footer>
</main>
@include('partials.ai-chat')
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/theme.js') }}"></script>
<script src="{{ asset('js/ai-chat.js') }}"></script>
@stack('scripts')
</body></html>
