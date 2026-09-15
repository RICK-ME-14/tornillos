<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - fe_email_enviado_en: cuándo se envió el comprobante por correo al cliente.
 * - fe_resumen_*: seguimiento del resumen diario de boletas (RC) que agrupa
 *   la boleta ante SUNAT en producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('fe_email_enviado_en')->nullable()->after('fe_anulado_en');
            $table->string('fe_resumen_estado', 20)->nullable()->after('fe_email_enviado_en'); // PENDIENTE|ENVIADO|ACEPTADO|RECHAZADO
            $table->foreignId('fe_resumen_id')->nullable()->after('fe_resumen_estado');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn(['fe_email_enviado_en', 'fe_resumen_estado', 'fe_resumen_id']);
        });
    }
};
