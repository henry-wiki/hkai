// ---------------------------------------------------------------------------
// servers.rs — 子進程（PHP／Apache）
// 對外只有兩個 function：
//   start() 揀埠 → spawn 全部 → 等埠開 → 回傳埠
//   kill()  跑自訂 on_close.ps1（如有指令）→ 逐個 taskkill /T /F
// ---------------------------------------------------------------------------

use std::net::{SocketAddr, TcpStream};
use std::os::windows::process::CommandExt;
use std::process::{Child, Command, Stdio};
use std::sync::atomic::{AtomicBool, Ordering};
use std::sync::{Mutex, OnceLock};
use std::time::Duration;

use crate::config::ShellConfig;

const CREATE_NO_WINDOW: u32 = 0x0800_0000;   // 唔開黑窗
const READY_TIMEOUT_MS: u64 = 15_000;        // 等 PHP 起身最長 15 秒
const READY_POLL_MS: u64 = 100;              // 每 100ms 試一次

static CHILDREN: OnceLock<Mutex<Vec<Child>>> = OnceLock::new();
static KILLED: AtomicBool = AtomicBool::new(false);

/// 揀一個可用埠 → 起全部子進程 → 等到埠開 → 回傳埠
pub fn start(shell: &ShellConfig) -> u16 {
    let port = pick_port(&shell.ports);
    spawn_all(shell, port);
    wait_until_open(port);
    log::info!("children started port={}", port);
    port
}

/// 關閉全部子進程（只做一次·同步等 taskkill 完成）
pub fn kill(shell: &ShellConfig) {
    // ExitRequested 同 Exit 都會呼叫 → AtomicBool 保證只做一次
    if KILLED.swap(true, Ordering::SeqCst) {
        return;
    }

    run_on_close_script(shell);

    if let Some(children) = CHILDREN.get() {
        for child in children.lock().unwrap().iter() {
            // 只按 PID 殺（/T 連子孫）；嚴禁 /IM，免得誤殺 Laragon 或其他站
            let _ = Command::new("taskkill.exe")
                .args(["/PID", &child.id().to_string(), "/T", "/F"])
                .creation_flags(CREATE_NO_WINDOW)
                .stdout(Stdio::null())
                .stderr(Stdio::null())
                .status();
        }
        log::info!("children killed");
    }
}

/// 喺 config.ports 嘅範圍內揀一個未佔用埠
fn pick_port(groups: &[[u16; 2]]) -> u16 {
    let seed = std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap_or_default()
        .subsec_nanos() as usize;

    for attempt in 0..60 {
        let group = groups[(seed + attempt) % groups.len()];
        let span = (group[1] - group[0]) as usize;
        let candidate = group[0] + ((seed / (attempt + 1)) % (span + 1)) as u16;
        if !port_open(candidate) {
            return candidate;
        }
    }
    0
}

/// 試連一下：連得上＝已有程式佔用
fn port_open(port: u16) -> bool {
    TcpStream::connect_timeout(&SocketAddr::from(([127, 0, 0, 1], port)), Duration::from_millis(250)).is_ok()
}

/// 按 config.children 逐個 spawn（{port} 會換成實際埠）
fn spawn_all(shell: &ShellConfig, port: u16) {
    let root = crate::root_dir();
    let php_dir = root.join("bin").join("php");
    let web_dir = shell.web_path();
    let current_path = std::env::var("PATH").unwrap_or_default();

    for spec in &shell.children {
        let args: Vec<String> = spec
            .args
            .iter()
            .map(|value| {
                value
                    .replace("{port}", &port.to_string())
                    .replace("{web}", &web_dir.display().to_string())
            })
            .collect();

        let mut command = Command::new(root.join(&spec.exe));
        command
            .args(&args)
            .current_dir(&root)
            .env("PATH", format!("{};{}", php_dir.display(), current_path))
            .creation_flags(CREATE_NO_WINDOW)
            .stdout(Stdio::null())
            .stderr(Stdio::null());

        for (key, value) in &spec.env {
            command.env(key, value);
        }

        match command.spawn() {
            Ok(child) => register(child),
            Err(error) => log::error!("spawn failed: {} ({})", spec.exe, error),
        }
    }
}

/// 記錄子進程（關窗時用 PID 殺）
fn register(child: Child) {
    CHILDREN.get_or_init(|| Mutex::new(Vec::new())).lock().unwrap().push(child);
}

/// 輪詢等到埠開（最長 READY_TIMEOUT_MS）
fn wait_until_open(port: u16) {
    let attempts = READY_TIMEOUT_MS / READY_POLL_MS;
    for _ in 0..attempts {
        if port_open(port) {
            return;
        }
        std::thread::sleep(Duration::from_millis(READY_POLL_MS));
    }
    log::warn!("php port {} not ready after {} ms", port, READY_TIMEOUT_MS);
}

/// 執行 runtime\on_close.ps1（純註釋＝跳過·避免白等 PowerShell）
fn run_on_close_script(shell: &ShellConfig) {
    if shell.mode != "live" || shell.on_close_script.is_empty() {
        return;
    }

    let script = crate::root_dir().join(&shell.on_close_script);
    let text = match std::fs::read_to_string(&script) {
        Ok(value) => value,
        Err(_) => return,
    };

    let has_command = text.lines().any(|line| {
        let trimmed = line.trim().trim_start_matches('\u{feff}');
        !trimmed.is_empty() && !trimmed.starts_with('#')
    });
    if !has_command {
        return;
    }

    let _ = Command::new("powershell.exe")
        .args(["-NoProfile", "-ExecutionPolicy", "Bypass", "-File"])
        .arg(&script)
        .creation_flags(CREATE_NO_WINDOW)
        .stdout(Stdio::null())
        .stderr(Stdio::null())
        .status();
    log::info!("on_close script ran");
}
