<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('img/icono-180.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>
<body>
<div class="app">

    {{-- ============ SIDEBAR ============ --}}
    <aside class="sidebar">
        <div class="sidebar-brand">
            @if(($empresa->logo ?? false))
                <img class="logo" src="{{ asset('storage/'.$empresa->logo) }}" alt="{{ $empresa->nombre }}">
            @else
                <img class="logo" src="{{ asset('img/logo.svg') }}" alt="" width="38" height="38">
            @endif
        </div>
        <div class="sidebar-title"><i class="fa-solid fa-gauge-high"></i> <span>{{ $empresa->nombre ?? config('app.name') }}</span></div>

        <ul class="sidebar-menu">
            <li class="g-inicio"><a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <i class="fa-solid fa-desktop"></i> <span class="txt">Dashboard</span></a></li>

            <li class="group-label g-ventas">Ventas</li>
            <li class="g-ventas"><a href="{{ route('ventas.pos') }}" class="{{ request()->routeIs('ventas.pos') ? 'active' : '' }}">
                <i class="fa-solid fa-cash-register"></i> <span class="txt">Punto de Venta</span></a></li>
            <li class="g-ventas"><a href="{{ route('ventas.index') }}" class="{{ request()->routeIs('ventas.index') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-line"></i> <span class="txt">Ventas</span></a></li>
            <li class="g-ventas"><a href="{{ route('cotizaciones.punto') }}" class="{{ request()->routeIs('cotizaciones.punto') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice"></i> <span class="txt">Punto de Cotización</span></a></li>
            <li class="g-ventas"><a href="{{ route('cotizaciones.index') }}" class="{{ request()->routeIs('cotizaciones.index') || request()->routeIs('cotizaciones.show') ? 'active' : '' }}">
                <i class="fa-solid fa-file-signature"></i> <span class="txt">Cotizaciones</span></a></li>
            <li class="g-ventas"><a href="{{ route('comprobantes.index') }}" class="{{ request()->routeIs('comprobantes.*') ? 'active' : '' }}">
                <i class="fa-solid fa-receipt"></i> <span class="txt">Comprobantes</span></a></li>

            <li class="group-label g-inventario">Inventario</li>
            <li class="g-inventario"><a href="{{ route('productos.index') }}" class="{{ request()->routeIs('productos.*') ? 'active' : '' }}">
                <i class="fa-solid fa-box"></i> <span class="txt">Productos</span></a></li>
            <li class="g-inventario"><a href="{{ route('categorias.index') }}" class="{{ request()->routeIs('categorias.*') ? 'active' : '' }}">
                <i class="fa-solid fa-tags"></i> <span class="txt">Categorías</span></a></li>
            <li class="g-inventario"><a href="{{ route('marcas.index') }}" class="{{ request()->routeIs('marcas.*') ? 'active' : '' }}">
                <i class="fa-solid fa-trademark"></i> <span class="txt">Marcas</span></a></li>
            <li class="g-inventario"><a href="{{ route('inventario.kardex') }}" class="{{ request()->routeIs('inventario.kardex') ? 'active' : '' }}">
                <i class="fa-solid fa-arrow-right-arrow-left"></i> <span class="txt">Kardex</span></a></li>
            <li class="g-inventario"><a href="{{ route('inventario.ajustes') }}" class="{{ request()->routeIs('inventario.ajustes') ? 'active' : '' }}">
                <i class="fa-solid fa-sliders"></i> <span class="txt">Ajustes de Stock</span></a></li>

            <li class="group-label g-compras">Compras</li>
            <li class="g-compras"><a href="{{ route('compras.index') }}" class="{{ request()->routeIs('compras.*') ? 'active' : '' }}">
                <i class="fa-solid fa-truck-ramp-box"></i> <span class="txt">Compras</span></a></li>
            <li class="g-compras"><a href="{{ route('proveedores.index') }}" class="{{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
                <i class="fa-solid fa-industry"></i> <span class="txt">Proveedores</span></a></li>

            <li class="group-label g-personas">Personas</li>
            <li class="g-personas"><a href="{{ route('clientes.index') }}" class="{{ request()->routeIs('clientes.*') ? 'active' : '' }}">
                <i class="fa-solid fa-users"></i> <span class="txt">Clientes</span></a></li>

            <li class="group-label g-reportes">Reportes</li>
            <li class="g-reportes"><a href="{{ route('reportes.ventas') }}" class="{{ request()->routeIs('reportes.ventas') ? 'active' : '' }}">
                <i class="fa-solid fa-chart-column"></i> <span class="txt">Ventas</span></a></li>
            <li class="g-reportes"><a href="{{ route('reportes.inventario') }}" class="{{ request()->routeIs('reportes.inventario') ? 'active' : '' }}">
                <i class="fa-solid fa-warehouse"></i> <span class="txt">Inventario</span></a></li>
            <li class="g-reportes"><a href="{{ route('reportes.ganancias') }}" class="{{ request()->routeIs('reportes.ganancias') ? 'active' : '' }}">
                <i class="fa-solid fa-coins"></i> <span class="txt">Ganancias</span></a></li>

            <li class="group-label g-config">Configuración</li>
            @if(auth()->user()->rol === 'admin')
            <li class="g-config"><a href="{{ route('usuarios.index') }}" class="{{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                <i class="fa-solid fa-user-gear"></i> <span class="txt">Usuarios</span></a></li>
            @endif
            <li class="g-config"><a href="{{ route('configuracion.empresa') }}" class="{{ request()->routeIs('configuracion.*') ? 'active' : '' }}">
                <i class="fa-solid fa-building"></i> <span class="txt">Empresa</span></a></li>
            @if(auth()->user()->rol === 'admin')
            <li class="g-config"><a href="{{ route('facturacion.config') }}" class="{{ request()->routeIs('facturacion.*') ? 'active' : '' }}">
                <i class="fa-solid fa-file-invoice-dollar"></i> <span class="txt">Facturación Electrónica</span></a></li>
            @endif
        </ul>
    </aside>

    {{-- ============ MAIN ============ --}}
    <div class="main">
        <header class="topbar">
            <button class="toggle" onclick="document.querySelector('.sidebar').classList.toggle('collapsed')">
                <i class="fa-solid fa-bars"></i>
            </button>
            <div class="spacer"></div>
            <div class="top-actions">
                {{-- Campana: cuenta cosas reales y lleva a donde se resuelven.
                     Sin nada que avisar, se muestra apagada y sin globo. --}}
                @if(($avisos['total'] ?? 0) > 0)
                    <details class="avisos">
                        <summary class="ico" title="Avisos" aria-label="{{ $avisos['total'] }} aviso(s)">
                            <i class="fa-regular fa-bell"></i>
                            <span class="badge">{{ $avisos['total'] }}</span>
                        </summary>
                        <div class="avisos-panel">
                            <div class="avisos-tit">Requieren tu atención</div>
                            @foreach($avisos['items'] as $a)
                                <a class="aviso {{ $a['tono'] }}" href="{{ $a['url'] }}">
                                    <span class="punto"></span>
                                    <span>{{ $a['texto'] }}</span>
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @else
                    <span class="ico ico-apagado" title="Sin avisos"><i class="fa-regular fa-bell"></i></span>
                @endif
                <div class="user">
                    <div class="avatar">{{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}</div>
                    <span style="font-size:13px">{{ auth()->user()->name ?? 'Usuario' }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn" title="Cerrar sesión">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="content">
            @include('layouts.flash')
            @yield('content')
        </main>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
@stack('scripts')
</body>
</html>
