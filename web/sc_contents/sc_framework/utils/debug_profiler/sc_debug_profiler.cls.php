<?PHP
namespace sc\framework\utils\debug_profiler;

class sc_debug_profiler_Class
{
	
	/** @var Profiler|null 唯一的實例 */
    private static $instance = null;

    /** @var array 存儲開始時間 */
    private $timers = [];

    /** @var array 存儲計時結果 */
    private $records = [];

    /**
     * 私有化建構子，防止外部 new
     */
    private function __construct()
    {
        // 初始時可以記錄一個開始標誌
        $this->records['profiler_start_time'] = microtime(true);
    }

    /**
     * 獲取唯一的 Profiler 實例 (Singleton 模式核心)
     *
     * @return Profiler
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 開始一個計時器
     *
     * @param string $name 計時器的唯一名稱
     */
    public function start($name)
    {
        $this->timers[$name] = microtime(true);
    }

    /**
     * 停止一個計時器並記錄結果
     *
     * @param string $name 之前用 start() 啟動的計時器名稱
     * @return float|null 返回本次操作的耗時（秒），如果計時器未啟動則返回 null
     */
    public function stop($name)
    {
        if (!isset($this->timers[$name])) {
            $this->records[$name] = 'Error: Timer was stopped without being started.';
            return null;
        }

        $startTime = $this->timers[$name];
        $endTime = microtime(true);
        $elapsedTime = $endTime - $startTime;

        // 直接將結果存入 records 陣列，鍵是計時器名稱
        $this->records[$name] = $elapsedTime;
        
        // 可選：為了避免一個名稱被多次 stop，可以清除它
        unset($this->timers[$name]);
        
        return $elapsedTime;
    }

    /**
     * 獲取所有計時記錄
     *
     * @return array 返回一個包含所有計時結果的關聯陣列
     */
    public function getReport()
    {
        return $this->records;
    }
	
	
}














