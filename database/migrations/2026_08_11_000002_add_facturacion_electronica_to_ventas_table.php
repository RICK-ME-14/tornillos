<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de comprobante electrónico en cada venta.
 * fe_estado:
 *   NO_APLICA  -> ticket interno o FE deshabilitada
 *   PENDIENTE  -> por enviar a SUNAT
 *   ENVIADO    -> enviado, esperando CDR
 *   ACEPTADO   -> aceptado por SUNAT
 *   RECHAZADO  -> rechazado por SUNAT
 *   ERROR      -> fallo de comunicación/proceso
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('fe_serie', 8)->nullable()->after('tipo_comprobante');
            $table->unsignedBigInteger('fe_correlativo')->nullable()->after('fe_serie');
            $table->string('fe_estado', 20)->default('NO_APLICA')->after('fe_correlativo');
            $table->string('fe_hash')->nullable()->after('fe_estado');
            $table->string('fe_ticket')->nullable()->after('fe_hash');
            $table->text('fe_observacion')->nullable()->after('fe_ticket');
            $table->string('fe_xml_ruta')->nullable()->after('fe_observacion');
            $table->string('fe_cdr_ruta')->nullable()->after('fe_xml_ruta');
            $table->timestamp('fe_enviado_en')->nullable()->after('fe_cdr_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'fe_serie', 'fe_correlativo', 'fe_estado', 'fe_hash', 'fe_ticket',
                'fe_observacion', 'fe_xml_ruta', 'fe_cdr_ruta', 'fe_enviado_en',
            ]);
        });
    }
};
