@php
    $attachmentRoleOptions = $attachmentRoleOptions ?? [
        'documento' => 'Documentos',
        'contrato' => 'Contratos',
        'outro' => 'Outros',
    ];
    $attachmentRolesOld = old('attachment_roles', ['documento']);
    if (!is_array($attachmentRolesOld) || $attachmentRolesOld === []) {
        $attachmentRolesOld = ['documento'];
    }
@endphp

<div x-data="ancoraAttachmentRepeater(@js(array_values($attachmentRolesOld)))" class="space-y-3">
    <template x-for="(row, index) in rows" :key="row.id">
        <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-800">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-500 dark:text-gray-400">
                        Anexo <span x-text="index + 1"></span>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="row.fileName || 'Nenhum arquivo selecionado'"></div>
                </div>
                <button
                    type="button"
                    @click="removeRow(index)"
                    x-show="rows.length > 1"
                    class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5"
                >
                    Remover
                </button>
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_220px_auto] md:items-end">
                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Arquivo</label>
                    <label class="inline-flex w-full cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-brand-300 px-4 py-4 text-sm font-medium text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10">
                        <i class="fa-solid fa-paperclip"></i>
                        <span x-text="row.fileName || 'Escolher arquivo'"></span>
                        <input type="file" name="attachment_files[]" class="sr-only" @change="updateFile(index, $event)">
                    </label>
                </div>

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-200">Categoria</label>
                    <select name="attachment_roles[]" x-model="row.role" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 dark:border-gray-700">
                        @foreach($attachmentRoleOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex justify-end md:pb-0.5">
                    <button
                        type="button"
                        @click="addRow()"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-brand-300 text-brand-600 hover:bg-brand-50 dark:border-brand-700 dark:text-brand-300 dark:hover:bg-brand-500/10"
                        title="Adicionar mais um anexo"
                    >
                        <i class="fa-solid fa-plus"></i>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
