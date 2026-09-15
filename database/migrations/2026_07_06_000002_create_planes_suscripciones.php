<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capa de suscripción del SaaS:
 *  - planes: catálogo de planes con precio y límites.
 *  - suscripciones: historial de suscripciones por empresa.
 *  - empresas: columnas del plan/estado vigente del tenant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->decimal('precio', 10, 2)->default(0); // precio mensual
            // Límites: null = ilimitado
            $table->integer('limite_productos')->nullable();
            $table->integer('limite_usuarios')->nullable();
            $table->integer('limite_ventas_mes')->nullable();
            $table->string('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->index();
            $table->foreignId('plan_id')->nullable()->index();
            $table->string('estado')->default('trial'); // trial, activa, vencida, cancelada
            $table->decimal('monto', 10, 2)->default(0);
            $table->date('inicia_en')->nullable();
            $table->date('termina_en')->nullable();
            $table->timestamps();
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->index();
            $table->string('estado_suscripcion')->default('trial')->after('plan_id');
            $table->date('trial_termina_en')->nullable()->after('estado_suscripcion');
            $table->date('suscripcion_termina_en')->nullable()->after('trial_termina_en');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['plan_id', 'estado_suscripcion', 'trial_termina_en', 'suscripcion_termina_en']);
        });
        Schema::dropIfExists('suscripciones');
        Schema::dropIfExists('planes');
    }
};
