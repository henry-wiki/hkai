<?PHP



function array_has_duplicates($array,$column_name,&$duplicates=array(),$ignore_emptys=true) 
{
    
	$values = array_column($array,$column_name);
	
	if($ignore_emptys)
	{
		$values = array_filter($values,function(&$value)	{return trim($value) !== "";});
	}
	
	
	$counts 	= array_count_values($values);
	
	//$duplicates = array_filter($counts, function($count) {return $count > 1;});
	$dup_vals = array();
	foreach($counts as $dup_val => $dup_cnt)
	{
		if($dup_cnt < 2)
			continue;
		
		$dup_vals[] = $dup_val;
		
	}
	
	$has_duplicates = count($dup_vals) > 0;
	
	$duplicates = array_intersect($values,$dup_vals);
	
		
	return $has_duplicates;
} 


