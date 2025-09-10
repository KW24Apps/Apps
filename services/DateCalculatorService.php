<?php

namespace Services;

use DateTime;
use Exception;

class DateCalculatorService
{
    /**
     * Calcula a diferença em dias entre duas datas.
     *
     * @param string $date1String A primeira data (data a vencer), no formato DD/MM/YYYY.
     * @param string|null $date2String A segunda data (data atual), no formato DD/MM/YYYY. Se nula, usa a data atual.
     * @return int A diferença em dias. Positivo se date1 for futura, negativo se date1 for passada.
     * @throws Exception Se as datas estiverem em formato inválido.
     */
    public function calculateDifferenceInDays(string $date1String, ?string $date2String = null): int
    {
        try {
            $date1 = DateTime::createFromFormat('d/m/Y', $date1String);
            if (!$date1) {
                throw new Exception("Formato de data inválido para data1: {$date1String}. Esperado DD/MM/YYYY.");
            }
            $date1->setTime(0, 0, 0); // Zera a hora para cálculo apenas de dias

            $date2 = $date2String ? DateTime::createFromFormat('d/m/Y', $date2String) : new DateTime();
            if (!$date2) {
                throw new Exception("Formato de data inválido para data2: {$date2String}. Esperado DD/MM/YYYY.");
            }
            $date2->setTime(0, 0, 0); // Zera a hora para cálculo apenas de dias

            // Calcula a diferença: (data a vencer) - (data atual)
            // Se date1 for futura, o intervalo será positivo.
            $interval = $date1->diff($date2);
            $days = (int)$interval->format('%a'); // Apenas o número absoluto de dias
            
            // Ajusta o sinal: se date1 for anterior a date2, o resultado deve ser negativo
            if ($date1 < $date2) {
                $days = -$days;
            }

            return $days;

        } catch (Exception $e) {
            // Em um ambiente real, você logaria este erro.
            // Por enquanto, relançamos para ser tratado pelo controller.
            throw $e;
        }
    }
}
