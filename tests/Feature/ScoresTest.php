<?php

namespace Tests\Feature;

use App\Domain\Scores;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Cálculo de notas (auditoría F-16): lógica pura, sin base de datos. Exige decimales exactos y un único redondeo.
 */
final class ScoresTest extends TestCase
{
    public function test_value_accepts_the_scale_limits_and_two_decimals(): void
    {
        $this->assertSame('0.00', Scores::value('0'));
        $this->assertSame('100.00', Scores::value('100'));
        $this->assertSame('99.99', Scores::value('99.99'));
        $this->assertSame('40.00', Scores::value(40, '40'));
    }

    #[DataProvider('invalidValues')]
    public function test_value_rejects_out_of_range_malformed_or_float_input(mixed $input, string $max = '100'): void
    {
        $this->expectException(ValidationException::class);
        Scores::value($input, $max);
    }

    public static function invalidValues(): array
    {
        return [
            'sobre el máximo' => ['100.01'], 'negativa' => ['-1'], 'texto' => ['abc'], 'tres decimales' => ['1.234'],
            'coma decimal' => ['1,5'], 'float de PHP' => [1.5], 'nulo' => [null], 'mérito sobre 40' => ['40.01', '40'], 'vacía' => [''],
        ];
    }

    public function test_weights_must_be_positive_and_sum_exactly_100(): void
    {
        $this->assertSame(['A' => '60.00', 'B' => '40.00'], Scores::weights(['A' => '60', 'B' => '40']));
        foreach ([['A' => '60', 'B' => '39.99'], ['A' => '100', 'B' => '0'], ['A' => '110', 'B' => '-10'], []] as $invalid) {
            try {
                Scores::weights($invalid);
                $this->fail('Se esperaba rechazo de pesos inválidos.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
    }

    public function test_exam_is_the_weighted_average_scaled_to_60(): void
    {
        $weights = ['Soporte' => '50', 'Redes' => '25', 'Software' => '25'];
        $this->assertSame('48.00', Scores::exam($weights, ['Soporte' => '80', 'Redes' => '60', 'Software' => '100']));
        $this->assertSame('60.00', Scores::exam($weights, ['Soporte' => '100', 'Redes' => '100', 'Software' => '100']));
        $this->assertSame('0.00', Scores::exam($weights, ['Soporte' => '0', 'Redes' => '0', 'Software' => '0']));
    }

    #[DataProvider('roundingCases')]
    public function test_exam_rounds_half_up_once_at_the_end(string $note, string $expected): void
    {
        $this->assertSame($expected, Scores::exam(['Único' => '100'], ['Único' => $note]));
    }

    public static function roundingCases(): array
    {
        // 0.6 × nota: 59.994 → 59.99, 0.498 → 0.50, 0.510 → 0.51
        return ['baja' => ['99.99', '59.99'], 'sube a 0.50' => ['0.83', '0.50'], 'exacto' => ['0.85', '0.51']];
    }

    public function test_a_missing_note_is_not_treated_as_zero(): void
    {
        $this->expectException(ValidationException::class);
        Scores::exam(['A' => '50', 'B' => '50'], ['A' => '80']);
    }

    public function test_a_null_note_is_not_treated_as_zero(): void
    {
        $this->expectException(ValidationException::class);
        Scores::exam(['A' => '50', 'B' => '50'], ['A' => '80', 'B' => null]);
    }

    public function test_round_adds_half_a_cent_and_truncates(): void
    {
        $this->assertSame('1.01', Scores::round('1.005'));
        $this->assertSame('1.00', Scores::round('1.004'));
    }
}
