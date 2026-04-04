(($) => {
    "use strict";

    class SandboxCatalog {
        constructor(options) {
            options = options || {};
            this.l10n = Object.assign({
                dialog_header:   'Создать папку',
                name_label:      'Название',
                desc_label:      'Описание',
                shared_label:    'Общий доступ',
                create_btn:      'Создать',
                cancel:          'Отмена',
                name_required:   'Укажите название',
                select_snippets: 'Выберите сниппеты',
                confirm_delete:  'Удалить выбранные элементы?',
            }, options.l10n || {});
        }

        selectedItems = new Map(); // id => type

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Клик по карточке — открыть
            $(document).on("click", ".sandbox-card", (e) => {
                if ($(e.target).is("input, a, span.js-export-snippet, span.js-export-folder")) return;
                const $card = $(e.currentTarget);
                const type = $card.data("type");
                const id = $card.data("id");
                if (type === "folder") {
                    location.href = `?module=backend&action=catalog&folder_id=${id}`;
                } else {
                    location.href = `?module=backend&snippet_id=${id}`;
                }
            });

            // Чекбоксы
            $(document).on("change", ".js-select-item", (e) => {
                const $cb = $(e.currentTarget);
                const id = $cb.data("id");
                const type = $cb.data("type");
                if ($cb.is(":checked")) {
                    this.selectedItems.set(`${type}_${id}`, { id, type });
                } else {
                    this.selectedItems.delete(`${type}_${id}`);
                }
                this.updateBulkPanel();
            });

            // Создать папку
            $("#btn-create-folder").on("click", () => this.showCreateFolderDialog());

            // Bulk actions
            $("#btn-bulk-share").on("click", () => this.bulkShare());
            $("#btn-bulk-delete").on("click", () => this.bulkDelete());

            // Экспорт сниппета
            $(document).on("click", ".js-export-snippet", (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data("id");
                this.exportItem("snippet", id);
            });

            // Экспорт папки
            $(document).on("click", ".js-export-folder", (e) => {
                e.stopPropagation();
                const id = $(e.currentTarget).data("id");
                this.exportItem("folder", id);
            });

            // Импорт
            $("#import-file").on("change", (e) => {
                const file = e.target.files[0];
                if (!file) return;
                const reader = new FileReader();
                reader.onload = (ev) => this.importData(ev.target.result);
                reader.readAsText(file);
                $(e.target).val("");
            });
        }

        updateBulkPanel() {
            const count = this.selectedItems.size;
            if (count > 0) {
                $("#selected-count").text(count);
                $("#bulk-panel").show();
            } else {
                $("#bulk-panel").hide();
            }
        }

        showCreateFolderDialog() {
            const parentId = new URLSearchParams(location.search).get("folder_id") || "";
            const html = `
                <div class="dialog-content-padding">
                    <div class="field">
                        <div class="name">${this.l10n.name_label} <span class="red">*</span></div>
                        <div class="value"><input type="text" id="folder-name-input" class="full-width" autofocus></div>
                    </div>
                    <div class="field">
                        <div class="name">${this.l10n.desc_label}</div>
                        <div class="value"><input type="text" id="folder-desc-input" class="full-width"></div>
                    </div>
                    <div class="field">
                        <label><input type="checkbox" id="folder-shared-input"> ${this.l10n.shared_label}</label>
                    </div>
                </div>
            `;
            $.waDialog({
                html,
                header: this.l10n.dialog_header,
                buttons: `<button class="button green js-confirm-create-folder">${this.l10n.create_btn}</button>
                          <a href="#" class="js-wa-dialog-close">${this.l10n.cancel}</a>`,
                onOpen: ($dialog) => {
                    $dialog.on("click", ".js-confirm-create-folder", () => {
                        const name = $("#folder-name-input").val().trim();
                        if (!name) { alert(this.l10n.name_required); return; }
                        $.ajax({
                            url: "?module=backend&action=folderSave",
                            type: "POST",
                            data: {
                                name,
                                description: $("#folder-desc-input").val(),
                                is_shared: $("#folder-shared-input").is(":checked") ? 1 : 0,
                                parent_id: parentId || "",
                            },
                            dataType: "json",
                            success: ({ status }) => {
                                if (status === "ok") location.reload();
                            },
                        });
                    });
                },
            });
        }

        bulkShare() {
            const snippetIds = [];
            this.selectedItems.forEach(({ id, type }) => {
                if (type === "snippet") snippetIds.push(id);
            });
            if (!snippetIds.length) { alert(this.l10n.select_snippets); return; }

            // Share each snippet by saving with is_shared=1
            const promises = snippetIds.map(id =>
                $.ajax({
                    url: "?module=backend&action=snippetSave",
                    type: "POST",
                    data: { id, is_shared: 1, name: "_placeholder_" },
                    dataType: "json",
                })
            );
            $.when(...promises).then(() => location.reload());
        }

        bulkDelete() {
            if (!confirm(this.l10n.confirm_delete)) return;

            const snippetIds = [];
            const folderIds = [];
            this.selectedItems.forEach(({ id, type }) => {
                if (type === "snippet") snippetIds.push(id);
                if (type === "folder") folderIds.push(id);
            });

            const requests = [];
            if (snippetIds.length) {
                requests.push($.ajax({
                    url: "?module=backend&action=snippetDelete",
                    type: "POST",
                    data: { ids: snippetIds },
                    dataType: "json",
                }));
            }
            folderIds.forEach(id => {
                requests.push($.ajax({
                    url: "?module=backend&action=folderDelete",
                    type: "POST",
                    data: { id },
                    dataType: "json",
                }));
            });

            if (requests.length) {
                $.when(...requests).then(() => location.reload());
            }
        }

        exportItem(type, id) {
            $.ajax({
                url: "?module=backend&action=export",
                data: { type, id },
                cache: true,
                dataType: "json",
                success: ({ status, data }) => {
                    if (status !== "ok") return;
                    const blob = new Blob([JSON.stringify(data, null, 2)], { type: "application/json" });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement("a");
                    a.href = url;
                    a.download = `sandbox_${type}_${id}.json`;
                    a.click();
                    URL.revokeObjectURL(url);
                },
            });
        }

        importData(jsonText) {
            $.ajax({
                url: "?module=backend&action=import",
                type: "POST",
                data: { data: jsonText },
                dataType: "json",
                success: ({ status }) => {
                    if (status === "ok") {
                        $.wa.notice("Импорт выполнен");
                        location.reload();
                    }
                },
            });
        }
    }

    window.SandboxCatalog = SandboxCatalog;

})(jQuery);
