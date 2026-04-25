<div class="flex flex-wrap gap-2">
    <a
        href="{{ route('clientes.export', array_merge(['scope' => $scope, 'format' => 'csv'], request()->query())) }}"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-gray-800/70"
    >
        <i class="fa-solid fa-file-csv"></i>
        <span>CSV</span>
    </a>
    <a
        href="{{ route('clientes.export', array_merge(['scope' => $scope, 'format' => 'xls'], request()->query())) }}"
        class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-200 dark:hover:bg-gray-800/70"
    >
        <i class="fa-solid fa-file-excel"></i>
        <span>XLS</span>
    </a>
    <a
        href="{{ route('clientes.export', array_merge(['scope' => $scope, 'format' => 'pdf'], request()->query())) }}"
        class="inline-flex items-center gap-2 rounded-xl border border-brand-300 bg-brand-50 px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-100 dark:border-brand-700 dark:bg-brand-500/10 dark:text-brand-300 dark:hover:bg-brand-500/20"
    >
        <i class="fa-solid fa-file-pdf"></i>
        <span>PDF</span>
    </a>
</div>
