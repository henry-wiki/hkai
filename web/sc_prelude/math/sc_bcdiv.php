<?php

function sc_bcdiv($left, $right, $scale = null)
{
	$scale 		= !ctype_digit((string)$scale) ? sc_get_max_scale($left,$right) : $scale;
	$test_scale = $scale + 1;

    $cleanRight = sc_clean_number($right);

    if (bccomp($cleanRight, '0', $scale) === 0) {
		return null;
    }

    $result = bcdiv(sc_clean_number($left), $cleanRight, $test_scale);

	$result = sc_bcround($result,$scale);

	return $result;
}
