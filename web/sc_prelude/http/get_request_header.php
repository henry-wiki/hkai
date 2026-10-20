<?php

function get_request_header($name)
{
	$name = strtolower($name);
	
	$headers = getallheaders();
    foreach ($headers as $header_name => $header_value) {
        $header_name = strtolower($header_name);
        if ($header_name === $name) 
		{
            return $header_value;
        }
    }
	
}


