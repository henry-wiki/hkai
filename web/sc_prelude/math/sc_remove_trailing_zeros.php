<?php

function sc_remove_trailing_zeros($num)
{
	$str = (string)$num;
	
	$str = trim($str);
	
	# 整數, 直接去掉前面的 '0'
	# 00012300 : 12300
	$dotPos = strpos($str, '.');
	if ($dotPos === false) 
	{
		$result = ltrim($str,'0');
		
		return $result;
	}
	
	// 浮點數
	$intPart = substr($str, 0, $dotPos);
    $decPart = substr($str, $dotPos + 1);
	
	$intPart = ltrim($intPart, '0');
	$decPart = rtrim($decPart, '0');
	
	
	$intPart = $intPart === '' ? '0' : $intPart;
	
	$result = $intPart . ($decPart ? '.' . $decPart : '');
	
   
    return $result;
}



// $test_nums = array();
// $test_nums[] = "123456789.123000";
// $test_nums[] = "123456789.000000";
// $test_nums[] = "123000000.123000";
// $test_nums[] = "123000000.000000";
// $test_nums[] = "001234567.000000";
// $test_nums[] = "001234567.001200";
// $test_nums[] = "0.000123000";
// $test_nums[] = "000.000123000";

