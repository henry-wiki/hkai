"""系統級環境偵測（唯讀——與專案無關，整台機器一份）。

用法（獨立執行——除錯／驗證用）：
    python -m diagnostics.system_env.system_env

輸出（stdout，單行 JSON——成功）：
    {
      "ok": true,
      "detected_at": "2026-09-30 12:00:00",
      "os": {"name": "Windows", "release": "10", "version": "10.0.19045", "arch": "AMD64", "hostname": "..."},
      "hardware": {"cpu_cores": 8, "memory_total_gb": 31.9, "memory_available_gb": 12.3},   # psutil 缺 = {}
      "disk": {"mount": "G:\\\\", "free_gb": 55.2, "total_gb": 118.0},                       # 當前工作碟——失敗 = null
      "network": {"internet": true},                                                       # best-effort 1.5 秒
      "container": false,                                                                  # Linux 容器偵測（其餘 = false）
      "shell": "PowerShell 5.1.19041" 或 "cmd.exe" 或 "/bin/bash",
      "timezone": "UTC+0800 (中國標準時間)",
      "python_version": "3.13.x",
      "runtimes": {"php": "7.3.33", "node": "20.x", "python": "3.11.16", ...},   # 只列偵測到的
      "tools": {"package_managers": [...], "build_tools": [...], "linters": [...], "containers": [...], "vcs": [...]}
    }

輸出（stdout，單行 JSON——失敗）：
    {"ok": false, "error": "..."}

設計：偵測一律 Python 層執行（platform／shutil／subprocess／psutil——跨平台一致，PHP 不做系統偵測）；
      所有偵測項目＝表格式（一項一行——加／減偵測項目只需改表，核心零改動）；
      全部欄位可選（偵測唔到＝空／唔放——唔寫 "unknown"／"N/A"，避免 AI 幻覺）；
      runtime 先 shutil.which 定位再跑 --version（缺工具零成本跳過，不靠 stderr 字樣猜）；
      單一命令逾時 5 秒自我保護；網絡檢查 best-effort 1.5 秒（唔拖慢）；結果欄位名即 JSON 契約（PHP 渲染層直接按 key 取值）。
"""

import datetime
import json
import os
import platform
import re
import shutil
import socket
import subprocess
import sys

# psutil（硬件／記憶體——缺＝該些欄位唔放；bridge 環境已裝）
try:
    import psutil
except ImportError:
    psutil = None

# 單一版本命令逾時（秒）——命令卡住自我保護
VERSION_TIMEOUT_SECONDS = 5

# 網絡檢查逾時（秒）——best-effort，唔拖慢偵測
NETWORK_TIMEOUT_SECONDS = 1.5

# runtime 偵測表：名稱 → (命令, 版本擷取樣式)——順序＝輸出欄位順序
RUNTIME_CHECKS = [
    ("php", "php", r"PHP (\d+\.\d+\.\d+)"),
    ("python", "python3", r"Python (\d+\.\d+\.\d+)"),
    ("node", "node", r"v(\d+\.\d+\.\d+)"),
    ("go", "go", r"go(\d+\.\d+\.\d+)"),
    ("rust", "rustc", r"rustc (\d+\.\d+\.\d+)"),
    ("java", "java", r'"(\d+[\.\d+]*)"'),
    ("dotnet", "dotnet", r"(\d+\.\d+\.\d+)"),
    ("ruby", "ruby", r"ruby (\d+\.\d+\.\d+)"),
    ("gcc", "gcc", r"(?:\(.*?\) )?(\d+\.\d+\.\d+)"),
    ("clang", "clang", r"version (\d+\.\d+\.\d+)"),
]

# 工具偵測表：分類 → 命令名清單（shutil.which 存在即列入）
TOOL_CATEGORIES = [
    ("package_managers", ["composer", "npm", "yarn", "pnpm", "bun", "pip", "pip3", "cargo", "gem", "nuget"]),
    ("build_tools", ["cmake", "make", "ninja", "msbuild", "gradle", "mvn", "bazel"]),
    ("linters", ["phpstan", "phpcs", "eslint", "prettier", "ruff", "flake8", "clippy", "pylint"]),
    ("containers", ["docker", "docker-compose", "podman"]),
    ("vcs", ["git", "hg", "svn"]),
]


class system_env_class:
    def __init__(self):
        pass

    def run_version_command(self, binary_path, command_arguments):
        """執行版本命令——回傳輸出文字（失敗／逾時回空字串）。"""
        command = [binary_path, *command_arguments]

        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=VERSION_TIMEOUT_SECONDS,
                check=False,
            )
        except (OSError, subprocess.SubprocessError):
            return ""

        # java -version 等特殊命令輸出在 stderr → 兩邊合併取第一行
        output_text = (completed.stdout + completed.stderr).strip()

        return output_text.splitlines()[0] if output_text else ""

    def collect_os_info(self):
        """收集作業系統資訊（platform 模組——跨平台結構化欄位）。"""
        os_info = {}
        os_info["name"] = platform.system()
        os_info["release"] = platform.release()
        os_info["version"] = platform.version()
        os_info["arch"] = platform.machine()
        os_info["hostname"] = platform.node()

        return os_info

    def collect_hardware(self):
        """收集硬件事實（CPU 核心／記憶體——psutil 缺＝空 dict，欄位唔放）。"""
        hardware = {}

        # 判定：psutil 未裝 → 空（欄位可選）
        if psutil is None:
            return hardware

        memory = psutil.virtual_memory()

        hardware["cpu_cores"] = psutil.cpu_count(logical=True)
        hardware["memory_total_gb"] = round(memory.total / (1024 ** 3), 1)
        hardware["memory_available_gb"] = round(memory.available / (1024 ** 3), 1)

        return hardware

    def collect_disk(self):
        """偵測當前工作碟空間（磁碟用量——失敗 = null，欄位唔放）。"""
        # Windows：工作目錄所在碟（如 G:\\）；POSIX：根
        if platform.system() == "Windows":
            mount_label = os.path.splitdrive(os.getcwd())[0] + "\\"
        else:
            mount_label = "/"

        try:
            usage = shutil.disk_usage(mount_label)
        except OSError:
            return None

        disk = {}
        disk["mount"] = mount_label
        disk["free_gb"] = round(usage.free / (1024 ** 3), 1)
        disk["total_gb"] = round(usage.total / (1024 ** 3), 1)

        return disk

    def collect_network(self):
        """偵測對外網絡（best-effort：逾時內連得上 1.1.1.1:443 當有外網）。"""
        internet_reachable = False

        try:
            with socket.create_connection(("1.1.1.1", 443), timeout=NETWORK_TIMEOUT_SECONDS):
                internet_reachable = True
        except OSError:
            internet_reachable = False

        network = {}
        network["internet"] = internet_reachable

        return network

    def collect_container(self):
        """偵測是否喺容器內（Linux：/.dockerenv 或 cgroup 含 docker／containerd——其餘 = false）。"""
        # 判定：非 Linux → 唔係容器（Windows／macOS 本機）
        if platform.system() != "Linux":
            return False

        # 判定：/.dockerenv 存在 → 容器
        if os.path.exists("/.dockerenv"):
            return True

        try:
            with open("/proc/1/cgroup", "r", encoding="utf-8", errors="replace") as cgroup_file:
                cgroup_text = cgroup_file.read()
        except OSError:
            return False

        # cgroup 出現容器標記 → 容器
        container_marks = ["docker", "containerd", "kubepods"]
        for container_mark in container_marks:
            if container_mark in cgroup_text:
                return True

        return False

    def collect_shell(self):
        """偵測預設 shell（Windows 依 COMSPEC／POSIX 依 SHELL 環境變數）。"""
        os_name = platform.system()

        # Windows：COMSPEC 指向 PowerShell 即取版本，否則 cmd.exe
        if os_name == "Windows":
            comspec_path = os.environ.get("COMSPEC", "")

            # COMSPEC 含 powershell／pwsh → 查 PowerShell 版本
            if "powershell" in comspec_path.lower() or "pwsh" in comspec_path.lower():
                version_text = self.run_version_command(
                    comspec_path, ["-NoProfile", "-Command", "$PSVersionTable.PSVersion.ToString()"])

                # 取到版本 → PowerShell x.y 名稱
                if version_text != "":
                    return "PowerShell " + version_text

                return "PowerShell"

            return "cmd.exe"

        # POSIX：直接讀 SHELL 環境變數
        return os.environ.get("SHELL", "unknown")

    def collect_timezone(self):
        """偵測目前時區（UTC 偏移＋顯示名）。"""
        local_now = datetime.datetime.now().astimezone()
        utc_offset = local_now.strftime("%z")
        timezone_name = local_now.tzname() or ""

        # 符號補齊：+0800 → UTC+08:00（後方帶時區顯示名）
        offset_label = "UTC" + utc_offset[0] + utc_offset[1:3] + ":" + utc_offset[3:]

        # 顯示名缺失 → 只回偏移
        if timezone_name == "":
            return offset_label

        return offset_label + " (" + timezone_name + ")"

    def collect_runtimes(self):
        """偵測已安裝 runtime 版本（先 which 定位，再跑版本命令擷取）。"""
        runtimes_found = {}

        # 逐 runtime 檢查（表序＝輸出序）
        for (runtime_name, command_name, version_pattern) in RUNTIME_CHECKS:
            binary_path = shutil.which(command_name)
            version_match = None

            # 命令存在 → 跑版本命令擷取
            if binary_path is not None:
                output_text = self.run_version_command(binary_path, ["--version"])
                version_match = re.search(version_pattern, output_text)

            # python：PATH 無／命中壞存根（WindowsApps stub 輸出空）→ 以當前直譯器補位（本程序即 python）
            if runtime_name == "python" and (binary_path is None or version_match is None):
                binary_path = sys.executable
                output_text = self.run_version_command(binary_path, ["--version"])
                version_match = re.search(version_pattern, output_text)

            # 輸出第一行擷取版本號（擷取不到＝非版本輸出（如壞 shim 的錯誤文字）→ 唔收，當未安裝）
            if version_match:
                runtimes_found[runtime_name] = version_match.group(1)

        return runtimes_found

    def collect_tools(self):
        """偵測常用工具（shutil.which 存在即列入該分類）。"""
        tools_found = {}

        # 逐分類檢查命令存在性
        for (category_name, tool_names) in TOOL_CATEGORIES:
            installed_tools = []

            # 逐工具 which（命中即名）
            for tool_name in tool_names:
                if shutil.which(tool_name) is not None:
                    installed_tools.append(tool_name)

            # 該分類有命中才寫入（空分類不佔欄位）
            if installed_tools:
                tools_found[category_name] = installed_tools

        return tools_found

    def get_system_env(self):
        result = {}
        result["ok"] = True
        result["detected_at"] = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        result["os"] = self.collect_os_info()
        result["hardware"] = self.collect_hardware()
        result["disk"] = self.collect_disk()
        result["network"] = self.collect_network()
        result["container"] = self.collect_container()
        result["shell"] = self.collect_shell()
        result["timezone"] = self.collect_timezone()
        result["python_version"] = platform.python_version()
        result["runtimes"] = self.collect_runtimes()
        result["tools"] = self.collect_tools()

        return result

    def main(self):
        # stdout 強制 UTF-8（Windows 預設 cp950——PHP 側固定收 UTF-8 JSON）
        sys.stdout.reconfigure(encoding="utf-8")

        result = self.get_system_env()
        print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    system_env_class().main()
