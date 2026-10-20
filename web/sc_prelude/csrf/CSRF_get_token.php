<?php


function CSRF_get_token()
{
	if(!@isset($_SESSION['csrf_token']))
		$_SESSION['csrf_token'] = md5(uniqid(rand(), true));
	
	return (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : null);
}
