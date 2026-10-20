<?PHP

function array_map_recursive($callback,$input) 
{ 
	foreach ($input as &$value) 
	{ 
		if (is_array($value)) 
		{ 
			$value = array_map_recursive($callback,$value); 
		} 
	} 

	return array_map($callback,$input); 
} 


