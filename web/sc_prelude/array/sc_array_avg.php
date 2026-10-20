<?php


function sc_array_avg($arr,$decimals=0)
{
	$sum = array_sum($arr);
	
	if(@$sum === null)
		return null;
	
	$avg = $sum / count($arr);
	
	//$avg = round($avg,2);
	$avg = toFixed($avg,2);
	
	return $avg;
	
}



