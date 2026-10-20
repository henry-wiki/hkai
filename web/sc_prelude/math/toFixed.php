<?PHP



function toFixed($n,$precision=2,$default="",$trimZero=false)
{
	// == return string, please convert to float before calculate
	$n = preg_replace("/[\s,]+/","",$n);
	$n = @number_format($n,$precision,'.','');
	$n_float = @floatval($n);
	
	if(@empty($n_float))
		return $default;
	else 
	{
		if($trimZero)
		{
			$n = rtrim($n,'0');
			$n = rtrim($n,'.');
		}
		
	}
	
	
	return $n;
}