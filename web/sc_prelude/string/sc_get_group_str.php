<?PHP








function sc_get_group_str($str,$separator=null,$autoSort=false)
{
	if(is_array($str))
	{
		$str_array = $str;
	}
	else 
	{
		$str_array = @explode(",",$str); 
	}
	
	$str_array = @array_filter($str_array);
	$str_array = @array_unique($str_array);
	$str_array = array_filter($str_array,function($item)	{return ($item."") !== "";});
	
	if(@$str_array && $autoSort)
		sort($str_array);
	
	$separator = @empty($separator) ? "," : $separator;
	
	$str_array = @array_map(function($item)	{return "".$item."";},$str_array);
	$str = implode($separator,$str_array);
		
	return $str;
}

