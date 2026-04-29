@php
    $editorId = $editorId ?? ('editor-' . \Illuminate\Support\Str::random(6));
    $name = $name ?? 'content_html';
    $value = $value ?? '';
    $placeholder = $placeholder ?? 'Digite aqui...';
    $minHeight = $minHeight ?? '260px';
    $variableDefinitions = $variableDefinitions ?? [];
    $showVariablePicker = $showVariablePicker ?? false;
    $toolbarButton = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/[0.06]';
    $toolbarSelect = 'h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200';
    $toolbarInput = 'h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-700 focus:outline-none dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200';
    $variableGroups = collect($variableDefinitions)
        ->map(fn ($variable) => [
            'key' => is_array($variable) ? ($variable['group'] ?? 'sistema') : ($variable->group ?? 'sistema'),
            'label' => is_array($variable) ? ($variable['group_label'] ?? 'Sistema') : ($variable->group_label ?? 'Sistema'),
        ])
        ->unique('key')
        ->values();
@endphp

<div class="space-y-3" data-rich-editor-wrapper>
    <div class="flex flex-wrap items-center gap-2">
        <select class="{{ $toolbarSelect }}" data-editor-font-family data-editor-target="{{ $editorId }}" title="Tipo de fonte">
            <option value="">Fonte</option>
            <option value="Arial, Helvetica, sans-serif">Arial</option>
            <option value="Georgia, serif">Georgia</option>
            <option value="Tahoma, sans-serif">Tahoma</option>
            <option value="'Times New Roman', serif">Times New Roman</option>
            <option value="Verdana, sans-serif">Verdana</option>
        </select>

        <select class="{{ $toolbarSelect }}" data-editor-font-size data-editor-target="{{ $editorId }}" title="Tamanho da fonte">
            <option value="">Tamanho</option>
            @foreach([7, 8, 9, 10, 11, 12, 14, 16, 18, 20, 24] as $sizeOption)
                <option value="{{ $sizeOption }}">{{ $sizeOption }}</option>
            @endforeach
        </select>

        <input type="number" min="1" step="0.5" value="10" class="{{ $toolbarInput }} w-20" data-editor-font-size-custom data-editor-target="{{ $editorId }}" title="Digite o tamanho da fonte">
        <button type="button" class="{{ $toolbarButton }}" data-editor-font-size-apply data-editor-target="{{ $editorId }}" title="Aplicar tamanho digitado">pt</button>

        <label class="{{ $toolbarButton }} cursor-pointer px-2" title="Cor da fonte" data-editor-color-trigger data-editor-target="{{ $editorId }}">
            <i class="fa-solid fa-palette"></i>
            <input type="color" value="#1f2937" class="sr-only" data-editor-color data-editor-target="{{ $editorId }}">
        </label>

        <button type="button" class="{{ $toolbarButton }}" data-editor-command="bold" data-editor-target="{{ $editorId }}" title="Negrito"><i class="fa-solid fa-bold"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="italic" data-editor-target="{{ $editorId }}" title="Italico"><i class="fa-solid fa-italic"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="underline" data-editor-target="{{ $editorId }}" title="Sublinhado"><i class="fa-solid fa-underline"></i></button>

        <button type="button" class="{{ $toolbarButton }}" data-editor-command="formatBlock" data-editor-value="h2" data-editor-target="{{ $editorId }}" title="Titulo H2">H2</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="formatBlock" data-editor-value="h3" data-editor-target="{{ $editorId }}" title="Titulo H3">H3</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="insertParagraph" data-editor-target="{{ $editorId }}" title="Paragrafo">P</button>

        <button type="button" class="{{ $toolbarButton }}" data-editor-command="insertUnorderedList" data-editor-target="{{ $editorId }}" title="Lista com marcadores"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="insertOrderedList" data-editor-target="{{ $editorId }}" title="Lista numerada"><i class="fa-solid fa-list-ol"></i></button>

        <button type="button" class="{{ $toolbarButton }}" data-editor-command="justifyLeft" data-editor-target="{{ $editorId }}" title="Alinhar a esquerda"><i class="fa-solid fa-align-left"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="justifyCenter" data-editor-target="{{ $editorId }}" title="Centralizar"><i class="fa-solid fa-align-center"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="justifyRight" data-editor-target="{{ $editorId }}" title="Alinhar a direita"><i class="fa-solid fa-align-right"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="justifyFull" data-editor-target="{{ $editorId }}" title="Justificar"><i class="fa-solid fa-align-justify"></i></button>

        <button type="button" class="{{ $toolbarButton }}" data-editor-table data-editor-target="{{ $editorId }}" title="Inserir tabela"><i class="fa-solid fa-table"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-rule data-editor-target="{{ $editorId }}" title="Inserir linha horizontal"><i class="fa-solid fa-minus"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-image data-editor-target="{{ $editorId }}" title="Inserir imagem por URL ou caminho publico"><i class="fa-solid fa-image"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-icon data-editor-target="{{ $editorId }}" title="Inserir icone do Font Awesome"><i class="fa-solid fa-icons"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-token="{{ '{' . '{numero_pagina}' . '}' }}" data-editor-target="{{ $editorId }}" title="Inserir numero da pagina atual">#</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-token="{{ '{' . '{total_paginas}' . '}' }}" data-editor-target="{{ $editorId }}" title="Inserir total de paginas">##</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-html='<div class="page-break"></div><p></p>' data-editor-target="{{ $editorId }}" title="Inserir quebra de pagina"><i class="fa-solid fa-file-lines"></i></button>
    </div>

    @if($showVariablePicker)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-white/[0.03]">
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Variaveis disponiveis</div>
            <div class="mb-4 flex flex-wrap gap-2" data-variable-filter-group>
                <button type="button" class="rounded-full border border-brand-300 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200" data-variable-filter="all">Todos</button>
                @foreach($variableGroups as $group)
                    <button type="button" class="rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200" data-variable-filter="{{ $group['key'] }}">{{ $group['label'] }}</button>
                @endforeach
            </div>
            <div class="flex flex-wrap gap-2" data-variable-filter-container>
                @foreach($variableDefinitions as $variable)
                    @php
                        $variableKey = is_array($variable) ? ($variable['key'] ?? '') : ($variable->key ?? '');
                        $variableDescription = is_array($variable) ? ($variable['description'] ?? '') : ($variable->description ?? '');
                        $variableGroup = is_array($variable) ? ($variable['group'] ?? 'sistema') : ($variable->group ?? 'sistema');
                        $variableToken = '{' . '{' . $variableKey . '}' . '}';
                    @endphp
                    <button
                        type="button"
                        class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200"
                        data-editor-variable="{{ $variableToken }}"
                        data-editor-target="{{ $editorId }}"
                        data-variable-group="{{ $variableGroup }}"
                        title="{{ $variableDescription }}"
                    >
                        {{ $variableToken }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <div
        id="{{ $editorId }}"
        contenteditable="true"
        data-rich-editor="{{ $editorId }}"
        data-rich-editor-placeholder="{{ $placeholder }}"
        class="min-h-[220px] w-full rounded-2xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
        style="min-height: {{ $minHeight }};"
    >{!! $value !!}</div>

    <textarea name="{{ $name }}" id="{{ $editorId }}_textarea" class="hidden" data-rich-editor-input="{{ $editorId }}">{{ $value }}</textarea>
</div>

@once
    <dialog id="rich-editor-table-dialog" class="fixed inset-0 m-auto w-full max-w-md rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <div class="p-6 space-y-4" data-rich-editor-table-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Inserir tabela</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Defina o tamanho e a espessura das linhas.</p>
                </div>
                <button type="button" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-rich-editor-close-dialog="#rich-editor-table-dialog">Fechar</button>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Linhas</label>
                    <input type="number" min="1" value="2" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" name="rows">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Colunas</label>
                    <input type="number" min="1" value="2" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" name="cols">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Espessura da linha</label>
                    <input type="number" min="0" step="0.5" value="1" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" name="border_width">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cor da linha</label>
                    <input type="color" value="#d1d5db" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-2 dark:border-gray-700 dark:bg-gray-900" name="border_color">
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-rich-editor-close-dialog="#rich-editor-table-dialog">Cancelar</button>
                <button type="button" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white" data-rich-editor-table-submit>Inserir</button>
            </div>
        </div>
    </dialog>

    <dialog id="rich-editor-rule-dialog" class="fixed inset-0 m-auto w-full max-w-md rounded-3xl border border-gray-200 bg-white p-0 shadow-2xl backdrop:bg-black/60 dark:border-gray-700 dark:bg-gray-900">
        <div class="p-6 space-y-4" data-rich-editor-rule-panel>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Inserir linha horizontal</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Escolha a espessura e a cor da linha.</p>
                </div>
                <button type="button" class="rounded-full border border-gray-200 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-rich-editor-close-dialog="#rich-editor-rule-dialog">Fechar</button>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Espessura</label>
                    <input type="number" min="0" step="0.5" value="1" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-900 dark:text-white" name="thickness">
                </div>
                <div>
                    <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cor</label>
                    <input type="color" value="#941415" class="h-11 w-full rounded-xl border border-gray-300 bg-white px-2 dark:border-gray-700 dark:bg-gray-900" name="color">
                </div>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" class="rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-700 dark:text-gray-200" data-rich-editor-close-dialog="#rich-editor-rule-dialog">Cancelar</button>
                <button type="button" class="rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white" data-rich-editor-rule-submit>Inserir</button>
            </div>
        </div>
    </dialog>

    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const escapeHtmlAttribute = (value) => String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/"/g, '&quot;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
            const selectionRanges = new Map();

            const syncEditor = (editorId) => {
                const editor = document.querySelector(`[data-rich-editor="${editorId}"]`);
                const input = document.querySelector(`[data-rich-editor-input="${editorId}"]`);
                if (!editor || !input) {
                    return;
                }

                input.value = editor.innerHTML.replace(/\u200B/g, '').trim();
            };

            const rememberSelection = (editorId) => {
                const editor = document.querySelector(`[data-rich-editor="${editorId}"]`);
                const range = editor ? getEditorSelectionRange(editor) : null;
                if (!editor || !range) {
                    return;
                }

                selectionRanges.set(editorId, range.cloneRange());
            };

            const restoreSelection = (editorId) => {
                const editor = document.querySelector(`[data-rich-editor="${editorId}"]`);
                const range = selectionRanges.get(editorId);
                if (!editor || !range) {
                    return;
                }

                const selection = window.getSelection();
                selection.removeAllRanges();
                selection.addRange(range.cloneRange());
            };

            const focusEditor = (editorId) => {
                const editor = document.querySelector(`[data-rich-editor="${editorId}"]`);
                if (!editor) {
                    return null;
                }

                editor.focus();
                restoreSelection(editorId);
                return editor;
            };

            const getEditorSelectionRange = (editor) => {
                const selection = window.getSelection();
                if (!selection || selection.rangeCount === 0) {
                    return null;
                }

                const range = selection.getRangeAt(0);
                return editor.contains(range.commonAncestorContainer) ? range : null;
            };

            const applyInlineStyle = (editorId, styles) => {
                const editor = focusEditor(editorId);
                if (!editor) {
                    return;
                }

                const range = getEditorSelectionRange(editor);
                if (!range) {
                    return;
                }

                const styleString = Object.entries(styles)
                    .filter(([, value]) => String(value || '').trim() !== '')
                    .map(([key, value]) => `${key}: ${value}`)
                    .join('; ');

                if (!styleString) {
                    return;
                }

                const selection = window.getSelection();

                if (range.collapsed) {
                    const span = document.createElement('span');
                    span.setAttribute('style', styleString);
                    const textNode = document.createTextNode('\u200B');
                    span.appendChild(textNode);
                    range.insertNode(span);

                    const nextRange = document.createRange();
                    nextRange.setStart(textNode, textNode.length);
                    nextRange.collapse(true);
                    selection.removeAllRanges();
                    selection.addRange(nextRange);
                } else {
                    const contents = range.extractContents();
                    const span = document.createElement('span');
                    span.setAttribute('style', styleString);
                    span.appendChild(contents);
                    range.insertNode(span);

                    const nextRange = document.createRange();
                    nextRange.selectNodeContents(span);
                    nextRange.collapse(false);
                    selection.removeAllRanges();
                    selection.addRange(nextRange);
                }

                syncEditor(editorId);
            };

            const applyFontSize = (editorId, size) => {
                const normalized = String(size || '').replace(',', '.').trim();
                if (!normalized || Number(normalized) <= 0) {
                    return;
                }

                applyInlineStyle(editorId, {
                    'font-size': `${normalized}pt`,
                });
            };

            const insertHtml = (editorId, html) => {
                const editor = focusEditor(editorId);
                if (!editor || !html) {
                    return;
                }

                document.execCommand('insertHTML', false, html);
                syncEditor(editorId);
            };

            const buildTableHtml = (rows, cols, borderWidth, borderColor) => {
                const width = Math.max(0, Number(borderWidth || 0));
                const borderStyle = width > 0 ? `${width}px solid ${borderColor}` : '0';
                let html = '<table style="width:100%; border-collapse:collapse;">';

                for (let row = 0; row < rows; row++) {
                    html += '<tr>';
                    for (let col = 0; col < cols; col++) {
                        html += `<td style="border:${borderStyle}; padding:8px;">&nbsp;</td>`;
                    }
                    html += '</tr>';
                }

                html += '</table><p></p>';
                return html;
            };

            const buildRuleHtml = (thickness, color) => {
                const value = Math.max(0, Number(thickness || 0));
                return `<hr style="border:0; border-top:${value}px solid ${escapeHtmlAttribute(color || '#941415')};">`;
            };

            document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
                const editorId = editor.getAttribute('data-rich-editor');
                ['input', 'keyup', 'mouseup', 'focus', 'click'].forEach((eventName) => {
                    editor.addEventListener(eventName, () => {
                        rememberSelection(editorId);
                        syncEditor(editorId);
                    });
                });
                syncEditor(editorId);
            });

            document.querySelectorAll('[data-editor-command]').forEach((control) => {
                control.addEventListener('mousedown', () => {
                    const target = control.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                const handler = () => {
                    const target = control.getAttribute('data-editor-target');
                    const command = control.getAttribute('data-editor-command');
                    const value = control.getAttribute('data-editor-value') ?? (control.tagName === 'SELECT' ? control.value : null);
                    const editor = focusEditor(target);
                    if (!editor || !command) {
                        return;
                    }

                    if (control.tagName === 'SELECT' && !value) {
                        return;
                    }

                    document.execCommand(command, false, value || null);
                    syncEditor(target);
                };

                const eventName = control.tagName === 'SELECT' ? 'change' : 'click';
                control.addEventListener(eventName, handler);
            });

            document.querySelectorAll('[data-editor-font-family]').forEach((control) => {
                control.addEventListener('mousedown', () => {
                    const target = control.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                control.addEventListener('change', () => {
                    const value = control.value;
                    const target = control.getAttribute('data-editor-target');
                    if (!value || !target) {
                        return;
                    }

                    applyInlineStyle(target, {
                        'font-family': value,
                    });
                    control.value = '';
                });
            });

            document.querySelectorAll('[data-editor-font-size]').forEach((control) => {
                control.addEventListener('mousedown', () => {
                    const target = control.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                control.addEventListener('change', () => {
                    const target = control.getAttribute('data-editor-target');
                    if (!target || !control.value) {
                        return;
                    }

                    applyFontSize(target, control.value);
                    control.value = '';
                });
            });

            document.querySelectorAll('[data-editor-font-size-apply]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const input = document.querySelector(`[data-editor-font-size-custom][data-editor-target="${target}"]`);
                    if (!target || !input) {
                        return;
                    }

                    applyFontSize(target, input.value);
                });
            });

            document.querySelectorAll('[data-editor-font-size-custom]').forEach((input) => {
                input.addEventListener('mousedown', () => {
                    const target = input.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });
            });

            document.querySelectorAll('[data-editor-color-trigger]').forEach((trigger) => {
                trigger.addEventListener('mousedown', () => {
                    const target = trigger.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });
            });

            document.querySelectorAll('[data-editor-color]').forEach((input) => {
                input.addEventListener('mousedown', () => {
                    const target = input.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                input.addEventListener('change', () => {
                    const target = input.getAttribute('data-editor-target');
                    if (!target || !input.value) {
                        return;
                    }

                    applyInlineStyle(target, {
                        color: input.value,
                    });
                });
            });

            document.querySelectorAll('[data-editor-variable]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const content = button.getAttribute('data-editor-variable');
                    if (!target || !content) {
                        return;
                    }

                    focusEditor(target);
                    document.execCommand('insertText', false, content);
                    syncEditor(target);
                });
            });

            document.querySelectorAll('[data-editor-token]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const content = button.getAttribute('data-editor-token');
                    if (!target || !content) {
                        return;
                    }

                    focusEditor(target);
                    document.execCommand('insertText', false, content);
                    syncEditor(target);
                });
            });

            document.querySelectorAll('[data-editor-html]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    insertHtml(button.getAttribute('data-editor-target'), button.getAttribute('data-editor-html'));
                });
            });

            document.querySelectorAll('[data-editor-image]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const editor = focusEditor(target);
                    if (!editor) {
                        return;
                    }

                    const src = window.prompt('Informe a URL completa da imagem ou o caminho publico (ex.: /uploads/arquivo.png):');
                    if (!src) {
                        return;
                    }

                    const alt = window.prompt('Texto alternativo da imagem (opcional):') || '';
                    insertHtml(target, `<img src="${escapeHtmlAttribute(src)}" alt="${escapeHtmlAttribute(alt)}" style="max-width:100%; height:auto;">`);
                });
            });

            document.querySelectorAll('[data-editor-icon]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const editor = focusEditor(target);
                    if (!editor) {
                        return;
                    }

                    const iconClass = window.prompt('Informe a classe do Font Awesome (ex.: fa-solid fa-phone):');
                    if (!iconClass) {
                        return;
                    }

                    insertHtml(target, `<i class="${escapeHtmlAttribute(iconClass)}"></i>&nbsp;`);
                });
            });

            const tableDialog = document.querySelector('#rich-editor-table-dialog');
            const ruleDialog = document.querySelector('#rich-editor-rule-dialog');

            document.querySelectorAll('[data-editor-table]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (!tableDialog || !target) {
                        return;
                    }

                    rememberSelection(target);
                    tableDialog.dataset.editorTarget = target;
                    tableDialog.showModal();
                });
            });

            document.querySelectorAll('[data-editor-rule]').forEach((button) => {
                button.addEventListener('mousedown', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (target) {
                        rememberSelection(target);
                    }
                });

                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    if (!ruleDialog || !target) {
                        return;
                    }

                    rememberSelection(target);
                    ruleDialog.dataset.editorTarget = target;
                    ruleDialog.showModal();
                });
            });

            document.querySelectorAll('[data-rich-editor-close-dialog]').forEach((button) => {
                button.addEventListener('click', () => {
                    const selector = button.getAttribute('data-rich-editor-close-dialog');
                    const dialog = selector ? document.querySelector(selector) : null;
                    dialog?.close();
                });
            });

            document.querySelector('[data-rich-editor-table-submit]')?.addEventListener('click', () => {
                const dialog = tableDialog;
                const target = dialog?.dataset.editorTarget;
                if (!dialog || !target) {
                    return;
                }

                const panel = dialog.querySelector('[data-rich-editor-table-panel]');
                const rows = Math.max(1, Number(panel?.querySelector('[name="rows"]')?.value || 1));
                const cols = Math.max(1, Number(panel?.querySelector('[name="cols"]')?.value || 1));
                const borderWidth = Number(panel?.querySelector('[name="border_width"]')?.value || 0);
                const borderColor = String(panel?.querySelector('[name="border_color"]')?.value || '#d1d5db');

                insertHtml(target, buildTableHtml(rows, cols, borderWidth, borderColor));
                dialog.close();
            });

            document.querySelector('[data-rich-editor-rule-submit]')?.addEventListener('click', () => {
                const dialog = ruleDialog;
                const target = dialog?.dataset.editorTarget;
                if (!dialog || !target) {
                    return;
                }

                const panel = dialog.querySelector('[data-rich-editor-rule-panel]');
                const thickness = Number(panel?.querySelector('[name="thickness"]')?.value || 1);
                const color = String(panel?.querySelector('[name="color"]')?.value || '#941415');

                insertHtml(target, buildRuleHtml(thickness, color));
                dialog.close();
            });

            document.querySelectorAll('[data-variable-filter-group]').forEach((group) => {
                group.addEventListener('click', (event) => {
                    const button = event.target.closest('[data-variable-filter]');
                    if (!button) {
                        return;
                    }

                    const wrapper = group.closest('[data-rich-editor-wrapper]');
                    const container = wrapper?.querySelector('[data-variable-filter-container]');
                    const filter = button.getAttribute('data-variable-filter') || 'all';
                    if (!container) {
                        return;
                    }

                    group.querySelectorAll('[data-variable-filter]').forEach((item) => {
                        item.classList.remove('border-brand-300', 'bg-brand-50', 'text-brand-700', 'dark:border-brand-800', 'dark:bg-brand-500/10', 'dark:text-brand-200');
                        item.classList.add('border-gray-200', 'bg-white', 'text-gray-700', 'dark:border-gray-700', 'dark:bg-gray-900', 'dark:text-gray-200');
                    });

                    button.classList.remove('border-gray-200', 'bg-white', 'text-gray-700', 'dark:border-gray-700', 'dark:bg-gray-900', 'dark:text-gray-200');
                    button.classList.add('border-brand-300', 'bg-brand-50', 'text-brand-700', 'dark:border-brand-800', 'dark:bg-brand-500/10', 'dark:text-brand-200');

                    container.querySelectorAll('[data-variable-group]').forEach((item) => {
                        const matches = filter === 'all' || item.getAttribute('data-variable-group') === filter;
                        item.classList.toggle('hidden', !matches);
                    });
                });
            });
        });
        </script>
    @endpush
@endonce
