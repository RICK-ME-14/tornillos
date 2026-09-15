<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resúmenes diarios de boletas (Resumen de Comprobantes · RC) enviados a SUNAT.
 * Cada fila corresponde a un envío por empresa y fecha de referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturacion_resumenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->date('fecha_referencia');           // día de las boletas resumidas
            $table->date('fecha_generacion');           // día de generación/envío
            $table->string('identificador', 30);        // RC-YYYYMMDD-#
            $table->unsignedInteger('cantidad')->default(0);
            $table->string('ticket')->nullable();       // ticket devuelto por SUNAT
            $table->string('estado', 20)->default('PENDIENTE'); // PENDIENTE|ENVIADO|ACEPTADO|RECHAZADO|ERROR
            $table->text('mensaje')->nullable();
            $table->string('xml_ruta')->nullable();
            $table->string('cdr_ruta')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha_referencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturacion_resumenes');
    }
};
