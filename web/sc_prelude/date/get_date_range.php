<?PHP


function get_date_range($start_date,$end_date,$separator=null,$format=null)
{
	if(!$separator)
		$separator = " - ";
	
	if($format)
	{
		$start_date_str = date($format,strtotime($start_date));
	}
	else 
	{
		$start_date_str = $start_date;
	}
	
	if($format)
	{
		$end_date_str = date($format,strtotime($end_date));
	}
	else 
	{
		$end_date_str = $end_date;
	}
	
	if($start_date_str === $end_date_str)
	{
		$date_range = $start_date_str;
	}
	else 
	{
		$date_range = $start_date_str . "".$separator."" . $end_date_str;
	}
	
	return $date_range;
}
