"""專案級環境偵測（唯讀——與專案目錄相關；每個專案一份）。

用法（獨立執行——除錯／驗證用）：
    python -m diagnostics.project_env.project_env <project_path>

輸出（stdout，單行 JSON——成功）：
    {
      "ok": true,
      "detected_at": "2026-10-01 14:00:00",
      "root": "xxxxx",   # resolve 後（Windows 原樣 backslash——PHP 顯示層自己統一 "/"）
      "name": "xxxxx",
      "vcs": {"type": "git", "branch": "master", "dirty": false},   # 非 git repo = null
      "has_agents_md": true,
      "languages": [{"name": "PHP", "percent": 92}, {"name": "JavaScript", "percent": 8}],   # 副檔名統計 top 3
      "frameworks": ["Laravel"],                                    # manifest 內容標記——未偵測到 = []
      "build_systems": ["CMake"],
      "package_managers": ["composer", "npm"],
      "test_tools": ["PHPUnit"],
      "linters": ["phpstan"],
      "required_runtimes": {"php": "8.1"},                          # .php-version／.nvmrc 等——未偵測到 = {}
      "manifests": ["composer.json", "package.json"],
      "has_dockerfile": false,
      "has_docker_compose": false,
      "ci_cd": ["GitHub Actions"]                                   # 未偵測到 = []
    }

輸出（stdout，單行 JSON——失敗）：
    {"ok": false, "error": "..."}

設計：偵測一律 Python 層執行（跨平台一致，PHP 不做檔案系統／git 偵測）；
      所有偵測項目＝表格式（一項一行——加／減偵測項目只需改表，核心零改動）；
      全部欄位可選（偵測唔到＝空／唔放——唔寫 "unknown"／"N/A"，避免 AI 幻覺）；
      純標記（AGENTS.md／Dockerfile／docker-compose／CI／manifest／build／test／linter）用 Path 存在檢查（零成本）；
      語言統計＝副檔名計數（跳過依賴／產物目錄、20000 檔上限自我保護——唔做重型掃描）；
      git 只兩條輕量唯讀命令（branch --show-current／status --porcelain——--no-optional-locks），
      唔拉 diagnostics.git 全診斷（嗰邊係 Git tab 用嘅重查詢）；
      單一 git 命令逾時 5 秒自我保護；結果欄位名即 JSON 契約（PHP 渲染層直接按 key 取值）。
"""

import datetime
import json
import os
import subprocess
import sys
from pathlib import Path

# 單一 git 命令逾時（秒）——慢碟／大 repo 自我保護
GIT_TIMEOUT_SECONDS = 5

# CI/CD 偵測表：（標記名，相對路徑，是否目錄）——存在即列入
CI_CHECKS = [
    ("GitHub Actions", ".github/workflows", True),
    ("GitLab CI", ".gitlab-ci.yml", False),
    ("Jenkins", "Jenkinsfile", False),
]

# manifest 偵測表：語言／生態標記檔名——存在即列入（表序＝輸出序；新語言加一行即可）
MANIFEST_CHECKS = [
    "composer.json",      # PHP
    "package.json",       # Node／JS 生態
    "pyproject.toml",     # Python（現代）
    "requirements.txt",   # Python（傳統）
    "go.mod",             # Go
    "Cargo.toml",         # Rust
    "pom.xml",            # Java（Maven）
    "build.gradle",       # Java／Kotlin（Gradle）
    "Gemfile",            # Ruby
]

# 語言統計：跳過目錄表（依賴／產物／工具——唔入去數）
LANGUAGE_SKIP_DIRECTORIES = {
    ".git", ".svn", ".hg",
    "node_modules", "vendor", "bower_components",
    ".venv", "venv", "env", "__pycache__",
    "dist", "build", "target", "coverage",
    ".next", ".nuxt", ".idea", ".vscode",
}

# 語言統計：副檔名 → 語言名（一項一行——加語言只需加一行；表序＝同時保留輸出穩定）
EXTENSION_LANGUAGES = [
    (".php", "PHP"),
    (".py", "Python"),
    (".js", "JavaScript"),
    (".mjs", "JavaScript"),
    (".cjs", "JavaScript"),
    (".jsx", "JavaScript"),
    (".ts", "TypeScript"),
    (".tsx", "TypeScript"),
    (".vue", "Vue"),
    (".go", "Go"),
    (".rs", "Rust"),
    (".java", "Java"),
    (".kt", "Kotlin"),
    (".c", "C"),
    (".h", "C"),
    (".cpp", "C++"),
    (".cc", "C++"),
    (".hpp", "C++"),
    (".cs", "C#"),
    (".rb", "Ruby"),
    (".swift", "Swift"),
    (".lua", "Lua"),
    (".dart", "Dart"),
    (".html", "HTML"),
    (".htm", "HTML"),
    (".css", "CSS"),
    (".scss", "CSS"),
    (".less", "CSS"),
    (".sql", "SQL"),
    (".sh", "Shell"),
    (".ps1", "PowerShell"),
]

# 語言統計：輸出前 N 名＋掃描檔案數上限（自我保護——唔做重型掃描）
LANGUAGES_TOP_N = 3
LANGUAGES_SCAN_FILE_LIMIT = 20000

# 框架偵測表：（manifest 檔名，內容標記（小寫子串），框架名）——讀檔上限見 FRAMEWORK_READ_LIMIT
FRAMEWORK_HINTS = [
    ("composer.json", "laravel/framework", "Laravel"),
    ("composer.json", "symfony/framework-bundle", "Symfony"),
    ("composer.json", "wordpress", "WordPress"),
    ("package.json", '"react"', "React"),
    ("package.json", '"vue"', "Vue"),
    ("package.json", '"svelte"', "Svelte"),
    ("package.json", '"next"', "Next.js"),
    ("package.json", '"express"', "Express"),
    ("package.json", '"@angular/core"', "Angular"),
    ("requirements.txt", "django", "Django"),
    ("requirements.txt", "fastapi", "FastAPI"),
    ("requirements.txt", "flask", "Flask"),
    ("pyproject.toml", "django", "Django"),
    ("pyproject.toml", "fastapi", "FastAPI"),
]

# 框架偵測：單一 manifest 讀入上限（字元——大 lockfile 自我保護）
FRAMEWORK_READ_LIMIT = 524288

# 構建系統偵測表：（標記檔名，系統名）——存在即列入
BUILD_SYSTEM_MARKERS = [
    ("CMakeLists.txt", "CMake"),
    ("Makefile", "Make"),
    ("build.gradle", "Gradle"),
    ("build.gradle.kts", "Gradle"),
    ("meson.build", "Meson"),
    ("pom.xml", "Maven"),
    ("build.sbt", "sbt"),
]

# 包管理器偵測表：（標記檔名，管理器名）——存在即列入
PACKAGE_MANAGER_MARKERS = [
    ("composer.json", "composer"),
    ("package.json", "npm"),
    ("yarn.lock", "yarn"),
    ("pnpm-lock.yaml", "pnpm"),
    ("requirements.txt", "pip"),
    ("pyproject.toml", "pip"),
    ("Pipfile", "pipenv"),
    ("go.mod", "Go modules"),
    ("Cargo.toml", "cargo"),
    ("pom.xml", "maven"),
    ("build.gradle", "gradle"),
    ("Gemfile", "bundler"),
]

# 測試工具偵測表：（標記檔名，工具名）——存在即列入
TEST_TOOL_MARKERS = [
    ("phpunit.xml", "PHPUnit"),
    ("phpunit.xml.dist", "PHPUnit"),
    ("pytest.ini", "pytest"),
    ("tox.ini", "tox"),
    ("jest.config.js", "Jest"),
    ("jest.config.cjs", "Jest"),
    ("jest.config.ts", "Jest"),
    ("vitest.config.js", "Vitest"),
    ("vitest.config.ts", "Vitest"),
    (".rspec", "RSpec"),
]

# Linter 偵測表：（標記檔名，Linter 名）——存在即列入
LINTER_MARKERS = [
    ("phpstan.neon", "phpstan"),
    ("phpstan.neon.dist", "phpstan"),
    ("phpcs.xml", "PHP_CodeSniffer"),
    ("phpcs.xml.dist", "PHP_CodeSniffer"),
    (".eslintrc", "ESLint"),
    (".eslintrc.json", "ESLint"),
    (".eslintrc.js", "ESLint"),
    ("eslint.config.js", "ESLint"),
    ("ruff.toml", "ruff"),
    (".ruff.toml", "ruff"),
    (".flake8", "flake8"),
    (".pylintrc", "pylint"),
    ("stylelint.config.js", "stylelint"),
]

# 要求 Runtime 版本偵測表：（版本檔名，runtime 名）——存在即讀內容（小檔）
RUNTIME_VERSION_FILES = [
    (".php-version", "php"),
    (".python-version", "python"),
    (".nvmrc", "node"),
    (".node-version", "node"),
    (".ruby-version", "ruby"),
]

# 副檔名 → 語言名 反查表（由 EXTENSION_LANGUAGES 派生——零手抄）
EXTENSION_LANGUAGE_MAP = dict(EXTENSION_LANGUAGES)


class project_env_class:
    def __init__(self):
        pass

    def run_git_command(self, project_path, git_arguments):
        """在專案路徑執行只讀 git 命令——回傳 stdout（失敗／非 repo 回空字串）。"""
        command = ["git", "-C", project_path, "--no-optional-locks", *git_arguments]

        try:
            completed = subprocess.run(
                command,
                capture_output=True,
                text=True,
                encoding="utf-8",
                errors="replace",
                timeout=GIT_TIMEOUT_SECONDS,
            )
        except (subprocess.TimeoutExpired, OSError):
            return ""

        # 非零結束碼（唔係 repo／git 缺）→ 當無輸出
        if completed.returncode != 0:
            return ""

        return completed.stdout.strip()

    def collect_vcs(self, root_path):
        """偵測版本控制（.git 存在先當 git——branch／dirty 兩條輕量命令）。"""
        # 判定：唔係 git repo → None（專案無版本控制）
        if not (root_path / ".git").exists():
            return None

        branch_name = self.run_git_command(str(root_path), ["branch", "--show-current"])
        porcelain_output = self.run_git_command(str(root_path), ["status", "--porcelain"])

        vcs_found = {}
        vcs_found["type"] = "git"
        # branch 空字串（detached HEAD）→ 標 "detached"
        vcs_found["branch"] = branch_name if branch_name else "detached"
        vcs_found["dirty"] = porcelain_output != ""

        return vcs_found

    def collect_languages(self, root_path):
        """統計主要語言（副檔名計數——跳過依賴／產物目錄；20000 檔上限自我保護）。"""
        extension_counts = {}
        scanned_files = 0
        scan_truncated = False

        # 逐目錄走訪（就地修剪跳過目錄——唔入依賴／產物）
        for (directory_path, directory_names, file_names) in os.walk(root_path):
            directory_names[:] = [name for name in directory_names if name not in LANGUAGE_SKIP_DIRECTORIES]

            # 逐檔數語言副檔名
            for file_name in file_names:
                file_extension = Path(file_name).suffix.lower()

                # 判定：只數語言表內嘅副檔名
                if file_extension in EXTENSION_LANGUAGE_MAP:
                    extension_counts[file_extension] = extension_counts.get(file_extension, 0) + 1
                    scanned_files = scanned_files + 1

                    # 判定：達掃描上限 → 停（自我保護）
                    if scanned_files >= LANGUAGES_SCAN_FILE_LIMIT:
                        scan_truncated = True
                        break

            # 判定：已標記截斷 → 收檔
            if scan_truncated:
                break

        total_counted = sum(extension_counts.values())

        # 判定：一個語言檔都數唔到 → 空（欄位可選——唔放）
        if total_counted == 0:
            return []

        language_counts = {}

        # 逐副檔名歸併到語言名
        for (file_extension, extension_count) in extension_counts.items():
            language_name = EXTENSION_LANGUAGE_MAP[file_extension]
            language_counts[language_name] = language_counts.get(language_name, 0) + extension_count

        # 由多到小排序（次鍵＝語言名——輸出穩定）
        sorted_languages = sorted(language_counts.items(), key=lambda item: (-item[1], item[0]))

        languages_top = []

        # 組 top N（百分比＝四捨五入整數；0% 唔出）
        for (language_name, language_count) in sorted_languages[:LANGUAGES_TOP_N]:
            percent = round(language_count * 100 / total_counted)

            # 判定：百分比捨入到 0 → 唔出
            if percent == 0:
                continue

            languages_top.append({"name": language_name, "percent": percent})

        return languages_top

    def collect_frameworks(self, root_path):
        """偵測框架（讀 manifest 內容標記——小寫子串比對；每檔讀入上限 512KB）。"""
        frameworks_found = []

        # 逐提示比對（表序＝輸出序）
        for (manifest_name, marker_text, framework_name) in FRAMEWORK_HINTS:
            manifest_path = root_path / manifest_name

            # 判定：manifest 唔存在 → 跳過
            if not manifest_path.exists():
                continue

            try:
                with open(manifest_path, "r", encoding="utf-8", errors="replace") as manifest_file:
                    content_text = manifest_file.read(FRAMEWORK_READ_LIMIT).lower()
            except OSError:
                continue

            # 判定：命中標記且未收過 → 收
            if marker_text.lower() in content_text and framework_name not in frameworks_found:
                frameworks_found.append(framework_name)

        return frameworks_found

    def collect_marker_names(self, root_path, marker_rows):
        """通用標記表偵測（(標記檔名, 名稱)——存在即列入；去重保序）。"""
        names_found = []

        # 逐標記檢查存在性
        for (marker_name, target_name) in marker_rows:
            if (root_path / marker_name).exists():
                if target_name not in names_found:
                    names_found.append(target_name)

        return names_found

    def collect_required_runtimes(self, root_path):
        """偵測專案要求嘅 runtime 版本（版本檔內容——存在即讀第一行）。"""
        required_found = {}

        # 逐版本檔檢查（表序＝輸出序）
        for (version_file_name, runtime_name) in RUNTIME_VERSION_FILES:
            version_path = root_path / version_file_name

            # 判定：版本檔唔存在 → 跳過
            if not version_path.exists():
                continue

            try:
                version_text = version_path.read_text(encoding="utf-8", errors="replace").strip()
            except OSError:
                continue

            # 判定：讀到版本且未收過此 runtime → 收第一行
            if version_text != "" and runtime_name not in required_found:
                required_found[runtime_name] = version_text.splitlines()[0]

        return required_found

    def collect_ci(self, root_path):
        """偵測 CI/CD 標記（存在即列入——表序＝輸出序）。"""
        ci_found = []

        # 逐標記檢查存在性（目錄標記＝is_dir；檔案標記＝exists）
        for (ci_name, relative_path, is_directory) in CI_CHECKS:
            marker_path = root_path / relative_path

            if is_directory:
                # 判定：目錄標記存在 → 收
                if marker_path.is_dir():
                    ci_found.append(ci_name)
            else:
                # 判定：檔案標記存在 → 收
                if marker_path.exists():
                    ci_found.append(ci_name)

        return ci_found

    def collect_manifests(self, root_path):
        """偵測語言／生態 manifest 標記（存在即列入——表序＝輸出序）。"""
        manifests_found = []

        # 逐 manifest 檢查存在性
        for manifest_name in MANIFEST_CHECKS:
            if (root_path / manifest_name).exists():
                manifests_found.append(manifest_name)

        return manifests_found

    def get_project_env(self, project_path):
        # 判定：路徑不存在／唔係目錄 → 失敗（PHP 端據此零寫入）
        root_path = Path(project_path)
        if not root_path.is_dir():
            return {"ok": False, "error": "project path not found: " + str(project_path)}

        resolved_root = root_path.resolve()

        result = {}
        result["ok"] = True
        result["detected_at"] = datetime.datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        result["root"] = str(resolved_root)
        result["name"] = resolved_root.name
        result["vcs"] = self.collect_vcs(resolved_root)
        result["has_agents_md"] = (resolved_root / "AGENTS.md").exists()
        result["languages"] = self.collect_languages(resolved_root)
        result["frameworks"] = self.collect_frameworks(resolved_root)
        result["build_systems"] = self.collect_marker_names(resolved_root, BUILD_SYSTEM_MARKERS)
        result["package_managers"] = self.collect_marker_names(resolved_root, PACKAGE_MANAGER_MARKERS)
        result["test_tools"] = self.collect_marker_names(resolved_root, TEST_TOOL_MARKERS)
        result["linters"] = self.collect_marker_names(resolved_root, LINTER_MARKERS)
        result["required_runtimes"] = self.collect_required_runtimes(resolved_root)
        result["manifests"] = self.collect_manifests(resolved_root)
        result["has_dockerfile"] = (resolved_root / "Dockerfile").exists()
        result["has_docker_compose"] = (
            (resolved_root / "docker-compose.yml").exists()
            or (resolved_root / "docker-compose.yaml").exists()
        )
        result["ci_cd"] = self.collect_ci(resolved_root)

        return result

    def main(self):
        # stdout 強制 UTF-8（Windows 預設 cp950——PHP 側固定收 UTF-8 JSON）
        sys.stdout.reconfigure(encoding="utf-8")

        # 參數：project_path（缺＝用法錯誤——回失敗 JSON）
        if len(sys.argv) < 2:
            print(json.dumps({"ok": False, "error": "usage: project_env <project_path>"}, ensure_ascii=False))
            return

        result = self.get_project_env(sys.argv[1])
        print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    project_env_class().main()
