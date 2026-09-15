<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\CotizacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpresaController;
use App\Http\Controllers\FacturacionController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\MarcaController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Web - Sistema de Ventas e Inventario
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('dashboard'));

// ---------- Autenticación ----------
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

Route::post('logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

// ---------- Área protegida ----------
Route::middleware('auth')->group(function () {

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
     | Módulos del sistema. Por ahora muestran una pantalla "en construcción".
     | En las siguientes iteraciones cada uno tendrá su CRUD completo
     | (Route::resource('productos', ProductoController::class), etc.).
     */

    // Ventas / POS
    Route::get('ventas/pos', [VentaController::class, 'pos'])->name('ventas.pos');
    Route::get('ventas/buscar-productos', [VentaController::class, 'buscarProductos'])->name('ventas.buscar');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');

    // Representación impresa del comprobante electrónico
    Route::get('ventas/{venta}/comprobante', [FacturacionController::class, 'comprobante'])->name('facturacion.comprobante');
    Route::get('ventas/{venta}/ticket', [FacturacionController::class, 'ticket'])->name('facturacion.ticket');
    // Bandeja de comprobantes electrónicos
    Route::get('comprobantes', [FacturacionController::class, 'comprobantes'])->name('comprobantes.index');
    Route::post('comprobantes/reenviar-pendientes', [FacturacionController::class, 'reenviarPendientes'])->name('facturacion.reenviar.pendientes');
    Route::post('comprobantes/{venta}/reenviar', [FacturacionController::class, 'reenviar'])->name('facturacion.reenviar');
    Route::get('comprobantes/{venta}/xml', [FacturacionController::class, 'descargarXml'])->name('facturacion.xml');
    Route::get('comprobantes/{venta}/cdr', [FacturacionController::class, 'descargarCdr'])->name('facturacion.cdr');
    Route::post('comprobantes/{venta}/email', [FacturacionController::class, 'enviarCorreo'])->name('facturacion.email');
    Route::post('comprobantes/resumen-diario', [FacturacionController::class, 'enviarResumenDiario'])->name('facturacion.resumen');

    // Cotizaciones
    Route::get('cotizaciones/punto', [CotizacionController::class, 'punto'])->name('cotizaciones.punto');
    Route::get('cotizaciones/export', [CotizacionController::class, 'export'])->name('cotizaciones.export');
    Route::get('cotizaciones', [CotizacionController::class, 'index'])->name('cotizaciones.index');
    Route::post('cotizaciones', [CotizacionController::class, 'store'])->name('cotizaciones.store');
    Route::get('cotizaciones/{cotizacion}', [CotizacionController::class, 'show'])->name('cotizaciones.show');
    Route::get('cotizaciones/{cotizacion}/imprimir', [CotizacionController::class, 'imprimir'])->name('cotizaciones.imprimir');
    Route::get('cotizaciones/{cotizacion}/ticket', [CotizacionController::class, 'ticket'])->name('cotizaciones.ticket');
    Route::post('cotizaciones/{cotizacion}/estado', [CotizacionController::class, 'estado'])->name('cotizaciones.estado');
    Route::post('cotizaciones/{cotizacion}/convertir', [CotizacionController::class, 'convertir'])->name('cotizaciones.convertir');

    // Compras
    Route::resource('compras', CompraController::class)->except(['edit', 'update']);
    Route::post('compras/{compra}/anular', [CompraController::class, 'anular'])->name('compras.anular');

    // Inventario (CRUD real)
    Route::get('productos/export', [ProductoController::class, 'export'])->name('productos.export');
    Route::resource('productos', ProductoController::class)->except('show');
    Route::resource('categorias', CategoriaController::class)->except('show');
    Route::resource('marcas', MarcaController::class)->except('show');
    Route::get('inventario/kardex', [InventarioController::class, 'kardex'])->name('inventario.kardex');
    Route::get('inventario/ajustes', [InventarioController::class, 'ajustes'])->name('inventario.ajustes');
    Route::post('inventario/ajustes', [InventarioController::class, 'guardarAjuste'])->name('inventario.ajustes.guardar');

    // Personas
    Route::resource('clientes', ClienteController::class)->except('show');
    Route::resource('proveedores', ProveedorController::class)->except('show');

    // Reportes
    Route::get('reportes/ventas', [ReporteController::class, 'ventas'])->name('reportes.ventas');
    Route::get('reportes/ventas/export', [ReporteController::class, 'exportVentas'])->name('reportes.ventas.export');
    Route::get('reportes/inventario', [ReporteController::class, 'inventario'])->name('reportes.inventario');
    Route::get('reportes/inventario/export', [ReporteController::class, 'exportInventario'])->name('reportes.inventario.export');
    Route::get('reportes/ganancias', [ReporteController::class, 'ganancias'])->name('reportes.ganancias');
    Route::get('reportes/ganancias/export', [ReporteController::class, 'exportGanancias'])->name('reportes.ganancias.export');

    // Configuración
    Route::resource('usuarios', UsuarioController::class)->except('show')->middleware('admin');
    Route::get('configuracion/empresa', [EmpresaController::class, 'edit'])->name('configuracion.empresa')->middleware('admin');
    Route::put('configuracion/empresa', [EmpresaController::class, 'update'])->name('configuracion.empresa.update')->middleware('admin');

    // Facturación Electrónica (SUNAT · Perú)
    Route::middleware('admin')->group(function () {
        Route::get('facturacion/configuracion', [FacturacionController::class, 'edit'])->name('facturacion.config');
        Route::put('facturacion/configuracion', [FacturacionController::class, 'update'])->name('facturacion.config.update');
        Route::post('facturacion/probar-conexion', [FacturacionController::class, 'probarConexion'])->name('facturacion.probar');
    });
});

