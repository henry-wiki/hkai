<?php

function sc_array_to_attrs($array1,$array2=array())
{
	$array1 = !@is_array($array1) ? array() : $array1;
	$array2 = !@is_array($array2) ? array() : $array2;

	$attrs = array_replace($array1,$array2);


	$parts = array();
    foreach ($attrs as $key => $value)
	{
        // Skip false boolean values
        if ($value === false) {
            continue;
        }

        // Handle true boolean values (attribute without value)
        if ($value === true) {
            $parts[] = htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
            continue;
        }

		// Handle arrays/objects - convert to JSON
        if (is_array($value) || is_object($value)) {
            $value = htmlspecialchars(json_encode($value), ENT_QUOTES, 'UTF-8');
        } else {
            $value = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
        }

        $key 		= htmlspecialchars($key, ENT_QUOTES, 'UTF-8');
        $parts[] 	= sprintf('%s="%s"', $key, $value);

    }

	return @implode(" ",$parts);
}
