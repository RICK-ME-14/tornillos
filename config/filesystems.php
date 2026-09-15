<?php

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
        ],

        /*
         | Comprobantes electrónicos (XML firmados, CDR y resúmenes de SUNAT).
         | Disco propio para que todo el respaldo fiscal viva en una sola
         | carpeta —storage/app/facturacion— junto al certificado y los PDF.
         | Nunca debe ser accesible desde la web.
         */
        'facturacion' => [
            'driver' => 'local',
            'root' => storage_path('app/facturacion'),
            'serve' => false,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
