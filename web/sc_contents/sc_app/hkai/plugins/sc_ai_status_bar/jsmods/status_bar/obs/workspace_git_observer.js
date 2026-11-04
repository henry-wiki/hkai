// jsmods/status_bar/obs/workspace_git_observer.js —— workspace 線 git 事件 → 狀態列 sb_git（接駁）
// 職責：向 workspace_stream 登記 "git" 事件；收到即交 act git_status_item.apply_git_entry()
// 上游：sc_ai_status_bar.js（域啟動時 init）
// 下游：registry().sc_stream().workspace().subscribe()、status_bar act git_status_item

class _workspace_git_observer {
    constructor() {
        // 取消登記函數（未登記＝null）
        this.unsubscribe = null;
    }

    // 登記（重複呼叫只登記一次）
    async init() {
        // 判定：已登記 → 直回
        if (this.unsubscribe !== null) {
            return this;
        }

        const workspace_stream_instance = await registry().sc_stream().workspace();

        this.unsubscribe = workspace_stream_instance.subscribe("git", this.apply_git_event.bind(this));

        return this;
    }

    // 收到 git 事件（{project_slug, entry}）→ 交狀態列
    async apply_git_event(event_payload) {
        const status_bar_domain = await registry().sc_ai_status_bar().status_bar();
        const git_status_item_instance = await status_bar_domain.act().git_status_item();

        await git_status_item_instance.apply_git_entry(event_payload.entry || {});
    }
}

export const workspace_git_observer = (function (instance) {
    return function () {

        return instance;
    };
})(new _workspace_git_observer());
