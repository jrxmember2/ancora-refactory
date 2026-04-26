@php
    $editorId = $editorId ?? ('editor-' . \Illuminate\Support\Str::random(6));
    $name = $name ?? 'content_html';
    $value = $value ?? '';
    $placeholder = $placeholder ?? 'Digite aqui...';
    $minHeight = $minHeight ?? '260px';
    $variableDefinitions = $variableDefinitions ?? [];
    $showVariablePicker = $showVariablePicker ?? false;
    $toolbarButton = 'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border border-gray-200 bg-white px-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-white/[0.06]';
@endphp

<div class="space-y-3" data-rich-editor-wrapper>
    <div class="flex flex-wrap items-center gap-2">
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="bold" data-editor-target="{{ $editorId }}"><i class="fa-solid fa-bold"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="italic" data-editor-target="{{ $editorId }}"><i class="fa-solid fa-italic"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="underline" data-editor-target="{{ $editorId }}"><i class="fa-solid fa-underline"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="formatBlock" data-editor-value="h2" data-editor-target="{{ $editorId }}">H2</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="formatBlock" data-editor-value="h3" data-editor-target="{{ $editorId }}">H3</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="insertUnorderedList" data-editor-target="{{ $editorId }}"><i class="fa-solid fa-list-ul"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="insertOrderedList" data-editor-target="{{ $editorId }}"><i class="fa-solid fa-list-ol"></i></button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-command="insertParagraph" data-editor-target="{{ $editorId }}">P</button>
        <button type="button" class="{{ $toolbarButton }}" data-editor-table data-editor-target="{{ $editorId }}"><i class="fa-solid fa-table"></i></button>
    </div>

    @if($showVariablePicker)
        <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50/60 p-4 dark:border-gray-700 dark:bg-white/[0.03]">
            <div class="mb-3 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">Variáveis disponíveis</div>
            <div class="flex flex-wrap gap-2">
                @foreach($variableDefinitions as $variable)
                    <button
                        type="button"
                        class="rounded-full border border-brand-200 bg-brand-50 px-3 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-100 dark:border-brand-800 dark:bg-brand-500/10 dark:text-brand-200"
                        data-editor-variable="{{ '{{' . $variable['key'] . '}}' }}"
                        data-editor-target="{{ $editorId }}"
                        title="{{ $variable['description'] ?? '' }}"
                    >
                        {{ '{{' . $variable['key'] . '}}' }}
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
                if (!editor || !input) return;
                input.value = editor.innerHTML.trim();
            };

            document.querySelectorAll('[data-rich-editor]').forEach((editor) => {
                const editorId = editor.getAttribute('data-rich-editor');
                editor.addEventListener('input', () => syncEditor(editorId));
                syncEditor(editorId);
            });

            document.querySelectorAll('[data-editor-command]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const command = button.getAttribute('data-editor-command');
                    const value = button.getAttribute('data-editor-value');
                    const editor = document.querySelector(`[data-rich-editor="${target}"]`);
                    if (!editor) return;
                    editor.focus();
                    document.execCommand(command, false, value || null);
                    syncEditor(target);
                });
            });

            document.querySelectorAll('[data-editor-variable]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const content = button.getAttribute('data-editor-variable');
                    const editor = document.querySelector(`[data-rich-editor="${target}"]`);
                    if (!editor) return;
                    editor.focus();
                    document.execCommand('insertText', false, content);
                    syncEditor(target);
                });
            });

            document.querySelectorAll('[data-editor-table]').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = button.getAttribute('data-editor-target');
                    const editor = document.querySelector(`[data-rich-editor="${target}"]`);
                    if (!editor) return;
                    editor.focus();
                    const tableHtml = '<table style="width:100%; border-collapse:collapse;"><tr><td style="border:1px solid #d1d5db; padding:8px;">Campo</td><td style="border:1px solid #d1d5db; padding:8px;">Informação</td></tr></table><p></p>';
                    document.execCommand('insertHTML', false, tableHtml);
                    syncEditor(target);
                });
            });
        });
        </script>
    @endpush
@endonce
