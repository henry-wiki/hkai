<?PHP
namespace sc\framework\i18n;

// == Locale 管理（遷自 helper/web/sc_web_langs；與 sc_i18n 字典同屬 i18n 元件） ==

class sc_locale_Class
{
	
	private static $instance = null;
	private $langs = array();
	
	
	public static function getInstance()	
	{
        if (!isset(self::$instance)) 
		{
            $class = __CLASS__;
			self::$instance = new $class();
		}
		return self::$instance;
	}
	
	public function __construct()
	{
		$this->initial();
	}
	
	// 語系清單（語系碼對應 i18n/lang/ 字典檔）
	public function initial()
	{
		$langs = array();
		
		// **************************************************************************************************************************
		$langs["en"] = array("Code"=>"en"	,"Name" => "English"	,"ShortName" => "Eng"	,"SortIndex" => 100	,"IsDefault"=>0	);
		$langs["sc"] = array("Code"=>"sc"	,"Name" => "简体中文"	,"ShortName" => "簡"	,"SortIndex" => 101	,"IsDefault"=>0	);
		$langs["tc"] = array("Code"=>"tc"	,"Name" => "繁體中文"	,"ShortName" => "繁"	,"SortIndex" => 101	,"IsDefault"=>0	);
		
		array_order_by($langs,"SortIndex asc");
		
		$this->langs = $langs;
	}
	
	
	public function get_langs()	{
		
		$langs = $this->langs;
		
		return $langs;
	}
	
	public function get_lang_item($lang)
	{
		$langs = $this->get_langs();
		
		return (isset($langs[$lang]) ? $langs[$lang] : null);
	}
	
	public function get_default_lang_item()
	{
		$langs = $this->get_langs();
		
		// 篩出標記為預設的語系
		$filter_rows = array_filter($langs,function($item)	{return (isset($item["IsDefault"]) ? $item["IsDefault"] : null);});
		
		// 無預設標記 → 取第一個語系
		if(@count($filter_rows))
		{
			$lang_item = current($filter_rows);
		}
		else 
		{
			$lang_item = current($langs);
		}
		
		return @$lang_item;
	}

	public function set_display_lang($lang)
	{
		$_SESSION["_lang"] = $lang;
	}
	
	public function is_valid_lang($lang)
	{
		$lang_item 	= $this->get_lang_item($lang);
		
		if(@$lang_item)
			return true;
		
		return false;
	}
	
	public function get_display_lang()
	{
		// session 有記錄 → 取該語系
		if(@isset($_SESSION["_lang"]))
		{
			$lang 		= $_SESSION["_lang"];
			$lang_item 	= $this->get_lang_item($lang);
		}
		else 
		{
			$lang_item = null;
		}
		
		// 語系無效或無記錄 → fallback 預設語系
		if(!@$lang_item)
		{
			$lang_item = $this->get_default_lang_item();
		}
		
		$lang = $lang_item["Code"];
		
		return $lang;
	}
	
	
}
