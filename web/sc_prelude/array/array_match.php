<?PHP



function array_match($data,$check_column,$match_data=array(),&$ref=array())	
{
	if(!@is_array($data))
		return null;
	if(!@is_array($match_data))
		return null;
	
	$ref = array();
	$ref["matched"] = array();
	$ref["not_matched"] = array();
	
	
	foreach($data as $row)
	{
		$match_value = (isset($row[$check_column]) ? $row[$check_column] : null);
		
		$is_matched = false;
		foreach($match_data as $value)
		{
			if($is_matched)
				continue;
			
			
			if($value == $match_value && !$is_matched)
			{
				$is_matched = true;
			}
		}
		
		if($is_matched)
		{
			$ref["matched"][] = $row;
		}
		else 
		{
			$ref["not_matched"][] = $row;
		}
		
	}
	
	$all_matched = count($ref["matched"]) > 0 && count($ref["not_matched"]) === 0;
	
	return $all_matched;

}

