<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuración global de la plataforma SaaS (fila única, editable por el super admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plataforma_configs', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_saas')->default('SaaS Ventas e Inventario');
            $table->string('logo')->nullable();
            $table->unsignedInteger('dias_trial')->default(14);
            $table->string('correo_soporte')->nullable();
            $table->string('moneda', 5)->default('S/');
            $table->text('mensaje_bienvenida')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plataforma_configs');
    }
};
