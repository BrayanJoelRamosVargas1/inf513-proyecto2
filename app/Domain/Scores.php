<?php

namespace App\Domain;

use Illuminate\Validation\ValidationException;

final class Scores
{
    /** Esquemas de composición que el sistema sabe calcular; PENDING_COMPOSITION no es calculable. */
    public const CALCULABLE_SCHEMES = ['DEMO_AREAS_100', 'DEMO_COMPONENTS_100'];

    /** Máximos de la escala DEMO: mérito /40 + examen /60 = final /100. */
    public const MERIT_MAX = '40';

    public const EXAM_MAX = '60';

    public const EXAM_FACTOR = '0.6';

    public static function value(mixed $value, string $max = '100'): string
    {
        if (! is_string($value) && ! is_int($value)) {
            self::fail('Ingrese una nota decimal como texto.');
        }
        $value = (string) $value;
        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/D', $value) || bccomp($value, $max, 2) > 0) {
            self::fail("Nota inválida: rango 0.00–{$max}, máximo dos decimales.");
        }

        return bcadd($value, '0', 2);
    }

    public static function round(string $value): string
    {
        return bcadd($value, '0.005', 2);
    }

    public static function weights(array $weights): array
    {
        if (! $weights) {
            self::fail('Debe configurar áreas o componentes.');
        }
        $total = '0';
        foreach ($weights as $area => $weight) {
            // PHP convierte la clave "1" en entero al construir el arreglo: se acepta y se valida como texto.
            if ((! is_string($area) && ! is_int($area)) || ! preg_match('/^[\pL\pN _-]{1,70}$/u', (string) $area)) {
                self::fail('Nombre de área inválido.');
            }
            $weight = self::value($weight);
            if (bccomp($weight, '0', 2) <= 0) {
                self::fail('Cada peso debe ser mayor a cero.');
            }
            $weights[$area] = $weight;
            $total = bcadd($total, $weight, 2);
        }
        if (bccomp($total, '100', 2) !== 0) {
            self::fail('Los pesos deben sumar exactamente 100%.');
        }

        return $weights;
    }

    public static function exam(array $weights, array $notes): string
    {
        self::weights($weights);
        $sum = '0';
        foreach ($weights as $area => $weight) {
            if (! array_key_exists($area, $notes) || $notes[$area] === null) {
                self::fail('Faltan notas: una ausencia no equivale a cero.');
            }
            $sum = bcadd($sum, bcmul(self::value($notes[$area]), $weight, 8), 8);
        }

        return self::round(bcmul(bcdiv($sum, '100', 8), self::EXAM_FACTOR, 8));
    }

    public static function fail(string $message): never
    {
        throw ValidationException::withMessages(['proceso' => $message]);
    }
}
