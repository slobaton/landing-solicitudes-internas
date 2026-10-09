<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Fecha limite = registro + N horas laborales (RN-D08, D-05, contrato §4.8).
 * Calculo puro (sin BD): recibe horario y feriados ya leidos.
 */
final class FechaLimiteCalculator
{
    /** Tope de dias a recorrer: evita bucles si la configuracion es invalida. */
    public const MAX_DIAS = 400;

    /**
     * @param array<int, array{inicio: string, fin: string, activo: bool}> $horario Dia ISO 1..7
     * @param array<string, true>                                          $feriados Fechas Y-m-d
     */
    public function calcular(\DateTimeImmutable $registro, int $horas, array $horario, array $feriados): \DateTimeImmutable
    {
        if ($horas <= 0) {
            throw new \InvalidArgumentException('Las horas de atención deben ser positivas');
        }

        $restante = $horas * 3600;
        $dia = $registro->setTime(0, 0);

        for ($i = 0; $i < self::MAX_DIAS; $i++, $dia = $dia->modify('+1 day')) {
            $franja = $horario[(int) $dia->format('N')] ?? null;
            if ($franja === null || !$franja['activo'] || isset($feriados[$dia->format('Y-m-d')])) {
                continue;
            }

            $inicio = $this->conHora($dia, $franja['inicio']);
            $fin = $this->conHora($dia, $franja['fin']);
            if ($registro > $inicio) {
                $inicio = $registro;
            }
            if ($inicio >= $fin) {
                continue;
            }

            $disponible = $fin->getTimestamp() - $inicio->getTimestamp();
            if ($disponible >= $restante) {
                return $inicio->modify('+' . $restante . ' seconds');
            }
            $restante -= $disponible;
        }

        throw new \RuntimeException('No hay días laborales configurados para calcular la fecha límite');
    }

    private function conHora(\DateTimeImmutable $dia, string $hora): \DateTimeImmutable
    {
        [$h, $m, $s] = array_map('intval', explode(':', $hora) + [0, 0, 0]);
        return $dia->setTime($h, $m, $s);
    }
}
