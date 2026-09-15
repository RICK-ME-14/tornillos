<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración de Facturación Electrónica (Perú - SUNAT).
 * Una fila por empresa (tenant). Guarda el estado, el modo de emisión,
 * los datos del emisor y las credenciales para el envío de comprobantes
 * electrónicos (boletas, facturas y notas de crédito) bajo UBL 2.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturacion_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // Estado y modo
            $table->boolean('habilitado')->default(false);        // emite ante SUNAT
            $table->boolean('emitir_automatico')->default(true);  // al cerrar la venta
            $table->string('driver', 30)->default('none');        // none | greenter
            $table->string('entorno', 20)->default('beta');       // beta | produccion
            $table->string('pais', 5)->default('PE');

            // Datos del emisor (aparecen en el comprobante)
            $table->string('ruc', 15)->nullable();
            $table->string('razon_social')->nullable();
            $table->string('nombre_comercial')->nullable();
            $table->string('direccion_fiscal')->nullable();
            $table->string('ubigeo', 10)->nullable();
            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('distrito')->nullable();

            // Series de comprobantes
            $table->string('serie_boleta', 8)->default('B001');
            $table->string('serie_factura', 8)->default('F001');
            $table->string('serie_nota_credito', 8)->default('BC01');

            // Credenciales SUNAT
            $table->string('sol_user')->nullable();               // Usuario Clave SOL
            $table->text('sol_pass')->nullable();                 // Clave SOL (encriptada)
            $table->string('certificado_ruta')->nullable();       // ruta al .pem
            $table->text('certificado_pass')->nullable();         // clave del certificado (encriptada)

            // Estado de la última prueba de conexión
            $table->timestamp('probado_en')->nullable();
            $table->string('estado_conexion', 30)->nullable();    // ok | error | null
            $table->text('mensaje_conexion')->nullable();

            $table->timestamps();

            $table->unique('empresa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_configs');
    }
};
