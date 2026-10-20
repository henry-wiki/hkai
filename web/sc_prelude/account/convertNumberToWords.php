<?php

function convertNumberToWords($number)
{
    $ones = [
        0 => '', 1 => 'ONE', 2 => 'TWO', 3 => 'THREE', 4 => 'FOUR',
        5 => 'FIVE', 6 => 'SIX', 7 => 'SEVEN', 8 => 'EIGHT', 9 => 'NINE',
        10 => 'TEN', 11 => 'ELEVEN', 12 => 'TWELVE', 13 => 'THIRTEEN',
        14 => 'FOURTEEN', 15 => 'FIFTEEN', 16 => 'SIXTEEN', 17 => 'SEVENTEEN',
        18 => 'EIGHTEEN', 19 => 'NINETEEN'
    ];

    $tens = [
        2 => 'TWENTY', 3 => 'THIRTY', 4 => 'FORTY', 5 => 'FIFTY',
        6 => 'SIXTY', 7 => 'SEVENTY', 8 => 'EIGHTY', 9 => 'NINETY'
    ];

    $thousands = [
        '', 'THOUSAND', 'MILLION', 'BILLION', 'TRILLION'
    ];

    if ($number == 0) {
        return 'ZERO';
    }

    // 將數字分成每三位一組
    $groups = [];
    while ($number > 0) {
        $groups[] = $number % 1000;
        $number = intval($number / 1000);
    }

    $words = [];
    foreach ($groups as $index => $group) {
        if ($group == 0) continue;

        $groupWords = '';

        // 處理百位
        $hundreds = intval($group / 100);
        if ($hundreds > 0) {
            $groupWords .= $ones[$hundreds] . ' HUNDRED';
        }

        // 處理十位和個位
        $remainder = $group % 100;
        if ($remainder > 0) {
            if ($groupWords != '') {
                $groupWords .= ' AND ';
            }

            if ($remainder < 20) {
                $groupWords .= $ones[$remainder];
            } else {
                $tensDigit = intval($remainder / 10);
                $onesDigit = $remainder % 10;
                $groupWords .= $tens[$tensDigit];
                if ($onesDigit > 0) {
                    $groupWords .= '-' . $ones[$onesDigit];
                }
            }
        }

        // 加上千位單位
        if ($thousands[$index] != '') {
            $groupWords .= ' ' . $thousands[$index];
        }

        $words[] = $groupWords;
    }

    // 反轉並組合
    $words = array_reverse($words);
    return implode(' ', $words);
}
