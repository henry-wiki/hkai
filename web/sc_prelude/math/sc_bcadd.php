<?php

function sc_bcadd($left, $right, $scale = null)
{
	$scale 		= !ctype_digit((string)$scale) ? sc_get_max_scale($left,$right) : $scale;
	$test_scale = $scale + 1;

    $result = bcadd(sc_clean_number($left), sc_clean_number($right), $test_scale);

	$result = sc_bcround($result,$scale);

	return $result;
}
