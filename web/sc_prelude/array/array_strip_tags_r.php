<?PHP





function array_strip_tags_r($data)
{
	if (gettype($data) == 'array')
		return array_map(__NAMESPACE__ . "\array_strip_tags_r", $data);
	else
		return strip_tags($data);

}
