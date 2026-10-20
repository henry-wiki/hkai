<?PHP

function fix_time($time,$default_time="00:00")
{
	$time = trim($time);
	$time = @substr($time,0,5);
	if(!@preg_match("/^[0-9]{2}\:[0-9]{2}$/",$time))
	{
		return $default_time;
	}
	return $time;
}
