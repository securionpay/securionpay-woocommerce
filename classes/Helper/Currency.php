<?php

class Currency
{
    /**
     * @param $currency
     * @return int
     */
    public static function getISO4217ExpByCurrency($currency)
    {
        $defaultExp = 2;
        $currencyToExpMap = array(
            0 => array('BIF', 'CLP', 'DJF', 'GNF', 'ISK', 'JPY', 'KMF', 'KRW', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'),
            3 => array('BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND')
        );

        foreach ($currencyToExpMap as $exp => $currencies) {
            if (in_array($currency, $currencies)) {
                return $exp;
            }
        }

        return $defaultExp;
    }

    /**
     * @param int $exp
     * @return int
     */
    public static function getMultiplierByExp($exp)
    {
        $exp = (int) $exp;
        return (int) pow(10, $exp);
    }

    /**
     * @param float $price
     * @param string $currency
     * @return int
     */
    public static function calculateMinorUnitsPriceForCurrency($price, $currency)
    {
        $price = (float) $price;
        $exp = self::getISO4217ExpByCurrency($currency);
        return (int) ($price * self::getMultiplierByExp($exp));
    }
}