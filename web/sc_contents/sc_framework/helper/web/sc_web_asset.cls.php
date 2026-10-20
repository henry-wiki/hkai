<?PHP
namespace sc\framework\helper\web;

class sc_web_asset_Class
{
    
    private static $instance = null;
    
    // 資源登記：group → [{name, src}, ...]（register_assets 建立；"*" = global 永遠載入）
    private $web_assets = [];
    
    // js module 登記（group → [name => 來源]——ESM 專用；與 register_assets 分開——jsmods 走 <script type='module'>）
    private $web_jsmods = [];
    
    public static function getInstance()	
    {
        if (!isset(self::$instance)) 
        {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
    }
    
    // 整組註冊資源（group → [name => 來源, ...]；"*" = global 永遠載入）
    public function register_assets($group,$assets)
    {
        // 首次註冊該組 → 初始化（避免 undefined index）
        if(!isset($this->web_assets[$group]))
        {
            $this->web_assets[$group] = [];
        }
        
        // 逐個登記（定型點：src 原樣存——註冊端已給 embed 最終可用路徑；embed 零轉換）
        foreach($assets as $name => $src)
        {
            $srcType      = strtolower(pathinfo($src, PATHINFO_EXTENSION));
            $srcHttpPos   = strpos($src, "http");          // http 前綴位置（false = 非 http 開頭）
            $srcIsHttp    = ($srcHttpPos === 0);           // 開頭即 http → http 來源
            $srcSource    = $srcIsHttp ? "http" : "local";
            
            $this->web_assets[$group][] = [
                "name"   => $name,
                "src"    => $src,
                "type"   => $srcType,
                "source" => $srcSource,
            ];
        }
    }
    
    // 依 group 取出資源清單（自動併入 global "*" 組）→ 返回 [{name, src, type, source}, ...]
    // 零運算：註冊時已定型，直接帶出
    public function load_assets($group)
    {
        $assets = [];
        
        // global 組（"*"）永遠包含
        if(isset($this->web_assets["*"]))
        {
            $assets = array_merge($assets, $this->web_assets["*"]);
        }
        
        // 指定組
        if(isset($this->web_assets[$group]))
        {
            $assets = array_merge($assets, $this->web_assets[$group]);
        }
        
        return $assets;
    }
    
    // 輸出端：資源清單 → head HTML（<script>/<link> tag）
    public function get_embed_assets($assets)
    {
        $output_lines = [];
        foreach($assets as $asset)
        {
            $ext = $asset["type"];
            $src = $asset["src"];
            
            // css → link tag
            if($ext === "css")
            {
                $output_lines[] = "<link href='".$src."' rel='stylesheet' type='text/css' />";
            }
            // js → script tag（傳統 script——CDN 庫與非 ESM 資產；plugin js 由 manifest + dynamic import 載入）
            else if($ext === "js")
            {
                $output_lines[] = "<script type='text/javascript' src='".$src."'></script>";
            }
        }
        
        return implode("\r\n", $output_lines);
    }
    
    #############################################################################################################
    
    // 整組註冊 js module（group → [name => 來源, ...]；"*" = global 永遠載入）
    public function register_jsmods($group,$jsmods)
    {
        // 首次註冊該組 → 初始化（避免 undefined index）
        if(!isset($this->web_jsmods[$group]))
        {
            $this->web_jsmods[$group] = [];
        }
        
        // 逐個登記（定型點：src 原樣存——註冊端已給 embed 最終可用路徑；embed 零轉換）
        foreach($jsmods as $name => $src)
        {
            $this->web_jsmods[$group][] = [
                "name" => $name,
                "src"  => $src,
            ];
        }
    }
    
    // 依 group 取出 js module 清單（自動併入 global "*" 組）
    public function load_jsmods($group)
    {
        $jsmods = [];
        
        // global 組（"*"）永遠包含
        if(isset($this->web_jsmods["*"]))
        {
            $jsmods = array_merge($jsmods, $this->web_jsmods["*"]);
        }
        
        // 指定組
        if(isset($this->web_jsmods[$group]))
        {
            $jsmods = array_merge($jsmods, $this->web_jsmods[$group]);
        }
        
        return $jsmods;
    }
    
    // 輸出端：js module 清單 → <script type='module' src=...>（ESM 入口）
    public function get_embed_jsmods($jsmods)
    {
        $output_lines = [];
        foreach($jsmods as $jsmod)
        {
            $output_lines[] = "<script type='module' src='".$jsmod["src"]."'></script>";
        }
        
        return implode("\r\n", $output_lines);
    }
    
    #############################################################################################################
    
}
