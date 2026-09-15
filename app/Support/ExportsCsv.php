<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCsv
{
    /**
     * Devuelve una descarga CSV compatible con Excel (con BOM UTF-8 y separador ';').
     *
     * @param string $nombre  Nombre del archivo (sin extensión)
     * @param array  $cabeceras  Fila de encabezados
     * @param iterable $filas  Cada fila es un array de valores
     */
    protected function descargarCsv(string $nombre, array $cabeceras, iterable $filas): StreamedResponse
    {
        $archivo = $nombre . '_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($cabeceras, $filas) {
            $out = fopen('php://output', 'w');

            // BOM para que Excel reconozca UTF-8 (acentos, ñ, símbolos)
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, $cabeceras, ';');
            foreach ($filas as $fila) {
                fputcsv($out, $fila, ';');
            }

            fclose($out);
        }, $archivo, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
