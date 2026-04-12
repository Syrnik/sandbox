(($) => {
    "use strict";

    const STORAGE_KEY = "sandbox_editor_state";

    class SandboxEditor {
        constructor(options) {
            options = options || {};
            this.l10n = Object.assign({
                execute_label:      'Выполнить',
                copied:             'Скопировано!',
                no_output:          'Нет вывода',
                dialog_header:      'Сохранить сниппет',
                name_label:         'Название',
                desc_label:         'Описание',
                folder_label:       'Папка',
                no_folder:          '— Без папки —',
                shared_label:       'Доступ для всех',
                save_as_new_label:  'Сохранить как новый',
                save_btn:           'Сохранить',
                cancel:             'Отмена',
                name_required:      'Укажите название сниппета',
                access_shared:      'Общий доступ',
                access_personal:    'Личный',
            }, options.l10n || {});
            this.folders = options.folders || [];
        }

        currentSnippetId = null;
        currentFolderId  = null;
        currentSnippetName = "";
        currentSnippetDesc = "";
        phpEditor = null;
        smartyEditor = null;
        isShared = 0;

        init() {
            this.initEditors();
            this.bindEvents();
            this.restoreFromLocalStorage();
            this.#initAutoSave();

            const urlParams = new URLSearchParams(location.search);
            const snippetId = urlParams.get('snippet_id');
            if (snippetId) {
                this.loadSnippet(parseInt(snippetId, 10));
            }
        }

        initEditors() {
            this.phpEditor = ace.edit("php-editor");
            this.phpEditor.session.setMode("ace/mode/php");
            this.phpEditor.setOptions({ fontSize: "14px", showPrintMargin: false });

            this.smartyEditor = ace.edit("smarty-editor");
            this.smartyEditor.session.setMode("ace/mode/smarty");
            this.smartyEditor.setOptions({ fontSize: "14px", showPrintMargin: false });

            this.applyWaTheme();
            this.#watchWaTheme();
        }

        applyWaTheme() {
            const isDark = document.documentElement.getAttribute("data-theme") !== "light";
            const theme = isDark ? "monokai" : "eclipse";
            this.phpEditor.setTheme("ace/theme/" + theme);
            this.smartyEditor.setTheme("ace/theme/" + theme);
        }

        bindEvents() {
            $("#btn-execute").on("click", () => this.execute());
            $("#btn-save").on("click", () => this.save());
            $("#btn-new").on("click", () => this.newSnippet());
            $("#btn-duplicate").on("click", () => this.duplicate());

            $("#btn-copy-result").on("click", () => {
                const text = $("#result-output").text();
                navigator.clipboard.writeText(text).then(() => {
                    const $btn = $("#btn-copy-result");
                    const orig = $btn.text();
                    $btn.text(this.l10n.copied);
                    setTimeout(() => $btn.text(orig), 1500);
                });
            });

            $("#btn-clear-result").on("click", () => {
                $("#result-output").html("");
                $("#result-errors").hide().html("");
            });

            $("#smarty-toggle").on("change", (e) => {
                const visible = e.target.checked;
                $("#smarty-editor-wrapper").toggle(visible);
                setTimeout(() => {
                    this.phpEditor.resize();
                    if (visible) this.smartyEditor.resize();
                }, 0);
            });

            $("#recent-snippets").on("change", (e) => {
                const id = parseInt($(e.currentTarget).val(), 10);
                if (id) this.loadSnippet(id);
                $(e.currentTarget).val("");
            });
        }

        saveToLocalStorage() {
            try {
                const state = {
                    code_php:       this.phpEditor.getValue(),
                    code_smarty:    this.smartyEditor.getValue(),
                    smarty_visible: $("#smarty-toggle").is(":checked"),
                    environment_id: $("#environment-select").val() ?? "",
                    snippet_id:     this.currentSnippetId,
                    snippet_name:   this.currentSnippetName,
                    snippet_desc:   this.currentSnippetDesc,
                    is_shared:      this.isShared,
                    folder_id:      this.currentFolderId,
                    timestamp:      Date.now(),
                };
                localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
            } catch {
                // localStorage недоступен — молча игнорируем
            }
        }

        restoreFromLocalStorage() {
            const urlParams = new URLSearchParams(location.search);
            if (urlParams.get("snippet_id")) return;

            try {
                const raw = localStorage.getItem(STORAGE_KEY);
                if (!raw) return;

                const state = JSON.parse(raw);

                if (state.code_php)    this.phpEditor.setValue(state.code_php, -1);
                if (state.code_smarty) this.smartyEditor.setValue(state.code_smarty, -1);

                if (state.smarty_visible) {
                    $("#smarty-toggle").prop("checked", true).trigger("change");
                }
                if (state.environment_id) {
                    $("#environment-select").val(state.environment_id);
                }
                if (state.snippet_id) {
                    this.currentSnippetId = state.snippet_id;
                }
                this.currentFolderId = state.folder_id ?? null;
                this.#updateMeta(state.snippet_name || "", state.snippet_desc || "", state.is_shared || 0);
            } catch {
                // Повреждённые данные — молча игнорируем
            }
        }

        clearLocalStorage() {
            try { localStorage.removeItem(STORAGE_KEY); } catch {}
        }

        execute() {
            this.saveToLocalStorage();

            const data = {
                code_php:       this.phpEditor.getValue(),
                code_smarty:    this.smartyEditor.getValue(),
                environment_id: $("#environment-select").val(),
            };

            $("#btn-execute").prop("disabled", true).html('<i class="fas fa-spinner wa-animation-spin speed-1000"></i> ' + this.l10n.execute_label);

            $.ajax({
                url: "?module=backend&action=snippetExecute",
                type: "POST",
                data,
                dataType: "json",
                success: ({ status, data: result }) => {
                    if (status !== "ok") return;
                    $("#result-output").html(result.output || "<em class='hint'>" + this.l10n.no_output + "</em>");
                    if (result.error) {
                        $("#result-errors").html(`<pre>${result.error}</pre>`).show();
                    } else {
                        $("#result-errors").hide().html("");
                    }
                    if (result.execution_time) {
                        $("#execution-time").text(`${result.execution_time}s`);
                    }
                },
                complete: () => {
                    $("#btn-execute").prop("disabled", false).html('<i class="fas fa-play"></i> ' + this.l10n.execute_label);
                },
            });
        }

        save() {
            const hasId = !!this.currentSnippetId;

            const folderOptions = this.#buildFolderOptions(this.folders);
            let folderOptionsHtml = `<option value="">${this.#escHtml(this.l10n.no_folder)}</option>`;
            folderOptions.forEach(f => {
                const prefix = '— '.repeat(f.depth);
                folderOptionsHtml += `<option value="${f.id}">${prefix}${this.#escHtml(f.name)}</option>`;
            });

            const content = `
                <div class="fields vertical">
                    <div class="field">
                        <div class="name">${this.l10n.name_label} <span class="red">*</span></div>
                        <div class="value">
                            <input type="text" id="save-dialog-name" class="full-width">
                        </div>
                    </div>
                    <div class="field">
                        <div class="name">${this.l10n.desc_label}</div>
                        <div class="value">
                            <textarea id="save-dialog-desc" class="full-width" rows="3" style="resize:vertical"></textarea>
                        </div>
                    </div>
                    <div class="field">
                        <div class="name">${this.l10n.folder_label}</div>
                        <div class="value">
                            <div class="wa-select">
                                <select id="save-dialog-folder">${folderOptionsHtml}</select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fields custom-mt-12">
                    <div class="field">
                        <div class="name">${this.l10n.shared_label}</div>
                        <div class="value">
                            <span class="switch" id="save-dialog-shared-switch">
                                <input type="checkbox" id="save-dialog-shared">
                            </span>
                        </div>
                    </div>
                    ${hasId ? `<div class="field">
                        <div class="name">${this.l10n.save_as_new_label}</div>
                        <div class="value">
                            <span class="switch" id="save-dialog-as-new-switch">
                                <input type="checkbox" id="save-dialog-as-new">
                            </span>
                        </div>
                    </div>` : ""}
                </div>`;

            const footer = `<button class="button green js-confirm-save">${this.l10n.save_btn}</button>
                            <a href="#" class="js-dialog-close">${this.l10n.cancel}</a>`;

            $.waDialog({
                header: this.l10n.dialog_header,
                content,
                footer,
                onOpen: ($wrapper) => {
                    $wrapper.find("#save-dialog-name").val(this.currentSnippetName);
                    $wrapper.find("#save-dialog-desc").val(this.currentSnippetDesc);
                    $wrapper.find("#save-dialog-folder").val(this.currentFolderId || "");
                    $wrapper.find("#save-dialog-shared").prop("checked", !!this.isShared);
                    $wrapper.find("#save-dialog-shared-switch").waSwitch();
                    if (hasId) {
                        $wrapper.find("#save-dialog-as-new-switch").waSwitch();
                    }

                    $wrapper.on("click", ".js-confirm-save", () => {
                        const newName = $wrapper.find("#save-dialog-name").val().trim();
                        if (!newName) {
                            alert(this.l10n.name_required);
                            $wrapper.find("#save-dialog-name").focus();
                            return;
                        }

                        const saveAsNew  = hasId && $wrapper.find("#save-dialog-as-new").is(":checked");
                        const newDesc    = $wrapper.find("#save-dialog-desc").val();
                        const newShared  = $wrapper.find("#save-dialog-shared").is(":checked") ? 1 : 0;
                        const newFolder  = $wrapper.find("#save-dialog-folder").val() || "";

                        const data = {
                            name:        newName,
                            description: newDesc,
                            code_php:    this.phpEditor.getValue(),
                            code_smarty: this.smartyEditor.getValue(),
                            is_shared:   newShared,
                            folder_id:   newFolder,
                        };
                        if (!saveAsNew && this.currentSnippetId) {
                            data.id = this.currentSnippetId;
                        }

                        $.ajax({
                            url: "?module=backend&action=snippetSave",
                            type: "POST",
                            data,
                            dataType: "json",
                            success: ({ status, data: snippet }) => {
                                if (status !== "ok") return;
                                this.currentSnippetId = parseInt(snippet.id);
                                this.currentFolderId  = snippet.folder_id ? parseInt(snippet.folder_id) : null;
                                this.#updateMeta(newName, newDesc, newShared);
                                this.saveToLocalStorage();
                                $wrapper.trigger("dialog-close");
                            },
                        });
                    });
                },
            });
        }

        loadSnippet(id) {
            $.ajax({
                url: "?module=backend&action=snippet",
                data: { id },
                cache: true,
                dataType: "json",
                success: ({ status, data: s }) => {
                    if (status !== "ok") return;
                    this.phpEditor.setValue(s.code_php ?? "", -1);
                    this.smartyEditor.setValue(s.code_smarty ?? "", -1);
                    this.currentSnippetId = parseInt(s.id);
                    this.currentFolderId  = s.folder_id ? parseInt(s.folder_id) : null;
                    this.#updateMeta(s.name, s.description ?? "", s.is_shared);
                    if (s.code_smarty) {
                        $("#smarty-toggle").prop("checked", true).trigger("change");
                    }
                    this.saveToLocalStorage();
                },
            });
        }

        newSnippet() {
            this.currentSnippetId = null;
            this.currentFolderId  = null;
            this.phpEditor.setValue("", -1);
            this.smartyEditor.setValue("", -1);
            this.#updateMeta("", "", 0);
            $("#result-output").html("");
            $("#result-errors").hide().html("");
            this.clearLocalStorage();
        }

        duplicate() {
            if (!this.currentSnippetId) return;
            $.ajax({
                url: "?module=backend&action=snippetDuplicate",
                type: "POST",
                data: { id: this.currentSnippetId },
                dataType: "json",
                success: ({ status, data: s }) => {
                    if (status !== "ok") return;
                    this.currentSnippetId = parseInt(s.id);
                    this.currentFolderId  = s.folder_id ? parseInt(s.folder_id) : null;
                    this.#updateMeta(s.name, s.description ?? "", s.is_shared);
                    this.saveToLocalStorage();
                    if ($.wa && $.wa.notice) $.wa.notice("Сниппет продублирован");
                },
            });
        }

        #updateMeta(name, desc, isShared) {
            this.currentSnippetName = name;
            this.currentSnippetDesc = desc;
            this.isShared = isShared ? 1 : 0;
            $("#snippet-name-display").text(name);
            $("#snippet-desc-display").text(desc || "");
            this.#updateAccessIcon();
            this.#updateDuplicateBtn();
        }

        #updateAccessIcon() {
            const $icon = $("#access-icon");
            $icon.html(this.isShared ? '<i class="fas fa-globe text-blue"></i>' : '<i class="fas fa-lock text-yellow"></i>');
            $icon.attr("title", this.isShared ? this.l10n.access_shared : this.l10n.access_personal);
        }

        #updateDuplicateBtn() {
            if (this.currentSnippetId) {
                $("#btn-duplicate").show();
            } else {
                $("#btn-duplicate").hide();
            }
        }

        #buildFolderOptions(folders) {
            const result = [];
            const traverse = (parentId, depth) => {
                folders
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

        #escHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        #watchWaTheme() {
            new MutationObserver(() => this.applyWaTheme()).observe(
                document.documentElement,
                { attributes: true, attributeFilter: ["data-theme"] }
            );
        }

        #initAutoSave() {
            let timer = null;
            const debouncedSave = () => {
                clearTimeout(timer);
                timer = setTimeout(() => this.saveToLocalStorage(), 1000);
            };
            this.phpEditor.on("change", debouncedSave);
            this.smartyEditor.on("change", debouncedSave);
        }
    }

    window.SandboxEditor = SandboxEditor;

})(jQuery);
