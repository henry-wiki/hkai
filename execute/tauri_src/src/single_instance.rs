// ---------------------------------------------------------------------------
// single_instance — 單一實例（以 exe 絕對路徑為準）
// 1. 用路徑 hash 做 named mutex 名
// 2. 已有同名 mutex → focus 舊實例（靠 PID 檔）→ 自己退出
// 3. 唔同路徑（例：dev 版 vs build 版）＝唔同 mutex → 可以同時開
// ---------------------------------------------------------------------------

use std::os::windows::process::CommandExt;
use std::path::PathBuf;

use windows::core::PCWSTR;
use windows::Win32::Foundation::{GetLastError, ERROR_ALREADY_EXISTS};
use windows::Win32::System::Threading::CreateMutexW;

/// 回傳 true＝已有同路徑實例（呼叫端應該即刻退出）
pub fn claim_or_focus() -> bool {
    let exe_path = std::env::current_exe().unwrap_or_else(|_| PathBuf::from("."));
    let exe_key = fnv1a(&exe_path.to_string_lossy().to_lowercase());
    let mutex_name = format!("HKAI_SHELL_{:016x}", exe_key);

    let mutex_wide: Vec<u16> = mutex_name.encode_utf16().chain(std::iter::once(0)).collect();

    // 建立 named mutex（handle 故意唔關——process 結束會自動釋放）
    let mutex_handle = unsafe { CreateMutexW(None, false, PCWSTR(mutex_wide.as_ptr())) };

    // 判定：mutex 建唔到 → 當成第一個實例（照開）
    if mutex_handle.is_err() {
        return false;
    }

    // 判定：mutex 已存在 → 有同路徑實例
    if unsafe { GetLastError() } == ERROR_ALREADY_EXISTS {
        focus_previous_instance(exe_key);
        return true;
    }

    write_pid_file(exe_key);

    false
}

/// PID 檔路徑（每個 exe 路徑一個）
fn pid_file_path(exe_key: u64) -> PathBuf {
    std::env::temp_dir().join(format!("hkai_shell_{:016x}.pid", exe_key))
}

/// 寫入自己 PID（供下一次重複啟動時 focus 用）
fn write_pid_file(exe_key: u64) {
    let pid_text = std::process::id().to_string();
    let _ = std::fs::write(pid_file_path(exe_key), pid_text);
}

/// focus 舊實例（用 PID 叫 WScript.Shell.AppActivate——失敗就靜靜退出）
fn focus_previous_instance(exe_key: u64) {
    let pid_text = std::fs::read_to_string(pid_file_path(exe_key)).unwrap_or_default();
    let previous_pid = pid_text.trim();

    // 判定：PID 檔冇內容 → 唔 focus
    if previous_pid.is_empty() {
        return;
    }

    let script = format!(
        "(New-Object -ComObject WScript.Shell).AppActivate({}) | Out-Null",
        previous_pid
    );

    // CREATE_NO_WINDOW（0x08000000）——唔開 console 窗（唔閃）
    let _ = std::process::Command::new("powershell.exe")
        .args(["-NoProfile", "-WindowStyle", "Hidden", "-Command", &script])
        .creation_flags(0x08000000)
        .spawn();
}

/// FNV-1a 64-bit（細、穩定、唔使額外 crate）
fn fnv1a(text: &str) -> u64 {
    let mut hash_value: u64 = 0xcbf2_9ce4_8422_2325;

    for text_byte in text.as_bytes() {
        hash_value ^= *text_byte as u64;
        hash_value = hash_value.wrapping_mul(0x0000_0100_0000_01b3);
    }

    hash_value
}
