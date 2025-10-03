<?php

return [
    'show_warnings'    => false,
    'public_path'      => null,
    'convert_entities' => true,

    'options' => [
        // where dompdf can write & read its cached font metrics
        'font_dir'   => storage_path('fonts'),
        'font_cache' => storage_path('fonts'),
        'temp_dir'   => sys_get_temp_dir(),

        // IMPORTANT: allow access to vendor/ so we can use DejaVu TTFs
        'chroot'     => realpath(base_path()),

        // make DejaVu Sans the default font
        'default_font'             => 'dejavu sans',

        'pdf_backend'               => 'CPDF',
        'default_media_type'        => 'screen',
        'default_paper_size'        => 'a4',
        'default_paper_orientation' => 'portrait',
        'dpi'                       => 96,

        'enable_php'                => false,
        'enable_javascript'         => true,
        'enable_remote'             => true,
        'allowed_remote_hosts'      => null,
        'font_height_ratio'         => 1.1,
        'enable_html5_parser'       => true,

        // protocols (inline data: url for the base64 logo)
        'allowed_protocols' => [
            'data://'  => ['rules' => []],
            'file://'  => ['rules' => []],
            'http://'  => ['rules' => []],
            'https://' => ['rules' => []],
        ],
    ],

    // 👇 Register DejaVu Sans from the dompdf package itself
    'font_family' => [
        'Dejavu sans' => [
            'R'  => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
            'B'  => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf'),
            'I'  => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Oblique.ttf'),
            'BI' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-BoldOblique.ttf'),
        ],
    ],
];
