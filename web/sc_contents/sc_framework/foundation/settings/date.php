<?PHP

// == settings 預設值來源：date（日期格式）——載入時註冊預設值 ==


function initial_settings_date()
{
	$info = array();
	$info["date.format"] = "d/m/Y";
	
	\sc\framework\foundation\settings\sc_settings_Class::getInstance()->set_multiple($info);
	\sc\framework\foundation\settings\sc_settings_Class::getInstance()->set_public_multiple($info);


}
initial_settings_date();
