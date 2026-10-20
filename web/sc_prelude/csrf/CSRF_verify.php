<?php


function CSRF_verify($token,$clear=false)	
{
		
		
		
	$result = $token === CSRF_get_token();
	
	if($clear === true)
		CSRF_clear();
	
	return $result;
	
}
