<?PHP

function fix_date($date,$default='0000-00-00')
{
	$date = trim($date);
	$date = @substr($date,0,10);
	if(!@preg_match("/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/",$date))
	{
		return $default;
	}

	if($date == "0000-00-00")
		return $default;

	return $date;
}
