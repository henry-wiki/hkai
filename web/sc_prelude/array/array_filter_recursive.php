<?PHP

function array_filter_recursive($input, $callback = null) 
{ 
	foreach ($input as &$value) 
	{ 
		if (is_array($value)) 
		{ 
			$value = array_filter_recursive($value, $callback); 
		} 
	} 

	return array_filter($input, $callback); 
} 


