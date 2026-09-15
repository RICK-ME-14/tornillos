<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Numeración correlativa de documentos internos (V-000001, C-000001,
 * COT-000001).
 *
 * Dos reglas que no son obvias y que ya costaron un fallo en produccion:
 *
 *  1. Se toma el MAYOR valor numérico, no la última fila por id. Si convive
 *     un número de otro formato -datos importados, un seeder- la última fila
 *     puede no ser la más alta y la secuencia se reinicia hasta chocar contra
 *     el índice único.
 *  2. Se bloquea la fila de la empresa. Sin eso, dos cajas registrando a la
 *     vez obtienen el mismo número y una de las dos operaciones se pierde.
 */
class Numerador
{
    /**
     * @param  class-string  $modelo   Modelo con columna `numero`
     * @param  string        $prefijo  Sin el guion: 'V', 'C', 'COT'
     */
    public static function siguiente(string $modelo, string $prefijo, int $digitos = 6): string
    {
        DB::table('empresas')->where('id', Tenant::id())->lockForUpdate()->first();

        // SUBSTRING empieza en 1: prefijo + guion + 1.
        $desde = strlen($prefijo) + 2;

        $ultimo = (int) $modelo::whereRaw('numero REGEXP ?', ["^{$prefijo}-[0-9]+$"])
            ->selectRaw("MAX(CAST(SUBSTRING(numero, {$desde}) AS UNSIGNED)) AS n")
            ->value('n');

        return $prefijo . '-' . str_pad((string) ($ultimo + 1), $digitos, '0', STR_PAD_LEFT);
    }
}
