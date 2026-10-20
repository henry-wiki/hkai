<?PHP


function sc_array_column($array,$column_name,$clear_empty=true,$unique=false)
{
	$values = array_map(function($element) use($column_name){return $element[$column_name];}, $array);
	
	if($clear_empty)
	{
		$values = array_filter_recursive($values,function($val)	{return trim($val) !== "";});
	}
	
	if($unique)
	{
		// ! unique just for 1-D array, 2-D array cannot unique
		// ! unique 過程會將 value, 轉為 string, 若value 是array, 會被錯誤地清除掉
		$values = array_unique($values);
	}
	
	
	
	$values = array_values($values);
	

	return $values;

}
