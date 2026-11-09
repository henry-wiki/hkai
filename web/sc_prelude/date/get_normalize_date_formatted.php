<?php

function get_normalize_date_formatted($date, $inputFormat = 'auto')
{
	$date = get_normalize_date($date,$inputFormat,sc_get_setting("date.format", ""));

	return $date;
}
