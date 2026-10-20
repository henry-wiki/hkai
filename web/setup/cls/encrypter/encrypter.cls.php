<?php

namespace sc\setup\encrypter;

// == HKAI setup encrypter：encrypter 域本體（域本體——singleton）
// == 職責：敏感值加解密一對——encrypt_text()（明文→存放字串）／decrypt_text()（存放字串→明文）——統一入口 setup()->encrypter()
// == key 源：記憶體即時導出——站根 stat（inode／device）＋主機名＋站根路徑＋固定 salt——零檔案、零 OS 判定、WIN／LIN／MAC 通用
// == 語意：同一部機同一位置＝解得返；搬去別處（換機／改路徑／改名）＝解唔到（同機上面嘅攻擊者防唔到——設計如此）
// == 規格：零框架（唔用 autoloader／session／DB——只用 PHP 內建 sodium）；需 sodium 擴展（PHP 7.2+ 內建）；語法下限 PHP 5.4
// == 格式：存放字串＝base64（sodium nonce 24 bytes＋密文）

class encrypter_Class
{
    private static $instance = null;

    private $encryption_key = null;

    /**
     * 單例（系統唯一入口——F12 鏈式跳定義靠本註解）
     *
     * @return \sc\setup\encrypter\encrypter_Class
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct()
    {
    }

    // 加密（明文 → 存放字串——入過程檔前用；空＝原樣回空）
    public function encrypt_text($plain_text)
    {
        $plain_text = (string)$plain_text;

        // 判定：空字串 → 原樣回空（唔加密）
        if ($plain_text === "") {
            return "";
        }

        $encryption_key = $this->derive_key();
        $nonce          = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $ciphertext = sodium_crypto_secretbox($plain_text, $nonce, $encryption_key);

        $payload = $nonce . $ciphertext;

        return base64_encode($payload);
    }

    // 解密（存放字串 → 明文——寫 .env／顯示前用；解唔到＝回空）
    public function decrypt_text($stored_text)
    {
        $stored_text = (string)$stored_text;

        // 判定：空字串 → 原樣回空（唔解密）
        if ($stored_text === "") {
            return "";
        }

        $payload = base64_decode($stored_text, true);

        // 判定：base64 唔合法 → 回空
        if ($payload === false) {
            return "";
        }

        // 判定：長度唔夠（nonce 24＋最少 1 密文字）→ 回空
        if (strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return "";
        }

        $encryption_key = $this->derive_key();
        $nonce          = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext     = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain_text = false;

        // 判定：解唔到（唔同機／位置／被改過——sodium 可能擲 SodiumException）→ 回空
        try {
            $plain_text = sodium_crypto_secretbox_open($ciphertext, $nonce, $encryption_key);
        } catch (\SodiumException $sodium_exception) {
            return "";
        }

        // 判定：解唔返（回 false）→ 回空
        if ($plain_text === false) {
            return "";
        }

        return $plain_text;
    }

    // 出 key（指紋＋固定 salt → sha256 32 bytes——同一部機同一位置＝穩定；每次即時、零存儲）
    private function derive_key()
    {
        // 判定：本請求已出過 → 用快取
        if ($this->encryption_key !== null) {
            return $this->encryption_key;
        }

        $fingerprint = $this->get_fingerprint();

        $this->encryption_key = hash("sha256", "sc-hkai_setup_encrypter_v1|" . $fingerprint, true);

        return $this->encryption_key;
    }

    // 出指紋（記憶體即時——站根 stat（inode／device）＋主機名＋站根路徑；零檔案、零 OS 判定）
    private function get_fingerprint()
    {
        $repo_root = sc_get_config("repo_root");

        $repo_root_stat = @stat($repo_root);

        // 判定：stat 失敗 → 歸空陣列（下面逐項兜底）
        if ($repo_root_stat === false) {
            $repo_root_stat = [];
        }

        $ino = 0;

        // 判定：stat 帶 ino → 用實際值；冇 → 用 fileinode 兜底
        if (isset($repo_root_stat["ino"])) {
            $ino = $repo_root_stat["ino"];
        } else {
            $ino = @fileinode($repo_root);
        }

        $dev = 0;

        // 判定：stat 帶 dev → 用實際值（冇＝0）
        if (isset($repo_root_stat["dev"])) {
            $dev = $repo_root_stat["dev"];
        }

        $fingerprint_parts = [];

        $fingerprint_parts[] = gethostname();
        $fingerprint_parts[] = php_uname("n");
        $fingerprint_parts[] = $repo_root;
        $fingerprint_parts[] = $ino;
        $fingerprint_parts[] = $dev;

        return implode("|", $fingerprint_parts);
    }
}
