<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Serie de notas de crédito para facturas (la de boletas ya existe en
 * serie_nota_credito). SUNAT usa series distintas por tipo de comprobante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturacion_configs', function (Blueprint $table) {
            $table->string('serie_nc_factura', 8)->default('FC01')->after('serie_nota_credito');
        });
    }

    public function down(): void
    {
        Schema::table('facturacion_configs', function (Blueprint $table) {
            $table->dropColumn('serie_nc_factura');
        });
    }
};
