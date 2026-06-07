<!doctype html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>@yield('title', 'LeadPilot PK')</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('logos/favicon_io/favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('logos/favicon_io/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('logos/favicon_io/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('logos/favicon_io/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('logos/favicon_io/site.webmanifest') }}">
    <script>document.documentElement.dataset.bsTheme=localStorage.getItem('leadpilot-theme')||'light';</script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<button class="theme-toggle auth-theme-toggle" type="button" data-theme-toggle aria-label="Switch color theme"><i class="bi bi-moon-stars"></i></button>
<div class="auth-page @hasSection('art-title') has-auth-art @else auth-page-simple @endif">
    @hasSection('art-title')
    <section class="auth-art">
        <img class="auth-logo-landscape" src="{{ asset('logos/dark landscape.png') }}" alt="LeadPilot">
        <span class="eyebrow text-white-50">@yield('art-eyebrow')</span>
        <h1>@yield('art-title')</h1>
        <p>@yield('art-copy')</p>
    </section>
    @endif
    <section class="auth-form"><div class="auth-box">@yield('content')</div></section>
</div>
<script src="{{ asset('js/theme.js') }}"></script>
</body>
</html>

