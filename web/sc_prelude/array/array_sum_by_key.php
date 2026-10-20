<?PHP




function array_sum_by_key($array,$key,$callable=null)
{
	if(is_callable($callable))
		$array 		= array_filter($array,$callable);
	
	$maps 		= array_map(function($item) use($key)	{return @floatval($item[$key]);},$array);
	$result 	= array_sum($maps);

	
	
	return $result;
}
