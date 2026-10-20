<?php

/**
 * 取得多個數字的最大小數位數
 * 
 * @param string ...$numbers 要檢查的數字（已清理過的數值字串）
 * @return int 最大小數位數
 */
function sc_get_max_scale()
{
	$numbers = func_get_args();
	$numbers = array_filter($numbers);
	
	
    $maxScale = 0;
    foreach ($numbers as $num) {
        $dotPos = strpos($num, '.');
        if ($dotPos !== false) {
            $scale = strlen(substr($num, $dotPos + 1));
            if ($scale > $maxScale) {
                $maxScale = $scale;
            }
        }
    }
    return $maxScale;
}
