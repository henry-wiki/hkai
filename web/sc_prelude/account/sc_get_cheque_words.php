<?php

function sc_get_cheque_words($amount)
{
	$amount = str_replace(",","",$amount);

    // 確保金額為正數且保留2位小數
    $amount = abs(round($amount, 2));

    // 分離整數和小數部分
    $parts = explode('.', number_format($amount, 2, '.', ''));
    $dollars = (int)$parts[0];
    $cents = (int)$parts[1];

    // 處理整數部分
    $dollarWords = '';
    if ($dollars == 0) {
        $dollarWords = 'ZERO';
    } else {
        $dollarWords = convertNumberToWords($dollars);
    }

    // 處理小數部分（個位 cents 不需 ZERO 前綴，如 "FOUR CENTS" 而非 "ZERO FOUR CENTS"）
    $centWords = '';
    if ($cents > 0) {
        $centWords = convertNumberToWords($cents);
        $centWords = ' AND ' . $centWords . ' CENTS';
    }

    // 組合結果
    $result = $dollarWords . ' DOLLARS' . $centWords . ' ONLY';

    return $result;
}


// 測試範例
//echo sc_get_cheque_words(1234.56) . "\n";
// 輸出: ONE THOUSAND TWO HUNDRED AND THIRTY-FOUR DOLLARS AND FIFTY-SIX CENTS ONLY

//echo sc_get_cheque_words(100.00) . "\n";
// 輸出: ONE HUNDRED DOLLARS ONLY

//echo sc_get_cheque_words(0.05) . "\n";
// 輸出: ZERO DOLLARS AND ZERO FIVE CENTS ONLY

//echo sc_get_cheque_words(999999.99) . "\n";
// 輸出: NINE HUNDRED AND NINETY-NINE THOUSAND NINE HUNDRED AND NINETY-NINE DOLLARS AND NINETY-NINE CENTS ONLY

//echo sc_get_cheque_words(50.10) . "\n";
// 輸出: FIFTY DOLLARS AND TEN CENTS ONLY
