<?PHP

/**

[sort] => Array
	(
		[0] => 
		[1] => 
		[2] => 
	)

[name] => Array
	(
		[0] => apple
		[1] => mary
		[2] => sam
	)

==== convert to ==================================
Array
	(
		[0] => array("sort"=>"",name=>"apple")
		[1] => array("sort"=>"",name=>"mary")
		[2] => array("sort"=>"",name=>"sam") 
	)


**/

function swap_array_keys($array=array())
{
	if(!@$array)
		return null;
	
	$new_array = array();
	$first_item 	= current($array);
	$first_columns 	= array_keys($array);
	
	foreach($array as $column_name => $items)
	{
		foreach($items as $idx => $value)
		{
			if(!@is_array($new_array[$idx]))	$new_array[$idx] = array();
			$new_array[$idx][$column_name] = $value;
		}
	}
	
	return $new_array;
}
