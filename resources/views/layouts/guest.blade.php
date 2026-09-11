@php
    use App\Models\SiteSetting;

    $siteSettings = SiteSetting::current();
    $authSiteName = $siteSettings->site_name ?: SiteSetting::DEFAULT_SITE_NAME;
    $authLogoUrl = $siteSettings->logoUrl('images/logo-brahma.png');
    $authRouteName = request()->route()?->getName();
    $authBackground = match ($authRouteName) {
        'login' => 'images/ui/casino/auth-login-bg.png',
        'register' => 'images/ui/casino/auth-register-bg.png',
        'password.request' => 'images/ui/casino/auth-forgot-bg.png',
        default => 'images/ui/casino/auth-forgot-bg.png',
    };
    $authPageTitle = match ($authRouteName) {
        'login' => 'Login',
        'register' => 'Create Account',
        'password.request' => 'Forgot Password',
        'password.reset' => 'Reset Password',
        'password.confirm' => 'Confirm Password',
        'verification.notice' => 'Verify Email',
        default => $authSiteName,
    };
    $isRegistration = $authRouteName === 'register';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $authPageTitle }} | {{ $authSiteName }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bb-auth-body">
    <main class="bb-auth-shell">
        <img src="{{ asset($authBackground) }}" alt="" aria-hidden="true" class="bb-auth-background">
        <div class="bb-auth-overlay" aria-hidden="true"></div>
        <div class="bb-auth-ambient bb-auth-ambient--purple" aria-hidden="true"></div>
        <div class="bb-auth-ambient bb-auth-ambient--blue" aria-hidden="true"></div>

        <div class="bb-auth-layout">
            <section class="bb-auth-brand" aria-label="{{ $authSiteName }}">
                <a href="{{ route('home') }}" class="bb-auth-brand-link">
                    <img src="{{ $authLogoUrl }}" alt="{{ $authSiteName }}" class="bb-auth-logo">
                </a>

                <div class="bb-auth-brand-copy">
                    <p class="bb-auth-eyebrow">Welcome to</p>
                    <h1>{{ $authSiteName }}</h1>
                    <p class="bb-auth-tagline">Play. Win. Dominate.</p>
                </div>
            </section>

            <section class="bb-auth-form-column">
                <div class="bb-auth-card {{ $isRegistration ? 'bb-auth-card--register' : 'bb-auth-card--standard' }}">
                    {{ $slot }}
                </div>
            </section>
        </div>
    </main>
</body>
</html>
