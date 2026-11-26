<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · MAP | LGU Sulop</title><link rel="stylesheet" href="{{ asset('agriculture.css') }}"></head>
<body class="login">
<header class="login-header">
    <div class="login-header-inner">
        <div class="brand"><span class="map-logo" aria-hidden="true"></span><span>MAP<small>Management of Agriculture Production</small></span></div>
        <p>Municipal Agriculture Office <span>Sulop, Davao del Sur</span></p>
    </div>
</header>
<main class="login-layout">
    <section class="login-card" aria-labelledby="sign-in-title">
        <h1 id="sign-in-title">Employee sign in</h1>
        <p>Use your LGU Sulop agriculture office account to access your workspace.</p>
        @if ($errors->any()) <div class="notice error" role="alert">{{ $errors->first() }}</div> @endif
        <form method="post" action="{{ route('login') }}" class="stack">
            @csrf
            <label>Email address <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required></label>
            <label>Password <input type="password" name="password" autocomplete="current-password" required></label>
            <button>Sign in</button>
        </form>
    </section>
    <div class="login-scene" aria-hidden="true"></div>
</main>
</body>
</html>
