<?php

function IsStringEmpty($str)
{
	if($str === null)
		return true;

	if(!is_string($str))
		return false;
	else
		return trim($str) === "";

}
