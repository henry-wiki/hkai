<?PHP



function sc_float_val($value,$scale=null)
{
	$value = trim($value);
	
	$value = str_replace(",","",$value);
	
	if($scale === null)
		$value = floatval($value);
	else 
		$value = round($value,$scale);
	
	return $value;
	
}