<?php

return [
    'marketing' => [
        'label' => 'Marketing',
        'legacy_module' => 'marketing',
        'features' => [
            'sales' => [
                'label' => 'Penjualan',
                'permissions' => [
                    'marketing.sales.view' => 'Lihat penjualan',
                    'marketing.sales.create' => 'Catat penjualan',
                ],
            ],
        ],
    ],
    'ppic_ic' => [
        'label' => 'PPIC — Inventory Control',
        'legacy_module' => 'ppic',
        'features' => [
            'sales' => ['label' => 'Kesiapan Penjualan', 'permissions' => [
                'ppic.ic.sales.view' => 'Lihat kesiapan penjualan',
                'ppic.ic.sales.check' => 'Periksa kebutuhan bahan',
                'ppic.ic.sales.request' => 'Ajukan kekurangan bahan',
                'ppic.ic.sales.handover' => 'Serahkan ke Production Plan',
            ]],
            'requests' => ['label' => 'Pengajuan Pembelian', 'permissions' => [
                'ppic.ic.requests.view' => 'Lihat pengajuan pembelian',
                'ppic.ic.requests.create' => 'Buat pengajuan pembelian',
            ]],
            'receipts' => ['label' => 'Barang Masuk', 'permissions' => [
                'ppic.ic.receipts.view' => 'Lihat barang masuk',
                'ppic.ic.receipts.create' => 'Catat penerimaan barang',
            ]],
            'issues' => ['label' => 'Barang Keluar', 'permissions' => [
                'ppic.ic.issues.view' => 'Lihat Barang Keluar',
                'ppic.ic.issues.create' => 'Buat, ubah, dan batalkan draft Barang Keluar',
                'ppic.ic.issues.confirm' => 'Konfirmasi pengeluaran barang',
                'ppic.ic.issues.reverse' => 'Balikkan pengeluaran barang',
            ]],
            'stock' => ['label' => 'Stok', 'permissions' => [
                'ppic.ic.stock.view' => 'Lihat stok',
            ]],
        ],
    ],
    'purchasing' => [
        'label' => 'Purchasing',
        'legacy_module' => 'purchasing',
        'features' => [
            'requests' => ['label' => 'Pengajuan dari IC', 'permissions' => [
                'purchasing.requests.view' => 'Lihat pengajuan dari IC',
            ]],
            'orders' => ['label' => 'Pesanan pembelian', 'permissions' => [
                'purchasing.orders.view' => 'Lihat pesanan pembelian',
                'purchasing.orders.create' => 'Proses dan buat pesanan pembelian',
            ]],
        ],
    ],
];