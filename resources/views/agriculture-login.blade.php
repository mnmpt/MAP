<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ isset($sectors) ? 'Register' : 'Sign in' }} · MAP | LGU Sulop</title><link rel="stylesheet" href="{{ asset('agriculture.css') }}"></head>
<body class="login">
<main class="login-layout">
    <aside class="login-scene" aria-label="LGU Sulop">
        <div class="login-office">
            <span>LGU Sulop</span>
            <p>Municipal Agriculture Office</p>
            <small>Davao del Sur, Philippines</small>
        </div>
    </aside>
    <section @class(['login-card', 'login-card-registration' => isset($sectors)]) aria-labelledby="sign-in-title">
        <div class="brand">
            <span class="login-mark"><span class="map-logo" aria-hidden="true"></span></span>
            <span>MAP<small>Management of Agriculture Production</small></span>
        </div>
        <h1 id="sign-in-title">{{ isset($sectors) ? 'Employee registration' : 'Sign In' }}</h1>
        <p>{{ isset($sectors) ? 'Request a sector. The Agriculture Head will review your registration.' : 'Use your LGU Sulop employee account.' }}</p>
        @if (session('notice')) <div class="notice" role="status">{{ session('notice') }}</div> @endif
        @if ($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="post" action="{{ route(isset($sectors) ? 'register' : 'login') }}" class="stack">
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
                <label>Password <input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72" required><small>Use at least 12 characters.</small></label>
                <label>Confirm password <input type="password" name="password_confirmation" autocomplete="new-password" required></label>
            @else
                <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
            @endisset
            <button>{{ isset($sectors) ? 'Submit registration' : 'Sign in' }}</button>
        </form>
        <p class="login-switch"><a href="{{ route(isset($sectors) ? 'login' : 'register') }}">{{ isset($sectors) ? 'Back to sign in' : 'Register for a sector' }}</a></p>
    </section>
</main>
</body>
</html>
