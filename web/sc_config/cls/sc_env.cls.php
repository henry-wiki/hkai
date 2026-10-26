<?php
namespace sc\config;

// == sc_env：env 檔（.env）載入器與讀寫（sc_config 同款造法——平面鍵值） ==
// == 位置：web/sc_config/cls/（域 cls 根——與 config 同區） ==
// == load()：__construct 時讀 .env 檔（parse_ini_file RAW——與 setup／app 同一讀法） ==
// == get()：平面鍵讀取；set()：填值寫入（缺先加·已有唔覆蓋——寫 .env 檔） ==

class sc_env_Class
{
    private static $instance = null;
    
    private $env_values = array();
    private $loaded = false;
    
    public static function getInstance()
    {
        if (!@isset(self::$instance)) {
            $class          = __CLASS__;
            self::$instance = new $class();
        }
        return self::$instance;
    }
    
    public function __construct()
    {
        $this->load();
    }
    
    // 載入 .env 全部值（__construct 呼叫一次——檔由 sc_config core.env 取）
    public function load()
    {
        // 已載入 → 跳過（載入一次即可）
        if ($this->loaded) {
            return;
        }
        $this->loaded = true;
        
        // env 檔（sc_config core.env——單一來源）
        $env_file = sc_get_config("core.env");
        
        // env 檔唔存在 → 空
        if (!is_file($env_file)) {
            return;
        }
        
        $env_values = parse_ini_file($env_file, false, INI_SCANNER_RAW);
        
        // 解析失敗（非陣列）→ 空
        if (!is_array($env_values)) {
            return;
        }
        
        $this->env_values = $env_values;
    }
    
    // 讀取（平面鍵；無值回 default）
    public function get($key, $default = null)
    {
        // key 不存在 → 回 default
        if (!isset($this->env_values[$key])) {
            return $default;
        }
        
        return $this->env_values[$key];
    }
    
    // 讀取全部（平面 map 原形）
    public function get_all()
    {
        return $this->env_values;
    }
    
    // 寫入（填值——缺先加·已有唔覆蓋·冇行接尾；.env 未存在＝由範本出發；寫後更新記憶體）
    public function set($key, $value)
    {
        $env_file    = sc_get_config("core.env");
        $env_example = sc_get_config("core.env_example");
        
        $existing_lines = [];
        
        // .env 已存在 → 讀現有行（保留原文）
        if (is_file($env_file)) {
            $existing_text      = file_get_contents($env_file);
            $existing_text_unix = str_replace("\r\n", "\n", $existing_text);
            $existing_lines     = explode("\n", $existing_text_unix);
        }
        
        // .env 未存在 → 由範本出發（整個範本照抄——格式＝範本）
        if (!is_file($env_file)) {
            $existing_lines = $this->read_env_example_lines($env_example);
        }
        
        $output_lines = $this->apply_env_entry($existing_lines, $key, $value);
        
        // 內容零改動 → 唔寫（值已在——唔覆蓋）
        if ($output_lines === $existing_lines) {
            return;
        }
        
        // 去尾空行（重砌只留一個結尾換行）
        while (count($output_lines) > 0) {
            $last_output_index = count($output_lines) - 1;
            $last_output_line  = trim($output_lines[$last_output_index]);
            
            // 判定：尾行唔空 → 停
            if ($last_output_line !== "") {
                break;
            }
            
            array_pop($output_lines);
        }
        
        // .env＝LF 結尾（php parse_ini RAW＋dotenv 兩通）
        $env_output_text = implode("\n", $output_lines) . "\n";
        
        file_put_contents($env_file, $env_output_text, LOCK_EX);
        
        $this->env_values[$key] = $value;
    }
    
    // 覆蓋寫入（值有則改、無則檔末補；雙引號式；temp+rename 原子；寫後更新記憶體）
    public function replace($key, $value)
    {
        $env_file = sc_get_config("core.env");

        $env_text = "";

        // 判定：檔存在 → 讀原文（保留註釋與其他行）
        if (is_file($env_file)) {
            $env_text = (string)file_get_contents($env_file);
        }

        $env_eol = "\n";

        // 判定：原檔用 CRLF → 跟隨原檔
        if (strpos($env_text, "\r\n") !== false) {
            $env_eol = "\r\n";
        }

        $env_line     = $key . "=" . $this->quote_env_value($value);
        $source_lines = explode("\n", str_replace("\r\n", "\n", $env_text));
        $output_lines = [];
        $replaced     = false;

        // 逐行檢查（同一 key 只處理第一行——其餘重複行略過）
        foreach ($source_lines as $line_item) {
            $line_key = $this->parse_env_line_key($line_item);

            // 判定：命中目標 key 且已替換過 → 略過
            if ($line_key === $key) {
                // 判定：首次命中 → 覆蓋
                if ($replaced === false) {
                    $output_lines[] = $env_line;
                    $replaced       = true;
                }

                continue;
            }

            $output_lines[] = $line_item;
        }

        // 判定：檔內無此 key → 先去尾空行，再檔末補一行
        if ($replaced === false) {
            while (count($output_lines) > 0) {
                $last_output_line = trim($output_lines[count($output_lines) - 1]);

                // 判定：尾行唔空 → 停
                if ($last_output_line !== "") {
                    break;
                }

                array_pop($output_lines);
            }

            $output_lines[] = $env_line;
        }

        // 去尾空行（重砌只留一個結尾換行）
        while (count($output_lines) > 0) {
            $last_output_line = trim($output_lines[count($output_lines) - 1]);

            // 判定：尾行唔空 → 停
            if ($last_output_line !== "") {
                break;
            }

            array_pop($output_lines);
        }

        $output_text = implode($env_eol, $output_lines) . $env_eol;

        // 原子寫（temp＋rename——避免寫一半損壞 .env）
        $temp_file = $env_file . ".tmp";
        file_put_contents($temp_file, $output_text, LOCK_EX);
        rename($temp_file, $env_file);

        $this->env_values[$key] = $value;
    }

    // 值加雙引號（內部反斜線／雙引號跳脫——與 .env 現行格式一致）
    private function quote_env_value($value)
    {
        $escaped_value = str_replace("\\", "\\\\", $value);
        $escaped_value = str_replace("\"", "\\\"", $escaped_value);

        return "\"" . $escaped_value . "\"";
    }

    // 讀範本行（.env.example——範本唔存在＝空）
    private function read_env_example_lines($env_example)
    {
        // 判定：範本唔存在 → 空
        if (!is_file($env_example)) {
            return [];
        }
        
        $example_text      = file_get_contents($env_example);
        $example_text_unix = str_replace("\r\n", "\n", $example_text);
        
        return explode("\n", $example_text_unix);
    }
    
    // 填一個值入行陣（搵到空值行＝填入；該行有值＝唔覆蓋；冇行＝接尾）——回新行陣
    private function apply_env_entry($env_lines, $env_key, $env_value)
    {
        $applied_lines = $env_lines;
        
        // 逐行搵 key（同一 key 只一行——搵到即處理完）
        foreach ($applied_lines as $line_index => $line_item) {
            $line_key = $this->parse_env_line_key($line_item);
            
            // 判定：唔係呢個 key → 下一行
            if ($line_key !== $env_key) {
                continue;
            }
            
            $line_value = $this->parse_env_line_value($line_item);
            
            // 判定：該行已有值 → 唔覆蓋（原樣保留）
            if ($line_value !== "") {
                return $applied_lines;
            }
            
            $applied_lines[$line_index] = $env_key . "=" . $env_value;
            
            return $applied_lines;
        }
        
        // 冇呢個 key → 接尾
        $applied_lines[] = $env_key . "=" . $env_value;
        
        return $applied_lines;
    }
    
    // 抽一行嘅 key（空行／註解＝空串）
    private function parse_env_line_key($env_line)
    {
        $trimmed_line = trim($env_line);
        
        // 判定：空行 → 冇 key
        if ($trimmed_line === "") {
            return "";
        }
        
        // 判定：註解行 → 冇 key
        if (strpos($trimmed_line, "#") === 0) {
            return "";
        }
        
        $key_parts = explode("=", $trimmed_line, 2);
        
        return trim($key_parts[0]);
    }
    
    // 抽一行嘅值（冇「=」＝空串）
    private function parse_env_line_value($env_line)
    {
        $trimmed_line = trim($env_line);
        
        $key_parts = explode("=", $trimmed_line, 2);
        
        // 判定：冇「=」→ 冇值
        if (count($key_parts) < 2) {
            return "";
        }
        
        return trim($key_parts[1]);
    }
}
