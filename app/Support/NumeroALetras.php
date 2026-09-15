<?php

namespace App\Support;

/**
 * Convierte un importe a su representación en letras para la leyenda del
 * comprobante (código 1000 de SUNAT). Ej: 118.00 -> "CIENTO DIECIOCHO CON 00/100 SOLES".
 */
class NumeroALetras
{
    public static function moneda(float $monto, string $moneda = 'S/'): string
    {
        $monto = round($monto, 2);
        $entero = (int) floor($monto);
        $decimales = (int) round(($monto - $entero) * 100);
        if ($decimales >= 100) { // borde por redondeo (p. ej. 0.999)
            $entero += 1;
            $decimales = 0;
        }

        $nombreMoneda = match (trim($moneda)) {
            'S/', 'S/.', 'PEN' => 'SOLES',
            '$', 'USD' => 'DÓLARES AMERICANOS',
            '€', 'EUR' => 'EUROS',
            default => 'SOLES',
        };

        return sprintf('%s CON %02d/100 %s', static::entero($entero), $decimales, $nombreMoneda);
    }

    public static function entero(int $n): string
    {
        if ($n === 0) {
            return 'CERO';
        }

        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
            'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $decenas = ['', '', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
            'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

        $texto = '';

        if ($n >= 1000000) {
            $millones = intdiv($n, 1000000);
            $texto .= ($millones === 1 ? 'UN MILLÓN ' : static::entero($millones) . ' MILLONES ');
            $n %= 1000000;
        }

        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $texto .= ($miles === 1 ? 'MIL ' : static::entero($miles) . ' MIL ');
            $n %= 1000;
        }

        if ($n >= 100) {
            if ($n === 100) {
                $texto .= 'CIEN ';
                $n = 0;
            } else {
                $texto .= $centenas[intdiv($n, 100)] . ' ';
                $n %= 100;
            }
        }

        if ($n >= 20) {
            $d = intdiv($n, 10);
            $u = $n % 10;
            if ($d === 2 && $u > 0) {
                $texto .= 'VEINTI' . strtolower($unidades[$u]);
                $texto = strtoupper($texto) . ' ';
            } else {
                $texto .= $decenas[$d];
                if ($u > 0) {
                    $texto .= ' Y ' . $unidades[$u];
                }
                $texto .= ' ';
            }
        } elseif ($n > 0) {
            $texto .= $unidades[$n] . ' ';
        }

        return trim($texto);
    }
}
