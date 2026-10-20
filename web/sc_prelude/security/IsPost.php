<?PHP


function IsPost()	{return @strtolower($_SERVER['REQUEST_METHOD']) === 'post';}