<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk — IRRIS.next</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400..800;1,9..40,400..800&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="login-page">
    <main class="login-shell">
        <section class="login-intro" aria-label="IRRIS.next ERP">
            <img class="login-intro-image" src="{{ asset('images/indoprima-login.png') }}" alt="" aria-hidden="true" />
            <div class="login-intro-copy">
                <h1>Satu sistem.<br />Seluruh operasi.</h1>
            </div>
        </section>

        <section class="login-panel" aria-labelledby="login-title">
            <div class="login-card">
                <a class="brand-lockup login-brand" href="{{ route('login') }}" aria-label="IRRIS.next">
                    <div class="brand-main">
                        <div class="brand-mark brand-logo">
                            <img src="{{ asset('irrisnext.png') }}" alt="Logo irris.next" />
                        </div>
                        <div class="brand-copy"><strong>IRRIS<span>.next</span></strong></div>
                    </div>
                </a>
                <div class="login-heading">
                    <span class="login-eyebrow">SELAMAT DATANG</span>
                    <h2 id="login-title">Masuk ke akun Anda</h2>
                    <p>Gunakan username dan password untuk melanjutkan.</p>
                </div>
                @if($errors->any())
                    <div id="login-error" class="login-error" role="alert">{{ $errors->first() }}</div>
                @endif
                <form action="{{ route('login.store') }}" method="POST" class="login-form">
                    @csrf
                    <div class="login-field">
                        <label for="username">Username</label>
                        <input id="username" name="username" type="text" value="{{ old('username') }}" required maxlength="60" autocomplete="username" autocapitalize="none" spellcheck="false" autofocus placeholder="Masukkan username" @if($errors->has('username')) aria-invalid="true" aria-describedby="login-error" @endif />
                    </div>
                    <div class="login-field">
                        <label for="password">Password</label>
                        <input id="password" name="password" type="password" required maxlength="255" autocomplete="current-password" placeholder="Masukkan password" @if($errors->has('password')) aria-invalid="true" aria-describedby="login-error" @endif />
                    </div>
                    <button type="submit" class="login-submit">Masuk <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
                </form>
                <p class="login-help">Kendala akses? Hubungi administrator internal.</p>
            </div>
        </section>
    </main>
</body>
</html>
