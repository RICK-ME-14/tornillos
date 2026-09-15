<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de acciones del super administrador (auditoría de la plataforma).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividad_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->index();   // super admin que ejecutó la acción
            $table->foreignId('empresa_id')->nullable()->index(); // tenant afectado (si aplica)
            $table->string('accion');            // suspender, activar, cambiar_plan, eliminar, impersonar, ...
            $table->string('descripcion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividad_logs');
    }
};
