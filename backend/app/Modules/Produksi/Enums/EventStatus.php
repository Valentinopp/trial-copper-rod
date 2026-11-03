<?php

namespace App\Modules\Produksi\Enums;

class EventStatus
{
    public const OPTIONS = [
        'panen' => [
            'value' => 'panen',
            'label' => 'Panen',
            'color' => 'green',
        ],
        'putus' => [
            'value' => 'putus',
            'label' => 'Putus',
            'color' => 'yellow',
        ],
    ];

    public static function allowedValues(): array
    {
        return array_keys(self::OPTIONS);
    }

    public static function label(?string $value): string
    {
        $normalized = self::normalize($value);
        return self::OPTIONS[$normalized]['label'] ?? '—';
    }

    public static function color(?string $value): string
    {
        $normalized = self::normalize($value);
        return self::OPTIONS[$normalized]['color'] ?? '';
    }

    public static function normalize(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        $normalized = mb_strtolower($raw);
        if (isset(self::OPTIONS[$normalized])) {
            return $normalized;
        }

        $legacy = ['panen_stop' => 'panen', 'panen_continue' => 'panen', 'putus_tarik_lot_baru' => 'putus', 'tarik_lot_baru' => ''];
        if (array_key_exists($normalized, $legacy)) {
            return $legacy[$normalized];
        }

        $label = str_replace(['—', '–', '-'], ' ', $normalized);
        $label = preg_replace('/[^a-z0-9]+/', ' ', $label);
        $label = trim(preg_replace('/\s+/', ' ', $label));

        $map = [
            'panen selesai mesin berhenti' => 'panen',
            'panen selesai berhenti'       => 'panen',
            'mesin berhenti'               => 'panen',
            'panen stop'                   => 'panen',
            'panen selesai mesin lanjut'   => 'panen',
            'panen selesai lanjut'         => 'panen',
            'mesin lanjut'                 => 'panen',
            'panen continue'               => 'panen',
            'panen lanjut lot berikutnya'  => 'panen',
            'putus'                        => 'putus',
            'kawat putus'                  => 'putus',
            'tarik lot baru'               => '',
            'menarik lot baru'             => '',
            'putus tarik lot baru'         => 'putus',
            'putus menarik lot baru'       => 'putus',
            'kawat putus tarik lot baru'   => 'putus',
            'kawat putus menarik lot baru' => 'putus',
        ];

        return $map[$label] ?? $raw;
    }
}
