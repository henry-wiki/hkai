<?php

/**
 * BC Math 四捨五入
 *
 * @param string $number 數字
 * @param int $scale 小數位數
 * @return string
 */
function sc_bcround($number, $scale = 9)
{
    // 判斷正負
    if (bccomp($number, '0', 20) >= 0) {
        // 正數：加 0.5 在目標位
        $add = '0.' . str_repeat('0', $scale) . '5';
        return bcadd($number, $add, $scale);
    } else {
        // 負數：減 0.5 在目標位
        $add = '0.' . str_repeat('0', $scale) . '5';
        return bcsub($number, $add, $scale);
    }
}
