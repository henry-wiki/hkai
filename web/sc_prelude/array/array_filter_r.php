<?PHP

function array_filter_r($input, $callback = null) 
{ 
	foreach ($input as &$value) 
	{ 
		if (is_array($value)) 
		{ 
			$value = array_filter_r($value, $callback); 
		} 
	} 

	if(is_callable($callback))
	{
		return array_filter($input, $callback); 
	}
	else 
	{
		return array_filter($input); 
	}
} 
