<?php


function CSRF_clear()	{
	
	if(@isset($_SESSION['csrf_token']))
		unset($_SESSION['csrf_token']);

}
