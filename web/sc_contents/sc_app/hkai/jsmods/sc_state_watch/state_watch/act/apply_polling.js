// jsmods/sc_state_watch/state_watch/act/apply_polling.js —— polling 快照全量寫入（act——開單叫工作）
// 職責：把 polling 快照逐單位寫入（消失單位歸零）；target＝polling、field＝project／chat
// 上游：state_stream polling 事件（seed／delta 都帶完整 current）
// 下游：act/write
// 結構：export const + eager 單例 closure factory（動態 import 用——零 .default）

class _apply_polling {
    constructor() {}

    // 套用 polling（polling＝{project:{slug:數}, chat:{slug:{sid:數}}}——全量寫入）
    async apply_polling(polling_snapshot) {
        const state_watch_module = registry().sc_state_watch();
        const state_watch_domain = state_watch_module.state_watch();
        const write_instance = await state_watch_domain.act().write();
        const store_instance = await state_watch_domain.store();

        const project_counts = polling_snapshot.project || {};
        const chat_counts    = polling_snapshot.chat || {};
        const current_projects = Object.keys(project_counts);
        const current_chats    = [];

        // 舊值＝store 現值（包含頁面初始 hydrate 落 store 嘅數）
        const old_snapshot       = store_instance.snapshot("polling");
        const old_project_counts = old_snapshot.project || {};
        const old_chat_counts    = old_snapshot.chat || {};
        const old_project_slugs  = Object.keys(old_project_counts);

        // 逐個舊專案：今輪唔再出現 → 歸零
        for (let old_index = 0; old_index < old_project_slugs.length; old_index++) {
            const old_project_slug = old_project_slugs[old_index];

            // 判定：今輪仍有 → 跳過
            if (current_projects.indexOf(old_project_slug) >= 0) {
                continue;
            }

            await write_instance.write({ target: "polling", unit: "project", field: old_project_slug, value: 0 });
        }

        // 逐個專案寫當刻數值
        for (let project_index = 0; project_index < current_projects.length; project_index++) {
            const project_slug = current_projects[project_index];

            await write_instance.write({ target: "polling", unit: "project", field: project_slug, value: project_counts[project_slug] });
        }

        // 收集今輪對話單位（以 chat 自己嘅 key 為準——唔靠 project 有冇出現）
        const chat_project_slugs = Object.keys(chat_counts);

        for (let project_index = 0; project_index < chat_project_slugs.length; project_index++) {
            const project_slug = chat_project_slugs[project_index];
            const project_chats = chat_counts[project_slug] || {};
            const project_chat_sids = Object.keys(project_chats);

            for (let chat_index = 0; chat_index < project_chat_sids.length; chat_index++) {
                const chat_sid = project_chat_sids[chat_index];

                current_chats.push(chat_sid);

                await write_instance.write({ target: "polling", unit: "chat", field: chat_sid, value: project_chats[chat_sid] });
            }
        }

        const old_chat_sids = Object.keys(old_chat_counts);

        // 逐個舊對話：今輪唔再出現 → 歸零
        for (let old_index = 0; old_index < old_chat_sids.length; old_index++) {
            const old_chat_sid = old_chat_sids[old_index];

            // 判定：今輪仍有 → 跳過
            if (current_chats.indexOf(old_chat_sid) >= 0) {
                continue;
            }

            await write_instance.write({ target: "polling", unit: "chat", field: old_chat_sid, value: 0 });
        }
    }
}

export const apply_polling = (function (instance) {
    return function () {

        return instance;
    };
})(new _apply_polling());
