(($) => {
    "use strict";

    class SandboxCatalog {
        constructor(options) {
            options = options || {};
            this.l10n = Object.assign({
                dialog_header:       'Создать папку',
                edit_folder_header:  'Редактировать папку',
                name_label:          'Название',
                desc_label:          'Описание',
                shared_label:        'Общий доступ',
                parent_folder_label: 'Родительская папка',
                no_parent:           '— Корень —',
                create_btn:          'Создать',
                save_btn:            'Сохранить',
                cancel:              'Отмена',
                name_required:       'Укажите название',
                select_snippets:     'Выберите сниппеты',
                confirm_delete:      'Удалить выбранные элементы?',
                move_btn:            'Переместить',
                move_header:         'Переместить в папку',
                move_confirm:        'Переместить',
                duplicate_snippet:   'Дублировать',
                copy_to_me:          'Копировать к себе',
                make_private:        'Сделать личным',
                share_btn:           'Сделать общим',
                open_snippet:        'Открыть в редакторе',
                open_folder:         'Открыть',
                edit_folder:         'Редактировать',
                export_btn:          'Скачать JSON',
                delete_btn:          'Удалить',
                confirm_delete_folder_header: 'Удалить папку?',
                confirm_delete_folder_text:   'Будут удалены все вложенные папки и все сниппеты внутри них. Это действие невозможно отменить.',
            }, options.l10n || {});
            this.folders = options.folders || [];
        }

        selectedItems = new Map(); // id => {id, type}

        init() {
            this.bindEvents();
        }

        bindEvents() {
            // Клик по карточке — открыть
            $(document).on("click", ".sandbox-card", (e) => {
                if ($(e.target).closest(".js-kebab, label, a").length) return;
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
            $("#btn-create-folder").on("click", () => this.showFolderDialog());

            // Kebab-меню
            $(document).on("click", ".js-kebab", (e) => {
                e.stopPropagation();
                const $btn = $(e.currentTarget);
                const data = {
                    id:          $btn.data("id"),
                    type:        $btn.data("type"),
                    name:        $btn.data("name") || "",
                    description: $btn.data("description") || "",
                    is_shared:   parseInt($btn.data("shared")) || 0,
                    is_owner:    parseInt($btn.data("owner")) === 1,
                    parent_id:   parseInt($btn.data("parentId")) || null,
                };
                this.showKebabMenu(e, data.type, data.id, data);
            });

            // Скрыть kebab при клике вне (не закрывать при клике на саму кнопку)
            $(document).on("click", (e) => {
                if ($(e.target).closest(".sandbox-kebab-menu, .js-kebab").length) return;
                $(".sandbox-kebab-menu").remove();
            });

            // Bulk actions
            $("#btn-bulk-share").on("click", () => this.bulkShare(1));
            $("#btn-bulk-unshare").on("click", () => this.bulkShare(0));
            $("#btn-bulk-move").on("click", () => this.bulkMove());
            $("#btn-bulk-delete").on("click", () => this.bulkDelete());

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
            const $panel = $("#bulk-panel");
            if (count > 0) {
                $("#selected-count").text(count);
                $panel.addClass("is-visible");
                $(".content:first").css("padding-bottom", $panel.outerHeight() + 16 + "px");
                const hasFolders = [...this.selectedItems.values()].some(({ type }) => type === "folder");
                $("#btn-bulk-move").toggle(!hasFolders);
            } else {
                $panel.removeClass("is-visible");
                $(".content:first").css("padding-bottom", "");
            }
        }

        // ========================
        // Диалог создания / редактирования папки
        // ========================

        showFolderDialog(folderData = null) {
            const isEdit = folderData !== null;
            const currentUrlFolderId = parseInt(new URLSearchParams(location.search).get("folder_id")) || null;

            const excludeId = isEdit ? parseInt(folderData.id) : null;
            const folderOptions = this.#buildFolderOptions(this.folders, excludeId);

            const defaultParentId = isEdit
                ? (parseInt(folderData.parent_id) || "")
                : (currentUrlFolderId || "");

            let parentOptionsHtml = `<option value="">${this.#escHtml(this.l10n.no_parent)}</option>`;
            folderOptions.forEach(f => {
                const prefix = '— '.repeat(f.depth);
                const selected = f.id === parseInt(defaultParentId) ? ' selected' : '';
                parentOptionsHtml += `<option value="${f.id}"${selected}>${prefix}${this.#escHtml(f.name)}</option>`;
            });

            const content = `
                <div class="fields vertical">
                    <div class="field">
                        <div class="name">${this.#escHtml(this.l10n.name_label)} <span class="red">*</span></div>
                        <div class="value">
                            <input type="text" id="folder-name-input" class="full-width"
                                   value="${isEdit ? this.#escHtml(folderData.name) : ''}" autofocus>
                        </div>
                    </div>
                    <div class="field">
                        <div class="name">${this.#escHtml(this.l10n.desc_label)}</div>
                        <div class="value">
                            <input type="text" id="folder-desc-input" class="full-width"
                                   value="${isEdit ? this.#escHtml(folderData.description || '') : ''}">
                        </div>
                    </div>
                    <div class="field">
                        <div class="name">${this.#escHtml(this.l10n.parent_folder_label)}</div>
                        <div class="value">
                            <div class="wa-select">
                                <select id="folder-parent-input">${parentOptionsHtml}</select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fields custom-mt-12">
                    <div class="field">
                        <div class="name">${this.#escHtml(this.l10n.shared_label)}</div>
                        <div class="value">
                            <span class="switch" id="folder-shared-switch">
                                <input type="checkbox" id="folder-shared-input"${isEdit && parseInt(folderData.is_shared) ? ' checked' : ''}>
                            </span>
                        </div>
                    </div>
                </div>`;

            const header     = isEdit ? this.l10n.edit_folder_header : this.l10n.dialog_header;
            const confirmBtn = isEdit ? this.l10n.save_btn : this.l10n.create_btn;

            $.waDialog({
                header,
                content,
                footer: `<button class="button green js-confirm-folder">${this.#escHtml(confirmBtn)}</button>
                         <a href="#" class="js-dialog-close">${this.#escHtml(this.l10n.cancel)}</a>`,
                onOpen: ($dialog) => {
                    $dialog.find("#folder-shared-switch").waSwitch();
                    $dialog.on("click", ".js-confirm-folder", () => {
                        const name = $dialog.find("#folder-name-input").val().trim();
                        if (!name) { alert(this.l10n.name_required); return; }

                        const postData = {
                            name,
                            description: $dialog.find("#folder-desc-input").val(),
                            is_shared:   $dialog.find("#folder-shared-input").is(":checked") ? 1 : 0,
                            parent_id:   $dialog.find("#folder-parent-input").val() || "",
                        };
                        if (isEdit) postData.id = folderData.id;

                        $.ajax({
                            url: "?module=backend&action=folderSave",
                            type: "POST",
                            data: postData,
                            dataType: "json",
                            success: ({ status }) => {
                                if (status === "ok") location.reload();
                            },
                        });
                    });
                },
            });
        }

        // ========================
        // Bulk operations
        // ========================

        bulkShare(isShared) {
            const snippetIds = [];
            const folderIds  = [];
            this.selectedItems.forEach(({ id, type }) => {
                if (type === "snippet") snippetIds.push(id);
                if (type === "folder")  folderIds.push(id);
            });
            if (!snippetIds.length && !folderIds.length) {
                alert(this.l10n.select_snippets);
                return;
            }
            $.ajax({
                url: "?module=backend&action=bulkShare",
                type: "POST",
                data: { snippet_ids: snippetIds, folder_ids: folderIds, is_shared: isShared },
                dataType: "json",
                success: ({ status }) => {
                    if (status === "ok") location.reload();
                },
            });
        }

        bulkMove(overrideIds = null) {
            const snippetIds = overrideIds ? [...overrideIds] : [];
            if (!overrideIds) {
                this.selectedItems.forEach(({ id, type }) => {
                    if (type === "snippet") snippetIds.push(id);
                });
            }
            if (!snippetIds.length) {
                alert(this.l10n.select_snippets);
                return;
            }

            const currentFolderParam = new URLSearchParams(location.search).get("folder_id");
            const currentFolderId = currentFolderParam ? parseInt(currentFolderParam) : null;
            const filteredFolders = currentFolderId
                ? this.folders.filter(f => parseInt(f.id) !== currentFolderId)
                : this.folders;
            const folderOptions = this.#buildFolderOptions(filteredFolders);

            let optionsHtml = `<option value="">${this.#escHtml(this.l10n.no_parent)}</option>`;
            folderOptions.forEach(f => {
                const prefix = '— '.repeat(f.depth);
                optionsHtml += `<option value="${f.id}">${prefix}${this.#escHtml(f.name)}</option>`;
            });

            const content = `
                <div class="fields vertical">
                    <div class="field">
                        <div class="name">${this.#escHtml(this.l10n.parent_folder_label)}</div>
                        <div class="value">
                            <div class="wa-select">
                                <select id="move-folder-select">${optionsHtml}</select>
                            </div>
                        </div>
                    </div>
                </div>`;

            $.waDialog({
                header: this.l10n.move_header,
                content,
                footer: `<button class="button green js-confirm-move">${this.#escHtml(this.l10n.move_confirm)}</button>
                         <a href="#" class="js-dialog-close">${this.#escHtml(this.l10n.cancel)}</a>`,
                onOpen: ($dialog) => {
                    $dialog.on("click", ".js-confirm-move", () => {
                        const folderId = $dialog.find("#move-folder-select").val() || 0;
                        $.ajax({
                            url: "?module=backend&action=snippetMove",
                            type: "POST",
                            data: { ids: snippetIds, folder_id: folderId },
                            dataType: "json",
                            success: ({ status }) => {
                                if (status === "ok") {
                                    $dialog.trigger("dialog-close");
                                    location.reload();
                                }
                            },
                        });
                    });
                },
            });
        }

        bulkDelete() {
            if (!confirm(this.l10n.confirm_delete)) return;

            const snippetIds = [];
            const folderIds  = [];
            this.selectedItems.forEach(({ id, type }) => {
                if (type === "snippet") snippetIds.push(id);
                if (type === "folder")  folderIds.push(id);
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

        // ========================
        // Одиночные операции (вызываются из kebab)
        // ========================

        duplicateSnippet(id) {
            $.ajax({
                url: "?module=backend&action=snippetDuplicate",
                type: "POST",
                data: { id },
                dataType: "json",
                success: ({ status }) => {
                    if (status === "ok") location.reload();
                },
            });
        }

        deleteSnippet(id) {
            if (!confirm(this.l10n.confirm_delete)) return;
            $.ajax({
                url: "?module=backend&action=snippetDelete",
                type: "POST",
                data: { ids: [id] },
                dataType: "json",
                success: ({ status }) => {
                    if (status === "ok") location.reload();
                },
            });
        }

        deleteFolder(id) {
            $.wa.confirm({
                title:                this.l10n.confirm_delete_folder_header,
                text:                 `<p>${this.#escHtml(this.l10n.confirm_delete_folder_text)}</p>`,
                success_button_title: this.l10n.delete_btn,
                success_button_class: 'red',
                cancel_button_title:  this.l10n.cancel,
                onSuccess: () => {
                    $.ajax({
                        url: "?module=backend&action=folderDelete",
                        type: "POST",
                        data: { id },
                        dataType: "json",
                        success: ({ status }) => {
                            if (status === "ok") location.reload();
                        },
                    });
                },
            });
        }

        shareItem(type, id, isShared) {
            const postData = type === "snippet"
                ? { snippet_ids: [id], folder_ids: [], is_shared: isShared }
                : { snippet_ids: [], folder_ids: [id], is_shared: isShared };
            $.ajax({
                url: "?module=backend&action=bulkShare",
                type: "POST",
                data: postData,
                dataType: "json",
                success: ({ status }) => {
                    if (status === "ok") location.reload();
                },
            });
        }

        // ========================
        // Kebab-меню
        // ========================

        showKebabMenu(event, type, id, data) {
            $(".sandbox-kebab-menu").remove();

            const items = [];

            if (type === "snippet") {
                items.push({ label: this.l10n.open_snippet, action: () => { location.href = `?module=backend&snippet_id=${id}`; } });
                items.push({ label: data.is_owner ? this.l10n.duplicate_snippet : this.l10n.copy_to_me, action: () => this.duplicateSnippet(id) });
                if (data.is_owner) {
                    items.push({ label: data.is_shared ? this.l10n.make_private : this.l10n.share_btn, action: () => this.shareItem("snippet", id, data.is_shared ? 0 : 1) });
                    items.push({ label: this.l10n.move_btn, action: () => this.bulkMove([id]) });
                }
                items.push({ label: this.l10n.export_btn, action: () => this.exportItem("snippet", id) });
                if (data.is_owner) {
                    items.push({ separator: true });
                    items.push({ label: this.l10n.delete_btn, danger: true, action: () => this.deleteSnippet(id) });
                }
            } else {
                items.push({ label: this.l10n.open_folder, action: () => { location.href = `?module=backend&action=catalog&folder_id=${id}`; } });
                if (data.is_owner) {
                    items.push({ label: this.l10n.edit_folder, action: () => this.showFolderDialog(data) });
                    items.push({ label: data.is_shared ? this.l10n.make_private : this.l10n.share_btn, action: () => this.shareItem("folder", id, data.is_shared ? 0 : 1) });
                }
                items.push({ label: this.l10n.export_btn, action: () => this.exportItem("folder", id) });
                if (data.is_owner) {
                    items.push({ separator: true });
                    items.push({ label: this.l10n.delete_btn, danger: true, action: () => this.deleteFolder(id) });
                }
            }

            const $menu = $('<div class="sandbox-kebab-menu">');
            items.forEach(item => {
                if (item.separator) {
                    $menu.append('<div class="sandbox-kebab-separator">');
                } else {
                    const $item = $(`<div class="sandbox-kebab-item${item.danger ? ' danger' : ''}">${this.#escHtml(item.label)}</div>`);
                    $item.on("click", (e) => {
                        e.stopPropagation();
                        $(".sandbox-kebab-menu").remove();
                        item.action();
                    });
                    $menu.append($item);
                }
            });

            const rect = event.currentTarget.getBoundingClientRect();
            const menuWidth = 180;
            const left = rect.right + menuWidth > window.innerWidth ? rect.right - menuWidth : rect.left;
            $menu.css({ top: rect.bottom, left });
            $("body").append($menu);
        }

        // ========================
        // Экспорт / Импорт
        // ========================

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

        // ========================
        // Вспомогательные приватные методы
        // ========================

        #buildFolderOptions(folders, excludeId = null) {
            const excluded = excludeId !== null ? this.#getDescendantIds(folders, excludeId) : new Set();
            const filtered = excluded.size
                ? folders.filter(f => !excluded.has(parseInt(f.id)))
                : folders;

            const result = [];
            const traverse = (parentId, depth) => {
                filtered
                    .filter(f => {
                        const fp = (f.parent_id == null || f.parent_id === '') ? null : parseInt(f.parent_id);
                        return fp === parentId;
                    })
                    .forEach(f => {
                        result.push({ id: parseInt(f.id), name: f.name, depth });
                        traverse(parseInt(f.id), depth + 1);
                    });
            };
            traverse(null, 0);
            return result;
        }

        #getDescendantIds(folders, rootId) {
            const ids = new Set([rootId]);
            const traverse = (pid) => {
                folders
                    .filter(f => parseInt(f.parent_id) === pid)
                    .forEach(f => { ids.add(parseInt(f.id)); traverse(parseInt(f.id)); });
            };
            traverse(rootId);
            return ids;
        }

        #escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }
    }

    window.SandboxCatalog = SandboxCatalog;

})(jQuery);
