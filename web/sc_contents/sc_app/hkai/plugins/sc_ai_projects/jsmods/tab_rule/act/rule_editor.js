// jsmods/tab_rule/act/rule_editor.js —— 規則頁編輯器（action 層——本機側）
// 職責：rule_editor（掛 editor 控制器——專案 modal 語境；每次 modal render 後重掛＋讀現值）
// 結構同 callback/ 家族：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _rule_editor {
    constructor() {
        this.editor_controller = null;
    }

    // 編輯器控制器（懶建——本模組唯一實例；語境＝project modal）
    async get_editor_controller() {
        // 判定：未建 → 建（registry 取 sc_editor 服務）
        if (!this.editor_controller) {
            const editor_service = await registry().sc_editor();
            this.editor_controller = editor_service.create_editor("[data-modal='project_modal']", "sc_ai_projects_tab_rule");
        }

        return this.editor_controller;
    }

    // 掛載規則編輯器（modal render 完、規則 pane 在場先呼叫——頁內無 mount 節點＝唔係規則頁、自動略過）
    async mount_rule_editor(j_modal) {
        const editor_controller = await this.get_editor_controller();

        editor_controller.mount_editor(j_modal);
    }

    // 讀規則現值（儲存前取值——交 req 出街）
    async read_rule_content() {
        const editor_controller = await this.get_editor_controller();

        return editor_controller.read_editor_content();
    }
}

export const rule_editor = (function (instance) {
    return function () {

        return instance;
    };
})(new _rule_editor());
