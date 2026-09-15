<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos de anulación electrónica sobre la venta:
 *  - Comunicación de Baja (RA)  -> facturas
 *  - Nota de Crédito (07)       -> boletas y facturas
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Comunicación de baja (facturas)
            $table->string('fe_baja_ticket')->nullable()->after('fe_enviado_en');
            $table->string('fe_baja_estado', 20)->nullable()->after('fe_baja_ticket');
            $table->string('fe_baja_motivo')->nullable()->after('fe_baja_estado');

            // Nota de crédito
            $table->string('fe_nc_serie', 8)->nullable()->after('fe_baja_motivo');
            $table->unsignedBigInteger('fe_nc_correlativo')->nullable()->after('fe_nc_serie');
            $table->string('fe_nc_estado', 20)->nullable()->after('fe_nc_correlativo');
            $table->string('fe_nc_hash')->nullable()->after('fe_nc_estado');
            $table->string('fe_nc_motivo')->nullable()->after('fe_nc_hash');
            $table->string('fe_nc_xml_ruta')->nullable()->after('fe_nc_motivo');
            $table->string('fe_nc_cdr_ruta')->nullable()->after('fe_nc_xml_ruta');
            $table->timestamp('fe_anulado_en')->nullable()->after('fe_nc_cdr_ruta');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'fe_baja_ticket', 'fe_baja_estado', 'fe_baja_motivo',
                'fe_nc_serie', 'fe_nc_correlativo', 'fe_nc_estado', 'fe_nc_hash',
                'fe_nc_motivo', 'fe_nc_xml_ruta', 'fe_nc_cdr_ruta', 'fe_anulado_en',
            ]);
        });
    }
};
