<?php

namespace App\Modules\Produksi\Services;

class ServoInferenceService
{
    public const COEFFICIENT = 0.00045 * 1.59 * 60; // 0.04293
    public const DEFAULT_DAILY_TARGET_MT = 10.368;
    public const STANDARD_LINES = 12;
    public const HOURS_PER_SHIFT = 8;
    public const SHIFTS = ['S1', 'S2', 'S3'];
    public const TAKEUP_OPTIONS = [
        '1XL', '1XR', '2YL', '2YR', '3XL', '3XR',
        '4YL', '4YR', '5XL', '5XR', '6YL', '6YR',
    ];

    public static function servoFromCode(?string $value): ?string
    {
        $code = preg_replace('/\s+/', '', strtoupper((string) $value));
        $code = str_replace(['×', '✕', '*'], 'X', $code);

        if (preg_match('/^(?:\d+)?([XY])(?:[LR])?$/', $code, $matches)) {
            return $matches[1];
        }

        return null;
    }

    public static function infer(?string $takeUp, ?string $cooling, ?string $manualServo = null): array
    {
        $takeServo = self::servoFromCode($takeUp);
        $coolingServo = self::servoFromCode($cooling);

        $found = array_values(array_filter([$takeServo, $coolingServo]));
        $unique = array_unique($found);
        $conflict = count($unique) > 1;

        $manual = strtoupper(trim((string) $manualServo));
        $validManual = in_array($manual, ['X', 'Y'], true) ? $manual : null;

        $effectiveServo = null;
        if (!$conflict) {
            $effectiveServo = $found[0] ?? $validManual;
        }

        return [
            'servo'          => $effectiveServo,
            'servo_conflict' => $conflict,
            'take_up_servo'  => $takeServo,
            'cooling_servo'  => $coolingServo,
        ];
    }
}
