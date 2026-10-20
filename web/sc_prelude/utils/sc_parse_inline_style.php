<?php

function sc_parse_inline_style($input)
{
	$styles = array();

    $input = str_replace(["\r\n", "\r", "\n"], ' ', $input);
    $input = trim($input);

	$declarations = explode(';', $input);

    foreach ($declarations as $declaration) {
		$parts = explode(':', $declaration, 2);

        if (count($parts) === 2) {
            $property = trim($parts[0]);
            $value = trim($parts[1]);

            if ($property !== '' && $value !== '') {
                $styles[$property] = $value;
            }
        }
    }

    return $styles;

}
