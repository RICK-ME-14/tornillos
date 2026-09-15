<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cotizaciones.
 *
 * Una cotización es una propuesta de precio: NO mueve stock ni genera
 * comprobante. Solo al convertirse en venta descuenta inventario y, si la
 * facturación está activa, emite el comprobante.
 *
 * Los precios, la unidad y la afectación al IGV se congelan en cada línea:
 * lo cotizado debe respetarse aunque el producto cambie después.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('numero')->unique();              // COT-000001
            $table->foreignId('cliente_id')->nullable()->constrained('clientes');
            $table->foreignId('user_id')->nullable()->constrained('users');

            $table->date('fecha');
            $table->date('valida_hasta');

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('impuesto', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // PENDIENTE | ACEPTADA | RECHAZADA | ANULADA
            // "Vencida" no se guarda: se deduce de valida_hasta.
            $table->string('estado', 20)->default('PENDIENTE');

            // Venta que nació de esta cotización, si se convirtió.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();

            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
            $table->index(['empresa_id', 'fecha']);
        });

        Schema::create('cotizacion_detalles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cotizacion_id')->constrained('cotizaciones')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');

            $table->string('descripcion');                   // nombre al cotizar
            $table->integer('cantidad');
            $table->decimal('precio', 12, 2);
            $table->string('tipo_afectacion_igv', 2)->default('10');
            $table->string('unidad_sunat', 5)->default('NIU');
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotizacion_detalles');
        Schema::dropIfExists('cotizaciones');
    }
};
