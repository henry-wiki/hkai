<?php

function sc_clean_number($number)
{
    if ($number === null || $number === '') {
        return '0';
    }

    $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $number);

    // 處理異常情況
    if ($cleaned === '' || $cleaned === '-') {
        return '0';
    }

    return $cleaned;
}
