<?PHP

// == settings 預設值來源：scale（小數位）——載入時註冊預設值 ==


function initial_settings_scale()
{
	$info = array();
	$info["scale.default"] = 2;
	$info["scale.default_format"] = "0,".number_format(0,$info["scale.default"],'.','');
	
	$info["scale.unit_price"] = 2;
	$info["scale.unit_price_format"] = "0,".number_format(0,$info["scale.unit_price"],'.','');
	
	$info["scale.base_unit_price"] = 9;
	$info["scale.base_unit_price_format"] = "0,".number_format(0,$info["scale.base_unit_price"],'.','');
		
	$info["scale.quantity"] = 0;
	$info["scale.quantity_format"] = "0,".number_format(0,$info["scale.quantity"],'.','');
	
	$info["scale.discount"] 			= 0;
	$info["scale.discount_format"] 		= "0,".number_format(0,$info["scale.discount"],'.','');
	
	$info["scale.discount_rate"] 		= 0;
	$info["scale.discount_rate_format"] = "0,".number_format(0,$info["scale.discount_rate"],'.','');
	
	// -- packing size / weight --
	$info["scale.size"] 		= 1;
	$info["scale.size_format"] = "0,".number_format(0,$info["scale.size"],'.','');
	
	$info["scale.weight"] 		= 1;
	$info["scale.weight_format"] = "0,".number_format(0,$info["scale.weight"],'.','');
	


	\sc\framework\foundation\settings\sc_settings_Class::getInstance()->set_multiple($info);
	\sc\framework\foundation\settings\sc_settings_Class::getInstance()->set_public_multiple($info);


}
initial_settings_scale();
