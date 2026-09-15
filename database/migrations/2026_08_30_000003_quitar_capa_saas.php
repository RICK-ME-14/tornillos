<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convierte la aplicación de SaaS multiempresa a sistema de una sola empresa.
 *
 * Se retiran el catálogo de planes, las suscripciones, la configuración de
 * plataforma y la bitácora del super administrador, junto con los campos que
 * las empresas y los usuarios usaban para esa capa.
 *
 * La columna empresa_id de las tablas de negocio SE CONSERVA: ya no separa
 * clientes, pero sigue actuando como salvaguarda en todas las consultas a
 * través del scope global de los modelos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('suscripciones');
        Schema::dropIfExists('planes');
        Schema::dropIfExists('plataforma_configs');
        Schema::dropIfExists('actividad_logs');

        Schema::table('empresas', function (Blueprint $table) {
            foreach (['plan_id', 'estado_suscripcion', 'trial_termina_en', 'suscripcion_termina_en'] as $columna) {
                if (Schema::hasColumn('empresas', $columna)) {
                    $table->dropColumn($columna);
                }
            }
        });

        if (Schema::hasColumn('users', 'is_super')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_super');
            });
        }
    }

    public function down(): void
    {
        // Restituye la estructura mínima por si se revierte la migración.
        Schema::table('empresas', function (Blueprint $table) {
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('estado_suscripcion', 20)->default('trial');
            $table->date('trial_termina_en')->nullable();
            $table->date('suscripcion_termina_en')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_super')->default(false);
        });

        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('slug')->unique();
            $table->decimal('precio', 10, 2)->default(0);
            $table->unsignedInteger('limite_productos')->nullable();
            $table->unsignedInteger('limite_usuarios')->nullable();
            $table->unsignedInteger('limite_ventas_mes')->nullable();
            $table->string('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id')->index();
            $table->unsignedBigInteger('plan_id')->nullable()->index();
            $table->string('estado', 20)->default('trial');
            $table->decimal('monto', 10, 2)->default(0);
            $table->date('inicia_en')->nullable();
            $table->date('termina_en')->nullable();
            $table->timestamps();
        });

        Schema::create('plataforma_configs', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_saas')->nullable();
            $table->unsignedInteger('dias_trial')->default(14);
            $table->string('correo_soporte')->nullable();
            $table->string('moneda', 10)->default('S/');
            $table->string('logo')->nullable();
            $table->timestamps();
        });

        Schema::create('actividad_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->string('accion', 191);
            $table->string('descripcion', 191)->nullable();
            $table->timestamps();
        });
    }
};
