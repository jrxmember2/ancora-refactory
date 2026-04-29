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
        <select class="{{ $toolbarSelect }}" data-editor-command="fontName" data-editor-target="{{ $editorId }}" title="Tipo de fonte">
            <option value="">Fonte</option>
            <option value="Arial">Arial</option>
            <option value="Georgia">Georgia</option>
            <option value="Tahoma">Tahoma</option>
            <option value="Times New Roman">Times New Roman</option>
            <option value="Verdana">Verdana</option>
        </select>
        <select class="{{ $toolbarSelect }}" data-editor-command="fontSize" data-editor-target="{{ $editorId }}" title="Tamanho da fonte">
            <option value="">Tamanho</option>
            <option value="2">10</option>
            <option value="3">12</option>
            <option value="4">14</option>
            <option value="5">18</option>
            <option value="6">24</option>
        </select>

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

        <button type="button" class="{{ $toolbarButton }}" data-editor-table data-editor-target="{{ $editorId }}" title="Inserir tabela simples"><i class="fa-solid fa-table"></i></button>
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
                        $variableToken = '{{' . $variableKey . '}}';
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
    @push('scripts')
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const syncEditor = (editorId) => {
                const editor = document.querySelector(`[data-rich-editor="${editorId}"]`);
                const input = document.querySelector(`[data-rich-editor-input="${editorId}"]`);
                if (!editor || !input) {
                    return;
                }

                input.value = editor.innerHTML.trim();
            };

            const focusEditor = (editorId) => {
                const editor = document.querySelector(`[data-rich-editor="${editorId}"]`);
                if (!editor) {
                    return null;
                }

                editor.focus();
                return editor;
            };

            document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
                const editorId = editor.getAttribute('data-rich-editor');
                editor.addEventListener('input', () => syncEditor(editorId));
                syncEditor(editorId);
            });

            document.querySelectorAll('[data-editor-command]').forEach((control) => {
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

            document.querySelectorAll('[data-editor-variable]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const content = button.getAttribute('data-editor-variable');
                    const editor = focusEditor(target);
                    if (!editor) {
                        return;
                    }

                    document.execCommand('insertText', false, content);
                    syncEditor(target);
                });
            });

            document.querySelectorAll('[data-editor-table]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const editor = focusEditor(target);
                    if (!editor) {
                        return;
                    }

                    const tableHtml = '<table style="width:100%; border-collapse:collapse;"><tr><td style="border:1px solid #d1d5db; padding:8px;">Campo</td><td style="border:1px solid #d1d5db; padding:8px;">Informacao</td></tr></table><p></p>';
                    document.execCommand('insertHTML', false, tableHtml);
                    syncEditor(target);
                });
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
