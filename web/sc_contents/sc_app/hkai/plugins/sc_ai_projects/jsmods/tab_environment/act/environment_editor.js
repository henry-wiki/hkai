// jsmods/tab_environment/act/environment_editor.js —— 環境頁檢視器（action 層——本機側）
// 職責：environment_editor（掛 editor 控制器——專案 modal 語境；唯讀檢視——無工具列、無儲存）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _environment_editor {
    constructor() {
        this.editor_controller = null;
    }

    // 檢視器控制器（懶建——本模組唯一實例；語境＝project modal）
    async get_editor_controller() {
        // 判定：未建 → 建（registry 取 sc_editor 服務）
        if (!this.editor_controller) {
            const editor_service = await registry().sc_editor();
            this.editor_controller = editor_service.create_editor("[data-modal='project_modal']", "sc_ai_projects_tab_environment");
        }

        return this.editor_controller;
    }

    // 掛載環境檢視器（modal render 完、環境 pane 在場先呼叫——頁內無 mount 節點＝唔係環境頁、自動略過）
    async mount_environment_editor(j_modal) {
        const editor_controller = await this.get_editor_controller();

        editor_controller.mount_editor(j_modal);
    }
}

export const environment_editor = (function (instance) {
    return function () {

        return instance;
    };
})(new _environment_editor());
