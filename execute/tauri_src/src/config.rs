// config.rs — 讀取設定（config.toml 優先·再 assets\shell.json·最後內建預設）
// 一個動作·一個檔：只負責「把設定變成 struct」

use std::collections::HashMap;
use std::path::PathBuf;

use serde::Deserialize;

#[derive(Deserialize, Clone, Debug)]
#[serde(default)]
pub struct SplashConfig {
    pub width: u32,
    pub height: u32,
    pub min_ms: u64,
}

impl Default for SplashConfig {
    fn default() -> Self {
        Self { width: 360, height: 240, min_ms: 1000 }
    }
}

#[derive(Deserialize, Clone, Debug)]
pub struct ChildSpec {
    pub exe: String,
    #[serde(default)]
    pub args: Vec<String>,
    #[serde(default)]
    pub env: HashMap<String, String>,
}

#[derive(Deserialize, Clone, Debug)]
#[serde(default)]
pub struct ShellConfig {
    pub mode: String,
    pub splash: SplashConfig,
    pub ports: Vec<[u16; 2]>,
    pub children: Vec<ChildSpec>,
    pub on_close_script: String,
    pub web_dir: String,
}

impl Default for ShellConfig {
    fn default() -> Self {
        Self {
            mode: "builtin".to_string(),
            splash: SplashConfig::default(),
            ports: vec![[7000, 7999], [3000, 3999], [13000, 13999]],
            children: vec![
                ChildSpec {
                    exe: "bin/php/php.exe".to_string(),
                    args: vec![
                        "-S".to_string(),
                        "127.0.0.1:{port}".to_string(),
                        "-t".to_string(),
                        ".".to_string(),
                    ],
                    env: HashMap::from([("PHP_CLI_SERVER_WORKERS".to_string(), "4".to_string())]),
                },
                ChildSpec {
                    exe: "bin/apache/bin/httpd.exe".to_string(),
                    args: vec!["-f".to_string(), "bin/apache/conf/httpd.conf".to_string()],
                    env: HashMap::new(),
                },
            ],
            on_close_script: "".to_string(),
            web_dir: "../web".to_string(),
        }
    }
}

impl ShellConfig {
    /// docroot 絕對路徑（支援 `web`／`./web`／`../web`／絕對路徑）
    pub fn web_path(&self) -> PathBuf {
        root_dir().join(&self.web_dir)
    }
}

fn root_dir() -> PathBuf {
    std::env::current_exe()
        .ok()
        .and_then(|path| path.parent().map(|parent| parent.to_path_buf()))
        .unwrap_or_else(|| PathBuf::from("."))
}

/// 回傳（設定, 可選警告訊息）
pub fn load() -> (ShellConfig, Option<String>) {
    let defaults = ShellConfig::default();

    // 1. config.toml（exe 根目錄·唔打包）
    let toml_path = root_dir().join("config.toml");
    if let Ok(text) = std::fs::read_to_string(&toml_path) {
        match toml::from_str::<ShellConfig>(&text) {
            Ok(parsed) => {
                return if parsed.mode == "live" {
                    (parsed, None)
                } else {
                    (defaults, None)
                };
            }
            Err(error) => {
                return (defaults, Some(format!("config.toml parse failed: {}", error)));
            }
        }
    }

    // 2. assets\shell.json（舊格式 fallback）
    let json_path = root_dir().join("assets").join("shell.json");
    if let Ok(text) = std::fs::read_to_string(&json_path) {
        if let Ok(parsed) = serde_json::from_str::<ShellConfig>(&text) {
            return if parsed.mode == "live" { (parsed, None) } else { (defaults, None) };
        }
    }

    (defaults, None)
}
