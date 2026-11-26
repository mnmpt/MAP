<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ isset($sectors) ? 'Register' : 'Sign in' }} · MAP | LGU Sulop</title><link rel="stylesheet" href="{{ asset('agriculture.css') }}"></head>
<body class="login">
<main class="login-layout">
    <section @class(['login-card', 'login-card-registration' => isset($sectors)]) aria-labelledby="sign-in-title">
        <div class="brand">
            <span class="login-mark"><span class="map-logo" aria-hidden="true"></span></span>
            <span>MAP<small>Management of Agriculture Production</small></span>
        </div>
        <h1 id="sign-in-title">{{ isset($sectors) ? 'Employee registration' : 'Sign In' }}</h1>
        <p>{{ isset($sectors) ? 'Request a sector. The Agriculture Head will review your registration.' : 'Use your LGU Sulop employee account.' }}</p>
        @if (session('notice')) <div class="notice" role="status">{{ session('notice') }}</div> @endif
        @if ($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="post" action="{{ route(isset($sectors) ? 'register' : 'login') }}" class="stack" @unless (isset($sectors)) data-auth-form @endunless>
            @csrf
            @isset($sectors)
                <label>Full name <input name="name" value="{{ old('name') }}" autocomplete="name" maxlength="255" required></label>
                <label>Sector
                    <select name="sector" required>
                        <option value="">Select your sector</option>
                        @foreach ($sectors as $key => $label)
                            <option value="{{ $key }}" @selected(old('sector') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endisset
            <label class="login-email">Email address <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required></label>
            @isset($sectors)
                <div class="password-field">
                    <label for="password">Password</label>
                    <div class="password-control">
                        <input id="password" type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" aria-describedby="password-hint" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Show password" hidden>Show</button>
                    </div>
                    <small id="password-hint">Use at least 12 characters.</small>
                </div>
                <div class="password-field">
                    <label for="password-confirmation">Confirm password</label>
                    <div class="password-control">
                        <input id="password-confirmation" type="password" name="password_confirmation" autocomplete="new-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="password-confirmation" aria-label="Show password confirmation" hidden>Show</button>
                    </div>
                </div>
            @else
                <div class="password-field">
                    <label for="password">Password</label>
                    <div class="password-control">
                        <input id="password" type="password" name="password" autocomplete="current-password" required>
                        <button class="password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Show password" hidden>Show</button>
                    </div>
                </div>
            @endisset
            <div class="login-actions">
                <button>{{ isset($sectors) ? 'Submit registration' : 'Sign in' }}</button>
                <a class="button login-switch" href="{{ route(isset($sectors) ? 'login' : 'register') }}">{{ isset($sectors) ? 'Back to sign in' : 'Register for a sector' }}</a>
            </div>
        </form>
    </section>
    <aside class="login-scene" aria-label="LGU Sulop">
        <div class="login-office">
            <span>LGU Sulop</span>
            <p>Municipal Agriculture Office</p>
            <small>Davao del Sur, Philippines</small>
        </div>
    </aside>
</main>
@unless (isset($sectors))
    @include('agriculture-auth-loading', ['message' => 'Signing in…'])
@endunless
<script src="{{ asset('auth-ui.js') }}" defer></script>
</body>
</html>
