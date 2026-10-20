<?PHP

function array_trim_r($data)
{
	if (gettype($data) == 'array')
		return array_map(__NAMESPACE__ . "\array_trim_r", $data);
	else
		return trim($data);

}
