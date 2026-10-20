// jsmods/_common/_domain_hooks.js —— 域共用 hook 機制（register／before／after——不變）
// 契約：各域用 Object.assign(<域>.prototype, …) 掛上；state＝域實例嘅 this.hooks（域 ctor 自建）

class _domain_hooks {
    constructor() {}

    // 動態註冊單一 hook（外部用家自我註冊——同一勾點可分多個訂閱；external_hook ＝ {before, after} 相位物件）
    register_hook(name, external_hook) {
        // 判定：未有此勾點 → 開新（容許 catalog 未宣告嘅勾點）
        if (!this.hooks[name]) {
            this.hooks[name] = [];
        }

        this.hooks[name].push(external_hook);
    }

    // 派發單一 hook（before 相位——依 name 查訂閱；hook_data 原樣傳落每個訂閱；未註冊者＝跳過）
    execute_hook_before(name, hook_data) {
        const matched_hooks = this.hooks[name];

        // 判定：未有註冊者 → 跳過
        if (!matched_hooks) {
            return;
        }

        // 逐個訂閱派發（有 before 函數先執行）
        for (let hook_index = 0; hook_index < matched_hooks.length; hook_index++) {
            const matched_hook = matched_hooks[hook_index];

            // 判定：有 before 函數 → 執行（帶 hook_data）
            if (typeof matched_hook["before"] === "function") {
                matched_hook["before"].apply(this, [hook_data]);
            }
        }
    }

    // 派發單一 hook（after 相位——同上）
    execute_hook_after(name, hook_data) {
        const matched_hooks = this.hooks[name];

        // 判定：未有註冊者 → 跳過
        if (!matched_hooks) {
            return;
        }

        // 逐個訂閱派發（有 after 函數先執行）
        for (let hook_index = 0; hook_index < matched_hooks.length; hook_index++) {
            const matched_hook = matched_hooks[hook_index];

            // 判定：有 after 函數 → 執行（帶 hook_data）
            if (typeof matched_hook["after"] === "function") {
                matched_hook["after"].apply(this, [hook_data]);
            }
        }
    }
}

export { _domain_hooks };
