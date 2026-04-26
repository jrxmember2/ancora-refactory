@props([
    'scope',
    'label' => 'Exportar',
    'allowSelection' => true,
])

@php
    $query = request()->except(['selected', 'page']);
    $exportUrl = function (string $format) use ($scope, $query) {
        return route('financeiro.export', ['scope' => $scope, 'format' => $format] + $query);
    };
@endphp

<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }} data-financeiro-export>
    <span class="inline-flex items-center rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">{{ $label }}</span>
    @if($allowSelection)
        <label class="inline-flex items-center gap-2 rounded-xl border border-gray-200 px-3 py-2 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
            <input type="checkbox" data-export-only-selected class="rounded border-gray-300 text-brand-500 focus:ring-brand-500 dark:border-gray-700">
            Apenas selecionados
        </label>
    @endif
    <button type="button" data-financeiro-export-button data-export-url="{{ $exportUrl('csv') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-200">CSV</button>
    <button type="button" data-financeiro-export-button data-export-url="{{ $exportUrl('xlsx') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-200">XLSX</button>
    <button type="button" data-financeiro-export-button data-export-url="{{ $exportUrl('pdf') }}" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-200">PDF</button>
    <button type="button" data-financeiro-export-button data-export-url="{{ $exportUrl('print') }}" data-export-target="blank" class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-800 dark:text-gray-200">Imprimir</button>
</div>

@once
    @push('scripts')
        <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-financeiro-export-button]');
            if (!button) {
                return;
            }

            const wrapper = button.closest('[data-financeiro-export]');
            const url = new URL(button.dataset.exportUrl, window.location.origin);
            const onlySelectedToggle = wrapper ? wrapper.querySelector('[data-export-only-selected]') : null;

            if (onlySelectedToggle && onlySelectedToggle.checked) {
                const selected = Array.from(document.querySelectorAll('input[name="selected[]"][data-select-item]:checked'))
                    .map((input) => input.value)
                    .filter(Boolean);

                if (!selected.length) {
                    window.alert('Selecione ao menos um registro para exportar apenas os itens marcados.');
                    return;
                }

                url.searchParams.delete('selected[]');
                selected.forEach((value) => url.searchParams.append('selected[]', value));
            }

            if (button.dataset.exportTarget === 'blank') {
                window.open(url.toString(), '_blank', 'noopener');
                return;
            }

            window.location.href = url.toString();
        });

        document.addEventListener('change', function (event) {
            const master = event.target.closest('[data-select-all]');
            if (!master) {
                return;
            }

            const table = master.closest('table');
            if (!table) {
                return;
            }

            table.querySelectorAll('input[name="selected[]"][data-select-item]').forEach((checkbox) => {
                checkbox.checked = master.checked;
            });
        });

        document.addEventListener('change', function (event) {
            const item = event.target.closest('input[name="selected[]"][data-select-item]');
            if (!item) {
                return;
            }

            const table = item.closest('table');
            const master = table ? table.querySelector('[data-select-all]') : null;
            if (!master) {
                return;
            }

            const items = Array.from(table.querySelectorAll('input[name="selected[]"][data-select-item]'));
            master.checked = items.length > 0 && items.every((checkbox) => checkbox.checked);
            master.indeterminate = items.some((checkbox) => checkbox.checked) && !master.checked;
        });
        </script>
    @endpush
@endonce
