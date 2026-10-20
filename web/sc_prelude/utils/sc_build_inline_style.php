<?php

function sc_build_inline_style($styles)
{
	$rules = array();
	foreach ($styles as $key => $value) {
		$rules[] = trim($key) . ': ' . trim($value) . ';';
	}

	// 用一個空格連接，自然就變成乾淨的單行了
	return implode(' ', $rules);
}
