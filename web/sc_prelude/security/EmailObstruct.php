<?php



function EmailObstruct($email,$char=null)
{
	if($char === null)
		$char = "x";
	
	
	$email = explode("@",$email);
	
	$email_0 = &$email[0];
	$email_1 = &$email[1];
	
	if(strlen($email_0)>1)
	{
		$replace_len = ceil(strlen($email_0)/2);
		
		$replace_str = str_repeat($char,$replace_len);
		
		$email_0 = substr($email_0,0,strlen($email_0) - $replace_len).$replace_str;
	}
	else {
		$replace_str = $char;
		
		$email_0 = $replace_str;
	}
	
	$email_1 = explode(".",$email_1);
	if(strlen($email_1[0])>1)
	{
		$replace_len = ceil(strlen($email_1[0])/2);
		
		$replace_str = str_repeat($char,$replace_len);
		
		$email_1[0] = substr($email_1[0],0,strlen($email_1[0]) - $replace_len).$replace_str;
	}
	else {
		$replace_str = $char;
		
		$email_1[0] = $replace_str;
	}
	$email_1 = implode(".",$email_1);
	
	return @implode("@",$email);
	
}
