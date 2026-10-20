<?PHP

function sc_get_min($data=array(),$debug=false)
{
	if(!is_array($data))
		return array();

	$min = null;
	foreach($data as $k => $value)
	{
		if($value===false)
		continue;
		if($value===null)
		continue;

		if($min === null)
			$min = $value;
		else
			$min = $min < $value ? $min : $value;
	}


	return $min;


}
