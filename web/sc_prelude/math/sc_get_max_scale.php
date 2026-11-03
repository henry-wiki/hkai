<?php

/**
 * 取得多個數字的最大小數位數
 * 
 * 參數：逐一傳入數字字串（內部以 func_get_args() 收集）
 *
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
