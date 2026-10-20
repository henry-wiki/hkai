<?PHP

function sc_ini_to_array($data)
{
	$data = trim($data);
	$data = $data."\r\n";
	$parsed_data = "";

	$index = 0;
	$linestr = strtok($data, "\n");



	$strlen = strlen($data);


	$break = 5000;
	$findpos = 0;

	$configs = array();

	$variable_name 	= "";
	$declare_value 	= "";
	$bracket_value 	= "";

	$action 	= null; 		// find declaration name
	$subaction 	= null; 		//

	$prev_char = null;


	$trees = array();
	$save_trees = false;


	$array_ref 		= array();
	$var_ref 		= null;
	$levels_vars 	= array();
	$bracket_vars 	= array();

	$section_names 	= array();

	$special_chars 	= array();

	$section_name 	= null;
	$line_text 		= null;


	// == jump over the special character
	$jump = false;


	$value_quote 							= null;
	$find_declaration_value_quote_opened 	= 0;
	$find_declaration_value_quote_closed 	= 0;

	$find_declaration_name_started = false;


	$comments = array();

	$starttime = microtime(true);

	$index = 0;

	//while(1)
	for($findpos = 0;$findpos<$strlen;$findpos++)
	{
		//$char = substr($data,$findpos,1);
		$char = $data[$findpos];
		//$data = mb_substr($data,1,null,"UTF-8");
		//$nextchar = mb_substr($data,$findpos+1,1,"UTF-8");

		if($findpos > $strlen)
			break;

		if(--$break < 0)
			break;

		// == default to capture all
		$action = !@$action ? "find_declaration_name" : $action;


		//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , char = ".trim($char)." ; \$findpos = ".$findpos."";

		$is_eof 					= $findpos === $strlen;

		//++$findpos;


		//if(--$break < 0)
		//	break;

		$line_text .= $char;

		//if(trim($line_text) === ";break")
		//	break;

		//if($is_new_line_char)
		//	$line_text = "";


		$stime = microtime(true);

		$is_new_line_char 			= $char === "\r" || $char === "\n";
		$is_comment_char 			= $char === ";" || $char === "#";

		if(!$jump)
		{
			// == turn on off comment
			if($is_comment_char)
			{
				// == check if turn on comment
				if($action == "find_declaration_name")
				{
					if(!$find_declaration_name_started)
					{
						$comment_on = true;

						$nextpos = strpos($data,"\r\n",$findpos);
						//$nextchar = substr($data,$nextpos-1,1);

						$findpos 	= $nextpos;

						//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , char = ".trim($char)." ; find the char = \$findpos = ".$findpos."";

						continue;
					}
				}
			}
			else if($is_new_line_char)
			{
				// == check if turn off comment
				if($action === "find_declaration_name")
				{
					if($comment_on)
					{
						$comment_on = false;
						////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), turn off comment";
						continue;
					}
				}

			}

		}
		if($comment_on)
		{
			////$trees[] 	= "action = ".$action." (line=". __LINE__ .") ,  comment is on, will continue";

			continue;
		}


		$is_empty_char 							= ctype_space($char);
		// == default
		$change_bracket_value_to_declare_value 	= false;
		$change_variable_name_to_declare_value 	= false;
		$change_section_names_to_bracket_vars 	= false;
		$force_set_empty_value_to_section_names = false;

		$is_backslash 				= $char === "\\";
		$is_quote_char 				= $char === "\"" || $char === "'";
		$is_open_bracket_char 		= $char === "[";
		$is_close_bracket_char 		= $char === "]";
		$is_equal_char 				= $char === "=";






		if(!$jump)
		{
			if($is_quote_char)
			{
				////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), found single/double quote";

				if($action == "find_declaration_value")
				{

					if(!$find_declaration_value_started && !$find_declaration_value_quote_opened)
					{
						$find_declaration_value_started = true;
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , started capture value";

						$find_declaration_value_quote_opened = 1;
						//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), open quote";

						$value_quote = $char;
						////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), markdown quote = \"".$char."\"";

						$declare_value = "";
						////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), clear declare value";
					}
					else
					{
						if($char === $value_quote)
						{
							$find_declaration_value_quote_closed = 1;
						}
					}
				}
				else if($action === "find_declaration_name")
				{
					if(!$find_declaration_name_started)
					{
						$find_declaration_name_started = true;

						$variable_name = "";
						////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), clear declare";
					}

					else
					{
					}
				}
				else if($action === "find_bracket_value")
				{

				}
			}
			else if($is_open_bracket_char)
			{
				if($action === "find_declaration_name")
				{
					$variable_name = trim($variable_name);
					$action = "find_bracket_value";

					if($variable_name === "")
					{

					}
				}
			}
			else if($is_close_bracket_char)
			{

			}
			else if($is_equal_char)
			{
				if($action === "find_declaration_name")
				{
					if(!$find_declaration_name_started)
					{
						$find_declaration_name_started = true;
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , started capture";

						$action = "assign_declaration_value";

						//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), keep capture variable_name";


					}
					else
					{
						$action = "assign_declaration_value";

						//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), reset action";

					}


				}
				else if($action === "find_bracket_value")
				{
					//$trees[] 	= "action = ".$action." (line=". __LINE__ .")";
					//$trees[] 	= "find_section_started = \"".$find_section_started."\"";
					//$trees[] 	= "find_section_bracket_opened = \"".$find_section_bracket_opened."\"";
					//$trees[] 	= "find_section_bracket_closed = \"".$find_section_bracket_closed."\"";

					if($find_section_bracket_opened && @$find_section_bracket_closed)
					{

					}
					else if($find_section_bracket_opened && !@$find_section_bracket_closed)
					{

					}
					else if(!$find_section_bracket_opened && @$find_section_bracket_closed)
					{
						$find_section_started = false;
						$find_section_bracket_opened = 0;
						$find_section_bracket_closed = 0;
						// == force turn off bracket
						$action = "assign_declaration_value";
						//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), reset action ( force turn off )";

					}
					else
					{
						$action = "assign_declaration_value";
						//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), reset action";
					}

				}
				else if($action === "find_declaration_value")
				{

				}
			}
			else if($is_new_line_char)
			{
				if($action === "find_declaration_name")
				{

					if(trim($variable_name))
					{
						// == find_declaration_name, 未到 assign_declaration_value, 那麼這個應該是 上層 variable 的 value

						$change_variable_name_to_declare_value = true;
					}
					else
					{
						// will continue capture variable name

					}


				}
				else if($action == "find_bracket_value")
				{
					// == prevent multiple line bracket key
					$action = "find_bracket_value";

					if($find_section_bracket_opened && @$find_section_bracket_closed)
					{
						if($find_section_started)
						{
						}
						else
						{
							$change_bracket_value_to_declare_value = true;
							//$trees[] 	= "action = ".$action." (line=". __LINE__ .") ,active bracket value -> declare value";
						}
					}
					else if($find_section_bracket_opened && !@$find_section_bracket_closed)
					{
						$find_section_started = false;
						////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), open bracket, no close bracket, need to inactive section";

						$find_section_bracket_opened = 0;
						////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), open bracket, no close bracket, need to inactive open bracket";

						$change_bracket_value_to_declare_value = true;
						////$trees[] 	= "action = ".$action." (line=". __LINE__ .") ,active bracket value -> declare value";

					}


				}
				else if($action === "find_declaration_value")
				{
					////$trees[] 	= "action = ".$action." (line=". __LINE__ ."), new line";
				}
			}
			else if($is_comment_char)
			{
				if($action == "find_declaration_name")
				{
					if(!$find_declaration_name_started)
					{
						$comment_on = true;

						$nextpos = strpos($data,"\r\n",$findpos);
						//$nextchar = substr($data,$nextpos-1,1);

						$findpos = $nextpos;

						////$trees[] 	= "action = ".$action." (line=". __LINE__ .") , active comment, findpos = \"".$findpos."\", nextpos = \"".$nextpos."\", nextchar = \"".$nextchar."\"";
						////$trees[] 	= "action = ".$action." (line=". __LINE__ .") , change findpos to \"".$findpos."\"";
					}
				}
			}
			else
			{
				// == any char except above
				if($action == "find_declaration_name")
				{
					if($is_empty_char)	{
						// ignore
					}
					else
					{
						if(!$find_declaration_name_started)
						{
							$find_declaration_name_started = true;

							//// $trees[] = "action = ".$action." (line=". __LINE__ .") , started capture value";
						}

						//if($find_declaration_name_quote_closed)
						//{
						//	$find_declaration_name_quote_closed = 0;

							//// $trees[] = "action = ".$action." (line=". __LINE__ .") , turn off \"find_declaration_name_quote_closed\"";
						//}


					}
				}


			}

		}


		if($change_variable_name_to_declare_value)
		{
			$value_quote 	= $name_quote;
			$declare_value 	= $variable_name;

			//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , going to reset action";
			//$trees[] 	= "value_quote = \"".$value_quote."\"";
			//$trees[] 	= "declare_value = \"".$declare_value."\"";

			$name_quote 	= "";
			$variable_name 	= "";

			$action = "find_declaration_value";
			//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , reset action";
		}
		else if($change_bracket_value_to_declare_value)
		{
			$declare_value 	= $variable_name.$bracket_value;

			$variable_name 	= "";
			$bracket_value 	= "";

			$action = "find_declaration_value";
			////$trees[] 	= "action = ".$action." (line=". __LINE__ .") , reset action";
		}


		if($action == "find_declaration_value")
		{
			$set_declare_value 	= false;
			$skip_char 			= false;
			$allow_jump			= false;
			$undo_skipped_rchar	= "";
			$more_chars	= "";


				if($is_backslash && !$jump)
				{
					$jump = true;

					//// $trees[] = "action = ".$action." (line=". __LINE__ .") , found special char ; turn on jump";
				}
				else if($is_new_line_char && !$jump)
				{
					if(!$find_declaration_value_quote_opened && !$find_declaration_value_quote_closed)
					{
						// $trees[] = "action = ".$action." (line=". __LINE__ ."), no open quote and no close quote";

						$skip_char 			= true;
						$set_declare_value 	= true;


					}
					else if($find_declaration_value_quote_opened === 1 && $find_declaration_value_quote_closed === 1)
					{
						// $trees[] = "action = ".$action." (line=". __LINE__ ."), have open quote and close quote";

						$skip_char 			= true;
						$set_declare_value 	= true;
					}
					else
					{
						$skip_char 			= false;
					}
				}
				else
				{
					if($is_empty_char)
					{
						// == ignore
					}
					else if($is_quote_char && !$jump)
					{
						// == ignore

					}
					else
					{
						if(!$find_declaration_value_started)
						{
							$find_declaration_value_started = true;

							// $trees[] = "action = ".$action." (line=". __LINE__ .") , started capture value";
						}

						if($find_declaration_value_quote_closed)
						{
							$find_declaration_value_quote_closed = 0;

							// $trees[] = "action = ".$action." (line=". __LINE__ .") , turn off \"find_declaration_value_quote_closed\"";
						}
					}

					if($find_declaration_value_started)
					{
						//if($value_quote)
						if($find_declaration_value_quote_opened && !$find_declaration_value_quote_closed)
						{
							if(!$find_declaration_value_quote_closed)
							{
								$target_findpos = $findpos + 1;
								$testpos = array();
								$testpos["backslash"]	= strpos($data,"\\",$target_findpos);

								$testpos["close_quote"]	= strpos($data,"".$value_quote."",$target_findpos);
								//$testpos["newline"] 	= strpos($data,"\r\n",$findpos);

								if(!$testpos["close_quote"])
								{
									$testpos["close_quote"] = $strlen;
									$set_declare_value = true;
								}



								$testpos_min 			= sc_get_min($testpos);
								// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos";
								// $trees[] = $testpos;
								// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos_min = ".$testpos_min."";
								if($testpos_min)
								{
									$more_chars 			= substr($data,$target_findpos,$testpos_min-$target_findpos);
									$findpos 				= $testpos_min-1;

									// $trees[] = "action = ".$action." (line=". __LINE__ .") , more_chars = ".$more_chars."";
									// $trees[] = "action = ".$action." (line=". __LINE__ ."), findpos char = \"".$data[$findpos]."\"";
								}
							}
						}
						else
						{
								$target_findpos = $findpos + 1;
								$testpos = array();
								$testpos["backslash"]	= strpos($data,"\\",$target_findpos);
								$testpos["newline"] 	= strpos($data,"\r\n",$target_findpos);



								//if(count($testpos) === 1)
								//	$testpos_min = array_shift($testpos);
								//else
								//	$testpos_min = call_user_func_array("min",$testpos);
								$testpos_min 			= sc_get_min($testpos);
								// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos";
								// $trees[] = $testpos;
								// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos_min = ".$testpos_min."";
								if($testpos_min)
								{
									$more_chars 			= substr($data,$target_findpos,$testpos_min-$target_findpos);
									$findpos 				= $testpos_min-1;

									// $trees[] = "action = ".$action." (line=". __LINE__ .") , more_chars = ".$more_chars."";
									// $trees[] = "action = ".$action." (line=". __LINE__ ."), findpos char = \"".$data[$findpos]."\"";


								}
						}
					}


					if($jump)
					{
						//// $trees[] = "action = ".$action." (line=". __LINE__ .") , char = \"".$char."\" ; turn off jump";
						$jump = false;

						if($is_quote_char)
						{}
						else if($is_backslash)
						{}
						else
						{
							$undo_skipped_rchar = "\\";
						}
					}
				}

				if($jump)
				{
					// == jump for current char
					$skip_char 			= true;
					$set_declare_value 	= false;
				}

				if(!$skip_char)
				{
					// == keep capture value
					$declare_value .= $undo_skipped_rchar.$char;

					// $trees[] = "action = ".$action." (line=". __LINE__ .") , keep capture, value = \"".$declare_value."\"";
					// $trees[] = "find_declaration_value_quote_opened = ".$find_declaration_value_quote_opened."";
					// $trees[] = "find_declaration_value_quote_closed = ".$find_declaration_value_quote_closed."";

				}

				if($more_chars !== "")
				$declare_value .= $more_chars;


				// == end of file
				if($is_eof)
				{
					$set_declare_value = true;
				}

				if($set_declare_value)
				{


					$declare_value = trim($declare_value);

					if($declare_value === "")
					{

						$alias_levels_vars = $levels_vars;
						$set_declare_value 	= true;
					}
					else if($change_variable_name_to_declare_value || $change_bracket_value_to_declare_value)
					{
						// == create alias_levels_vars
						$alias_levels_vars = $levels_vars;
						$alias_levels_vars[] = "";

						$set_declare_value 	= true;
					}
					else
					{
						$alias_levels_vars = $levels_vars;
						$set_declare_value 	= true;
					}

					$alias_section_names = array_reverse($section_names);


				}

				if($set_declare_value)
				{


					// $trees[] = "action = ".$action." (line=". __LINE__ .") , will set the captured";
					// $trees[] = "value_quote = \"".$value_quote."\"";
					// $trees[] = "alias_section_names";
					// $trees[] = $alias_section_names;
					// $trees[] = "alias_levels_vars";
					// $trees[] = $alias_levels_vars;
					// $trees[] = "declare_value";
					// $trees[] = $declare_value;
					// $trees[] = "configs_before";
					// $trees[] = $configs;


					//$declare_value = trim($declare_value);
					if($value_quote)
					{
						if($value_quote === $declare_value[0])
						{
							$declare_value = substr($declare_value,1);
						}
						if($value_quote === substr($declare_value,-1))
						{
							$declare_value = substr($declare_value,0,-1);
						}
						$value_quote = "";
					}
					else
					{
						if($declare_value === "")
						{
							$declare_value = "[#EMPTY]";
							$declare_value = null;
						}
					}


					// == explode
					$tmp_var = &$configs;

					//if($find_declaration_value_array_on)
					//$alias_levels_vars = $find_declaration_value_levels_vars;
					//else
					//$alias_levels_vars = $levels_vars;
					//if($section_name)
					//array_unshift($alias_levels_vars,$section_name);

					foreach($alias_section_names as $section_name)
					{
						array_unshift($alias_levels_vars,$section_name);
					}

					$alias_level_var_last_key = key(array_slice($alias_levels_vars, -1, 1, true));
					foreach($alias_levels_vars as $k => $level_var)
					{
						if($k === $alias_level_var_last_key)
						{
							if($level_var == "")
								$tmp_var[] = $declare_value;
							else
								$tmp_var[$level_var] = $declare_value;

						}
						else
						{
							if($level_var == "")
							{
								$tmp_var[] = array();
							}
							else if(!@is_array($tmp_var[$level_var]))
							{
								if(@isset($tmp_var[$level_var]))
								{
									if($tmp_var[$level_var] === null)
										$tmp_var[$level_var] = array();
									else
										$tmp_var[$level_var] = array($tmp_var[$level_var]);
								}
								else
									$tmp_var[$level_var] = array();


							}

							// end($tmp_var);
							// $last_key = key($tmp_var);
							// Although end() seems to be the easiest, it's not the fastest. The faster, and much stronger alternative is array_slice()
							$last_key = key(array_slice($tmp_var, -1, 1, true));

							$tmp_var = &$tmp_var[$last_key];
						}

					}
					unset($tmp_var);

					/*
					while(@is_array($alias_levels_vars) && @count($alias_levels_vars))
					{
						$level_var = array_shift($alias_levels_vars);;
						if(@count($alias_levels_vars))
						{
							if($level_var == "")
							{
								$tmp_var[] = array();
							}
							else if(!@is_array($tmp_var[$level_var]))
							{
								if(@isset($tmp_var[$level_var]))
								{
									if($tmp_var[$level_var] === null)
										$tmp_var[$level_var] = array();
									else
										$tmp_var[$level_var] = array($tmp_var[$level_var]);
								}
								else
									$tmp_var[$level_var] = array();


							}

							// end($tmp_var);
							// $last_key = key($tmp_var);
							// Although end() seems to be the easiest, it's not the fastest. The faster, and much stronger alternative is array_slice()
							$last_key = key(array_slice($tmp_var, -1, 1, true));

							$tmp_var = &$tmp_var[$last_key];
						}
						else
						{
							// == last key
							if($level_var == "")
								$tmp_var[] = $declare_value;
							else
							{
								$tmp_var[$level_var] = $declare_value;
							}
							break;
						}
					}
					unset($tmp_var);
					*/

					// == re-defeine action
					$action = "find_declaration_name";

					// == clear $levels_vars
					//$levels_vars = array();

					$find_declaration_name_started 			= false;
					//$find_declaration_name_quote_opened 	= 0;
					//$find_declaration_name_quote_closed 	= 0;

					$find_declaration_value_started 		= false;
					$find_declaration_value_quote_opened 	= 0;
					$find_declaration_value_quote_closed 	= 0;

					$set_declare_value 						= false;

					$declare_value 							= "";


					// $trees[] = "action = ".$action." (line=". __LINE__ ."), set declare value";
					// $trees[] = "configs after";
					// $trees[] = $configs;
				}



		}
		else if($action == "find_bracket_value")
		{
			$skip_char = false;
			$set_section_value = false;
			$set_non_section_value = false;
			$undo_skipped_rchar	= "";
			$more_chars = "";

			if($char == "\\" && !$jump)
			{
				$jump = true;

				// $trees[] = "action = ".$action." (line=". __LINE__ .") , found special char ; turn on jump";
			}
			else if($is_open_bracket_char && !$jump)
			{
				$skip_char 	= false;


				// *** note : $varaible_name may be === "0"
				if($variable_name !== "")
				{
					//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), have varaible";
					if(!$find_section_bracket_opened)
					{
						$find_section_bracket_opened = 1;
						//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), open bracket";

						$target_findpos = $findpos + 1;
						$testpos = array();
						$testpos["backslash"]		= strpos($data,"\\",$target_findpos);
						$testpos["close_bracket"]	= strpos($data,"]",$target_findpos);
						$testpos["newline"] 		= strpos($data,"\r\n",$target_findpos);


						//if(count($testpos) === 1)
						//	$testpos_min = array_shift($testpos);
						//else
						//	$testpos_min = call_user_func_array("min",$testpos);
						$testpos_min 			= sc_get_min($testpos);
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos";
						// $trees[] = $testpos;
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos_min = ".$testpos_min."";
						if($testpos_min)
						{
							$more_chars 			= substr($data,$target_findpos,$testpos_min-$target_findpos);
							$findpos 				= $testpos_min-1;

							// $trees[] = "action = ".$action." (line=". __LINE__ .") , more_chars = ".$more_chars."";
							// $trees[] = "action = ".$action." (line=". __LINE__ ."), findpos char = \"".$data[$findpos]."\"";
						}

					}
					else if($find_section_bracket_closed)
					{
						// => $find_section_bracket_opened && $find_section_bracket_closed
						// == no need to check if find_section_started, because have variable_name, must not section
						$set_non_section_value = true;

					}
				}
				else
				{
					// == no variable, must be section started

					if(!$find_section_started)
					{
						$find_section_started = true;
						$find_section_bracket_opened = 1;



						// jump to find_section_bracket_closed
						$target_findpos = $findpos + 1;
						$testpos = array();
						$testpos["backslash"]		= strpos($data,"\\",$target_findpos);
						$testpos["close_bracket"]	= strpos($data,"]",$target_findpos);
						$testpos["newline"] 		= strpos($data,"\r\n",$target_findpos);


						//if(count($testpos) === 1)
						//	$testpos_min = array_shift($testpos);
						//else
						//	$testpos_min = call_user_func_array("min",$testpos);
						$testpos_min 			= sc_get_min($testpos);
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos";
						// $trees[] = $testpos;
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos_min = ".$testpos_min."";
						if($testpos_min)
						{
							$more_chars 			= substr($data,$target_findpos,$testpos_min-$target_findpos);
							$findpos 				= $testpos_min-1;

							// $trees[] = "action = ".$action." (line=". __LINE__ .") , more_chars = ".$more_chars."";
							// $trees[] = "action = ".$action." (line=". __LINE__ ."), findpos char = \"".$data[$findpos]."\"";

						}


					}
					else if($find_section_bracket_opened && $find_section_bracket_closed)
					{
						// == no need to check if find_section_started, because no variable_name, must not section
						$set_section_value = true;
					}
				}

				//// $trees[] = "action = ".$action." (line=". __LINE__ .") , found open bracket!!";
				//// $trees[] = "find_section_started : ".$find_section_started."";
				//// $trees[] = "find_section_bracket_opened : ".$find_section_bracket_opened."";
				//// $trees[] = "find_section_bracket_closed : ".$find_section_bracket_closed."";



			}
			else if($is_close_bracket_char && !$jump)
			{
				$skip_char 	= false;

				$find_section_bracket_closed = 1;
				//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), close bracket **";
				//$trees[] 	= "find_section_started = \"".$find_section_started."\"";
				//$trees[] 	= "find_section_bracket_opened = \"".$find_section_bracket_opened."\"";
				//$trees[] 	= "find_section_bracket_closed = \"".$find_section_bracket_closed."\"";


			}
			else if($is_equal_char && !$jump)
			{
				$skip_char 	= false;
				if($find_section_bracket_opened && $find_section_bracket_closed)
				{
					$skip_char = true;
					if($find_section_started)
					{
						$set_section_value = true;
						$change_section_names_to_bracket_vars = true;
					}
					else
					{
						$set_non_section_value = true;
					}
				}
			}
			else if($is_new_line_char && !$jump)
			{
				if($find_section_bracket_opened && $find_section_bracket_closed)
				{
					// $trees[] = "action = ".$action." (line=". __LINE__ ."), have open bracket and close bracket";

					$skip_char 			= true;
					if($find_section_started)
					{
						$set_section_value 			= true;
					}
					else
					{
						$set_non_section_value 			= false;
					}
				}
				else
				{
					// $trees[] = "action = ".$action." (line=". __LINE__ ."), not have open bracket and close bracket";
					$skip_char 			= false;
				}
			}
			else
			{
				if($is_empty_char)
				{
					// == ignore
				}
				else
				{
					if(!$find_bracket_value_started)
					{
						$find_bracket_value_started = true;

						// $trees[] = "action = ".$action." (line=". __LINE__ .") , started capture value";
					}

					if($find_section_bracket_closed)
					{
						$find_section_bracket_closed = 0;

						// $trees[] = "action = ".$action." (line=". __LINE__ .") , turn off close bracket";
					}
				}



				if($jump)
				{
					// $trees[] = "action = ".$action." (line=". __LINE__ .") , char = \"".$char."\" ; turn off jump";
					$jump = false;

					if($is_open_bracket_char)	{}
					else if($is_close_bracket_char)	{}
					else
					{
						$undo_skipped_rchar = "\\";
					}
				}

			}

			if($jump)
			{
				$skip_char  = true;

			}



			if($set_non_section_value)
			{
				// $trees[] = "action = ".$action." (line=". __LINE__ .") , not section";

				$bracket_value 	= trim($bracket_value);
				//$bracket_value 	= preg_replace("/^[\[]/","",$bracket_value);
				//$bracket_value 	= preg_replace("/[\]]$/","",$bracket_value);
				if("[" === $bracket_value[0])
				{
					$bracket_value = substr($bracket_value,1);
				}
				if("]" === substr($bracket_value,-1))
				{
					$bracket_value = substr($bracket_value,0,-1);
				}

				$bracket_value 	= trim($bracket_value);

				// == 若有single/double quote, 應該會於尾部有, 要trim
				if($bracket_quote)
				{
					$bracket_value 	= rtrim($bracket_value,$bracket_quote);
					$bracket_quote 	= "";
				}
				$bracket_vars[] 	= $bracket_value;


				$find_section_started = false;



				if($is_open_bracket_char)	{
					// == if the char is [, don't reset the find_section_bracket_opened
				}
				else
				{
					$find_section_bracket_opened = 0;
				}

				$find_section_bracket_closed = 0;






				// $trees[] = "action = ".$action." (line=". __LINE__ .") ,set bracket value = \"".$bracket_value."\"";
				// $trees[] = "variable_name = \"".$variable_name."\"";
				// $trees[] = "bracket_vars=";
				// $trees[] = $bracket_vars;

				$bracket_value 	= "";
				//// $trees[] = "action = ".$action." (line=". __LINE__ .") , clear bracket_value";



				if($is_equal_char)
				{
					$action 	= "assign_declaration_value";
					//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), reset action, work for next char";

				}





			}

			if($set_section_value)
			{
				// $trees[] = "action = ".$action." (line=". __LINE__ .") , is section";

				if($clear_section_names_on_next_section_name_created)
				{
					$section_names 	= array();
					$levels_vars 	= array();
					$clear_section_names_on_next_section_name_created = false;

					// $trees[] = "action = ".$action." (line=". __LINE__ .") , clear section names";

				}


				//// $trees[] = "clear_section_names_on_next_section_name_created = ".$clear_section_names_on_next_section_name_created."";

				$bracket_value = trim($bracket_value);
				//$bracket_value = preg_replace("/^[\[]/","",$bracket_value);
				//$bracket_value = preg_replace("/[\]]$/","",$bracket_value);
				if("[" === $bracket_value[0])
				{
					$bracket_value = substr($bracket_value,1);
				}
				if("]" === substr($bracket_value,-1))
				{
					$bracket_value = substr($bracket_value,0,-1);
				}
				$bracket_value = trim($bracket_value);



				$section_names[] = $bracket_value;

				//// $trees[] = "action = ".$action." (line=". __LINE__ .") ,set section";
				//// $trees[] = "section_names=";
				//// $trees[] = $section_names;

				//$find_section_started = false;
				//// $trees[] = "action = ".$action." (line=". __LINE__ .") , turn off find_section_started";

				if($is_open_bracket_char)	{
					// == if the char is [, don't reset the find_section_bracket_opened
				}
				else
				{
					$find_section_bracket_opened = 0;
				}

				$find_section_bracket_closed = 0;
				//// $trees[] = "action = ".$action." (line=". __LINE__ .") , turn off open bracket";
				//// $trees[] = "action = ".$action." (line=". __LINE__ .") , turn off close bracket";


				$bracket_value 	= "";
				//// $trees[] = "action = ".$action." (line=". __LINE__ .") , clear bracket_value";


				if($is_new_line_char)
				{
					$tmp_var = &$configs;

					//if($find_declaration_value_array_on)
					//$alias_levels_vars = $find_declaration_value_levels_vars;
					//else
					//$alias_levels_vars = $levels_vars;
					//if($section_name)
					//array_unshift($alias_levels_vars,$section_name);
					$alias_section_names = $section_names;

					foreach($alias_section_names as $section_name)
					{
							if($section_name === "")
							{
								$tmp_var[] = array();
							}
							else if(!@is_array($tmp_var[$section_name]))
							{
								$tmp_var[$section_name] = array();
							}

							$last_key = key(array_slice($tmp_var, -1, 1, true));

							$tmp_var = &$tmp_var[$last_key];
					}
					unset($tmp_var);


					// while(@is_array($alias_section_names) && @count($alias_section_names))
					// {
						// $section_name = array_shift($alias_section_names);;

							// if($section_name == "")
							// {
								// $tmp_var[] = array();
							// }
							// else if(!@is_array($tmp_var[$section_name]))
							// {
								// $tmp_var[$section_name] = array();
							// }

							// $last_key = key(array_slice($tmp_var, -1, 1, true));

							// $tmp_var = &$tmp_var[$last_key];

					// }
					//unset($tmp_var);

					$find_section_started 			= false;
					$find_section_bracket_opened 	= 0;
					$find_section_bracket_closed 	= 0;

					// telling to clear section_names if
					$clear_section_names_on_next_section_name_created = true;
					$action = "find_declaration_name";

					$levels_vars 	= array();
					$variable_name 	= "";

					// $trees[] = "action = ".$action." (line=". __LINE__ .") , reset action##";
					// $trees[] = "alias_section_names = ";
					// $trees[] = $alias_section_names;
				}





			}


			if($change_section_names_to_bracket_vars)
			{
				$bracket_vars = $section_names;
				$find_section_started 			= false;
				$find_section_bracket_opened 	= 0;
				$find_section_bracket_closed 	= 0;

				// telling to clear section_names if
				$section_names = array();


				$action = "assign_declaration_value";
				//$trees[] 	= "action = ".$action." (line=". __LINE__ ."), reset action";
				//$trees[] 	= "active change_section_names_to_bracket_vars";
				//$trees[] 	= "section_names = ";
				//$trees[] 	= $section_names;
				//$trees[] 	= "bracket_vars = ";
				//$trees[] 	= $bracket_vars;
			}





			if(!@$skip_char)
			{
				// == keep capture value

				$bracket_value .= $undo_skipped_rchar.$char;

				// $trees[] = "action = ".$action." (line=". __LINE__ ."), keep capture; bracket value = \"".$bracket_value."\"";
			}

			if($more_chars !== "")
			$bracket_value .= $more_chars;

		}
		else if($action == "find_declaration_name")
		{

			$more_chars = "";

			// == find declaration name

					$target_findpos = $findpos + 1;
					$testpos = array();
					//$testpos["backslash"]		= strpos($data,"\\",$target_findpos);
					$testpos["close_bracket"]	= strpos($data,"[",$target_findpos);
					$testpos["equal"] 			= strpos($data,"=",$target_findpos);
					$testpos["newline"] 		= strpos($data,"\r\n",$target_findpos);


						//if(count($testpos) === 1)
						//	$testpos_min = array_shift($testpos);
						//else
						//	$testpos_min = call_user_func_array("min",$testpos);
						$testpos_min 			= sc_get_min($testpos,true);
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos";
						// $trees[] = $testpos;
						// $trees[] = "action = ".$action." (line=". __LINE__ .") , testpos_min = ".$testpos_min."";
						if($testpos_min)
						{
							$more_chars 			= substr($data,$target_findpos,$testpos_min-$target_findpos);
							$findpos 				= $testpos_min-1;

							// $trees[] = "action = ".$action." (line=". __LINE__ .") , more_chars = ".$more_chars."";
							// $trees[] = "action = ".$action." (line=". __LINE__ ."), findpos char = \"".$data[$findpos]."\"";

						}


			// == keep capture value
			$variable_name .= $char;


			if($more_chars !== "")
			$variable_name .= $more_chars;

			//$trees[] = "action = ".$action." (line=". __LINE__ .") , variable_name = \"".$variable_name."\"";
			//// $trees[] = "action = ".$action." (line=". __LINE__ .") , name_quote = \"".$name_quote."\"";
		}
		else if($action == "assign_declaration_value")
		{
			$levels_vars = array();

			// == variable name
			if($name_quote)
			{
				$variable_name = trim($variable_name);
				if($name_quote === $varaible_name[0])
				{
					$variable_name = substr($variable_name,1);
				}
				if($name_quote === substr($variable_name,-1))
				{
					$variable_name = substr($variable_name,0,-1);
				}
				////$trees[] 	= "action = ".$action." (line=". __LINE__ .") ,  char = \"".$char."\", variable_name with name quote, do trim";
			}
			else
			{
				$variable_name = trim($variable_name);
			}


			$levels_vars = $bracket_vars;

			if(@$variable_name)
			array_unshift($levels_vars,$variable_name);




			//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , add variable_name to levels_vars";
			//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , add bracket_vars to levels_vars";
			//$trees[] 	= "action = ".$action." (line=". __LINE__ .") , clear variable_name, bracket_vars";


			$bracket_vars 	= array();
			$bracket_value 	= "";
			$variable_name 	= "";
			$name_quote 	= "";


			$find_declaration_name_started = false;
			//$find_declaration_name_quote_opened = 0;
			//$find_declaration_name_quote_closed = 0;

			$find_section_started = false;
			$find_section_bracket_opened = 0;
			$find_section_bracket_closed = 0;

			$action 	= "find_declaration_value";

			//$trees[] 	= "action = ".$action." (line=". __LINE__ .") ,  reset action";

		}

		if($is_new_line_char && !$jump)
		{
			$display_char = "newline";
		}
		else
		{
			$display_char = $char;
		}

		$etime = microtime(true);


		//$tdiff = bcsub(number_format($etime,6,".",""),number_format($stime,6,".",""),6);
		//$tdiff = $tdiff * 10000;
		//$tdiff = bcsub($etime,$stime);

		//if($tdiff > 0.1)
		//{
			// $trees[] = "<div style='color:#f00000;font-weight:bold;'>###".(++$index)."##tdiff=".($tdiff)."########nextfindpos=".$findpos."##########action=".$action."/[char=\"".($display_char)."\"]############################</div>";
		//}
		//else
		//{
			// $trees[] = "###".(++$index)."##tdiff=".($tdiff)."########nextfindpos=".$findpos."##########action=".$action."/[char=\"".($display_char)."\"]########################";
		//}

	}

	$endtime = microtime(true);

	$timediff = $endtime - $starttime;

	//printArray($trees);

	return $configs;


}
