<?PHP
namespace sc\framework\foundation\session;

// == 註：6 支介面方法加 #[\ReturnTypeWillChange]——PHP 7.x 視為註釋（零副作用）、8.1+ 免除 return type deprecation
// == 唔用真實 return type 嘅原因：SessionHandlerInterface 要求 string|false／int|false 等 union（8.0+ 語法）——本框架下限 PHP 7.3 用唔到
class sc_session_file_Class implements \SessionHandlerInterface
{
	private static $instance = null;
	
	private $dbconn = null;
	
	// session 存檔路徑（set_session_save_path 明確指定；未指定時由 open() 兜底）
	private $savePath = "";
	
	public function __construct()	{}
	public static function getInstance()	
	{
        if (!isset(self::$instance)) 
		{
            $class = __CLASS__;
			self::$instance = new $class();
		}
		return self::$instance;
	}
	
	
	public function set_session_save_path($savePath)
	{
		$this->savePath = rtrim($savePath,"/");
		
		// == 目錄不存在 → 自動建立（對齊 Laravel 自動建 storage 目錄） ==
		if(!@is_dir($this->savePath))
		{
			@mkdir($this->savePath,SC_CHMOD,true);
		}
		
		@ini_set('session.save_path',$savePath);
	}
	
	public function check()
	{
		$savePath = $this->savePath;
		if(!@file_exists($savePath))
		{
			sc_trigger_error("Session save path : \"".$savePath."\" is not existing");
		}
		if(!@is_writable($savePath) || !@is_dir($savePath))
		{
			sc_trigger_error("Session save path : \"".$savePath."\" is writable");
		}
		
		return true;
	}
	
	
	
	#[\ReturnTypeWillChange]
public function open($savePath, $sessionName)
    {
		// 保留已設定的 savePath（set_session_save_path 明確指定；PHP 傳入的可能是預設值）
		// 若未設定過 → 用 PHP 傳入值兜底
		if (!$this->savePath) {
			$this->savePath = rtrim($savePath,"/");
		}
		
		return true;
    }
	#[\ReturnTypeWillChange]
public function close()
	{  
		return true;  
		
	} 
	#[\ReturnTypeWillChange]
public function read($id)
	{
		$id 	= $this->mres($id);
		
		$file = $this->savePath . '/sess_' . $id;
        if (file_exists($file)) 
		{
            $data = file_get_contents($file);
			
        } else 
		{
            $data = '';
        }
		
		return $data;
	}
	#[\ReturnTypeWillChange]
public function write($id, $data)
	{  
		
		$id 	= $this->mres($id);
		$file 	= $this->savePath . '/sess_' . $id;
		
		$result = file_put_contents($file, $data);
		
		// 回傳 false 才是失敗；0（寫入 0 bytes 空 session）是成功——不可用三元判斷（0 會被當 false）
		return $result === false ? false : true;
	}
	
	#[\ReturnTypeWillChange]
public function destroy($id)
	{  
		$id 	= $this->mres($id);
		
		$file = $this->savePath ."/sess_".$id;
        if (file_exists($file)) 
		{
           unlink($file);
        }

        return true;
	}
	
	#[\ReturnTypeWillChange]
public function gc($max)
	{  
		foreach (glob($this->savePath ."/sess_*") as $file) 
		{
            if (filemtime($file) + $max < time() && file_exists($file)) {
                unlink($file);
            }
        }

        return true;  
	} 
	private function mres($data)	
	{
		$search = array("\\",  "\x00", "\n",  "\r",  "'",  '"', "\x1a");
		$replace = array("\\\\","\\0","\\n", "\\r", "\'", '\"', "\\Z");

		return str_replace($search, $replace, $data);
	}
	
}
