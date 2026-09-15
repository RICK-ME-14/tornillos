<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registra el efectivo recibido y el vuelto en las ventas en efectivo,
 * para que queden en el ticket y en el historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->decimal('efectivo_recibido', 12, 2)->nullable()->after('total');
            $table->decimal('vuelto', 12, 2)->nullable()->after('efectivo_recibido');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['efectivo_recibido', 'vuelto']);
        });
    }
};
