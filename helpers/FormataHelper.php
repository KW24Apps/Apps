<?php

class FormataHelper
{
    public static function normalizarValor($entrada)
    {
        $valor = trim($entrada);

        if (preg_match('/^\d+\.\d{1,2}$/', $valor)) {
            return floatval($valor);
        }

        if (preg_match('/^\d+,\d{1,2}$/', $valor)) {
            return floatval(str_replace(',', '.', $valor));
        }

        if (strpos($valor, '.') !== false && strpos($valor, ',') !== false) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
            return floatval($valor);
        }

        return floatval(str_replace(',', '.', preg_replace('/[^\d.,]/', '', $valor)));
    }

    public static function valorPorExtenso($valor)
    {
        $fmt = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);
        $inteiro = floor($valor);
        $centavos = round(($valor - $inteiro) * 100);

        $texto = $fmt->format($inteiro) . ' reais';
        if ($centavos > 0) {
            $texto .= ' e ' . $fmt->format($centavos) . ' centavos';
        }

        return $texto;
    }
}
