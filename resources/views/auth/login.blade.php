<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#14181d">
    <title>Iniciar sesión · {{ $empresa->nombre ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('img/icono-180.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
</head>
<body>
<main class="acceso">

    {{-- ============ Marca ============ --}}
    <section class="acceso-marca">

        {{-- Trama de tuercas hexagonales: textura de taller, casi invisible. --}}
        <svg class="acceso-trama" aria-hidden="true" focusable="false">
            <defs>
                <pattern id="tuercas" width="56" height="97" patternUnits="userSpaceOnUse"
                         patternTransform="scale(.62) rotate(0)">
                    <polygon points="28,2 54,17 54,47 28,62 2,47 2,17"
                             fill="none" stroke="#fff" stroke-width="2.5"/>
                    <circle cx="28" cy="32" r="11" fill="none" stroke="#fff" stroke-width="2.5"/>
                    <polygon points="0,50 26,65 26,95 0,110 -26,95 -26,65"
                             fill="none" stroke="#fff" stroke-width="2.5"/>
                    <circle cx="0" cy="80" r="11" fill="none" stroke="#fff" stroke-width="2.5"/>
                    <polygon points="56,50 82,65 82,95 56,110 30,95 30,65"
                             fill="none" stroke="#fff" stroke-width="2.5"/>
                    <circle cx="56" cy="80" r="11" fill="none" stroke="#fff" stroke-width="2.5"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#tuercas)"/>
        </svg>

        <div class="acceso-logo">
            @if($empresa->logo ?? false)
                <img src="{{ asset('storage/'.$empresa->logo) }}" alt="{{ $empresa->nombre }}">
            @else
                <img src="{{ asset('img/logo.svg') }}" alt="" width="58" height="58">
            @endif
        </div>

        <h1 class="acceso-nombre">{{ $empresa->nombre ?? config('app.name') }}</h1>
        <p class="acceso-rubro">Lubricantes &amp; Pernos</p>

        <p class="acceso-desc">
            Control de ventas, stock y comprobantes electrónicos para tu negocio de
            aceites, repuestos y fijaciones.
        </p>

        <ul class="acceso-rasgos">
            <li>
                <span class="ico"><i class="fa-solid fa-oil-can"></i></span>
                <span>Aceites y lubricantes</span>
            </li>
            <li>
                <span class="ico"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                <span>Pernos y fijaciones</span>
            </li>
            <li>
                <span class="ico"><i class="fa-solid fa-file-invoice-dollar"></i></span>
                <span>Facturación SUNAT</span>
            </li>
        </ul>
    </section>

    {{-- ============ Formulario ============ --}}
    <section class="acceso-form">
        <h2>Iniciar sesión</h2>
        <p class="sub">Ingresa tus credenciales para entrar al sistema.</p>

        @if ($errors->any())
            <div class="acceso-error" role="alert">
                <i class="fa-solid fa-circle-exclamation"></i>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="campo">
                <label for="email">Correo electrónico</label>
                <div class="campo-caja">
                    <i class="fa-solid fa-envelope"></i>
                    <input type="email" name="email" id="email"
                           value="{{ old('email') }}" placeholder="tucorreo@empresa.com"
                           autocomplete="username" autofocus required>
                </div>
            </div>

            <div class="campo">
                <label for="password">Contraseña</label>
                <div class="campo-caja">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="password" id="password"
                           placeholder="••••••••" autocomplete="current-password" required>
                </div>
            </div>

            <label class="recordar" for="remember">
                <input type="checkbox" name="remember" id="remember">
                <span>Mantener sesión iniciada</span>
            </label>

            <button type="submit" class="acceso-btn">
                <i class="fa-solid fa-right-to-bracket"></i> Ingresar
            </button>
        </form>

        <p class="acceso-pie">
            <strong>{{ $empresa->nombre ?? config('app.name') }}</strong>
            @if($empresa->ruc ?? false) · RUC {{ $empresa->ruc }} @endif
            <br>¿Problemas para entrar? Contacta al administrador del sistema.
        </p>
    </section>

</main>
</body>
</html>
