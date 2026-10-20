<?php


function sc_array_sum($arr,$column=null,$scale=null)
{
	$scale = $scale === null ? 8 : $scale;
	
	if($column===null)
	{
		$result = '0';
		foreach($arr as $val)
		{
			$result = bcadd($result,$val,8);
		}
		
		$result = sc_float_val($result);
		
		return $result;
	}
	else 
	{
		$arr = array_column($arr,$column);
		
		$result = '0';
		foreach($arr as $val)
		{
			$result = bcadd($result,$val,8);
		}
		
		$result = sc_float_val($result);
		
		return $result;
	}
	
	
}



