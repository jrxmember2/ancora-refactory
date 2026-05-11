@extends('layouts.app')

@php
    $inputClass = 'h-11 w-full rounded-xl border border-gray-300 bg-white px-4 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $textareaClass = 'w-full rounded-xl border border-gray-300 bg-white px-4 py-3 text-sm text-gray-900 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-none focus:ring-4 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
    $buttonClass = 'rounded-xl bg-brand-500 px-4 py-3 text-sm font-medium text-white hover:bg-brand-600';
    $softButtonClass = 'rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-white/[0.03]';
@endphp

@section('content')
<div class="space-y-6">
    <x-ancora.section-header title="Base Legal Global" subtitle="Documentos juridicos compartilhados para compor a base de consulta comum do Chat do Sindico." />

    <div class="flex flex-wrap gap-3">
        <a href="{{ route('config.ai.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Voltar para IA</span>
        </a>
        <a href="{{ route('config.index') }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
            <i class="fa-solid fa-sliders"></i>
            <span>Voltar para Configuracoes</span>
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-error-200 bg-error-50 px-5 py-4 text-sm text-error-700 shadow-theme-xs dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-300">
            <div class="font-semibold">Existem campos com ajuste pendente.</div>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="rounded-2xl border border-brand-200 bg-brand-50/70 p-5 text-sm text-brand-900 shadow-theme-xs dark:border-brand-900/40 dark:bg-brand-500/10 dark:text-brand-100">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-circle-info mt-0.5"></i>
            <div class="space-y-1.5">
                <p class="font-semibold">Regra desta fase</p>
                <p>O processamento de IA desta etapa aceita apenas arquivos <code>.docx</code>. PDF pode ser guardado como anexo simples, mas nao gera blocos pesquisaveis agora.</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <div class="space-y-6">
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Novo documento global</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Use esta area para subir o Codigo Civil, leis, normas e outras bases juridicas globais.</p>

                <form method="post" action="{{ route('config.ai.legal-base.store') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do documento</label>
                        <input name="name" value="{{ old('name') }}" class="{{ $inputClass }}" placeholder="Ex.: Codigo Civil Brasileiro">
                        @error('name')
                            <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                        <select name="document_type" class="{{ $inputClass }}">
                            @foreach($catalog['document_types'] as $key => $label)
                                <option value="{{ $key }}" @selected(old('document_type') === $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('document_type')
                            <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Data da versao/publicacao</label>
                        <input type="date" name="document_date" value="{{ old('document_date') }}" class="{{ $inputClass }}">
                        @error('document_date')
                            <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Arquivo</label>
                        <input type="file" name="document_file" accept=".docx,.pdf,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="block w-full rounded-xl border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-700 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-500 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-brand-600 dark:border-gray-700 dark:text-gray-300">
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Aceitos: DOCX e PDF. Processamento nesta fase: somente DOCX.</p>
                        @error('document_file')
                            <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                        <input type="checkbox" name="is_active" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked(old('is_active', '1') === '1')>
                        <span>
                            <span class="block font-medium">Documento ativo</span>
                            <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Somente documentos ativos podem alimentar a base global do Chat do Sindico.</span>
                        </span>
                    </label>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacao</label>
                        <textarea name="observation" rows="4" class="{{ $textareaClass }}" placeholder="Contexto da versao, fonte oficial, observacoes internas...">{{ old('observation') }}</textarea>
                        @error('observation')
                            <p class="mt-2 text-xs text-error-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button class="{{ $buttonClass }} w-full">Cadastrar documento global</button>
                </form>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">Resumo rapido</h3>
                <div class="mt-5 space-y-3 text-sm text-gray-600 dark:text-gray-300">
                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Documentos cadastrados</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ number_format($documents->count(), 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Documentos ativos</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ number_format($documents->where('is_active', true)->count(), 0, ',', '.') }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 px-4 py-3 dark:border-gray-800">
                        <div class="text-xs uppercase tracking-[0.16em] text-gray-500">Blocos gerados</div>
                        <div class="mt-1 font-medium text-gray-900 dark:text-white">{{ number_format((int) $documents->sum('chunks_count'), 0, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-4 xl:col-span-2">
            @forelse($documents as $document)
                @php
                    $statusClasses = match($document->processing_status) {
                        'processed' => 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300',
                        'error' => 'bg-error-100 text-error-700 dark:bg-error-500/15 dark:text-error-300',
                        default => 'bg-warning-100 text-warning-700 dark:bg-warning-500/15 dark:text-warning-300',
                    };
                @endphp

                <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ $document->name }}</h3>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $document->is_active ? 'bg-success-100 text-success-700 dark:bg-success-500/15 dark:text-success-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200' }}">
                                    {{ $document->is_active ? 'Ativo' : 'Inativo' }}
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                    {{ $document->processingStatusLabel() }}
                                </span>
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                    {{ strtoupper($document->extension() ?: 'arquivo') }}
                                </span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                                <span>{{ $document->documentTypeLabel() }}</span>
                                <span>{{ $document->document_date?->format('d/m/Y') }}</span>
                                <span>{{ number_format((int) $document->file_size / 1024, 0, ',', '.') }} KB</span>
                                <span>{{ $document->chunks_count }} blocos</span>
                            </div>
                            <p class="mt-2 break-all text-sm text-gray-600 dark:text-gray-300">{{ $document->original_name }}</p>
                            @if($document->observation)
                                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $document->observation }}</p>
                            @endif
                            @if($document->processing_error)
                                <div class="mt-3 rounded-2xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-900/40 dark:bg-error-500/10 dark:text-error-300">
                                    <div class="font-medium">Ultimo erro de processamento</div>
                                    <div class="mt-1">{{ $document->processing_error }}</div>
                                </div>
                            @endif
                            @if(!$document->isDocx())
                                <div class="mt-3 rounded-2xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-900/40 dark:bg-warning-500/10 dark:text-warning-300">
                                    Este arquivo foi salvo como anexo simples. O processamento de IA desta fase funciona apenas com DOCX.
                                </div>
                            @endif
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('config.ai.legal-base.download', $document) }}" class="{{ $softButtonClass }} inline-flex items-center gap-2">
                                <i class="fa-solid fa-download"></i>
                                <span>Baixar</span>
                            </a>
                            @if($document->isDocx())
                                <form method="post" action="{{ route('config.ai.legal-base.process', $document) }}">
                                    @csrf
                                    <button class="{{ $buttonClass }} inline-flex items-center gap-2">
                                        <i class="fa-solid fa-gears"></i>
                                        <span>{{ $document->processing_status === 'processed' ? 'Reprocessar documento' : 'Processar documento' }}</span>
                                    </button>
                                </form>
                            @else
                                <button type="button" disabled class="cursor-not-allowed rounded-xl border border-gray-200 px-4 py-3 text-sm font-medium text-gray-400 dark:border-gray-700 dark:text-gray-500">
                                    PDF sem processamento
                                </button>
                            @endif
                        </div>
                    </div>

                    <form method="post" action="{{ route('config.ai.legal-base.update', $document) }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        @csrf
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Nome do documento</label>
                            <input name="name" value="{{ $document->name }}" class="{{ $inputClass }}">
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Tipo</label>
                            <select name="document_type" class="{{ $inputClass }}">
                                @foreach($catalog['document_types'] as $key => $label)
                                    <option value="{{ $key }}" @selected($document->document_type === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Data da versao/publicacao</label>
                            <input type="date" name="document_date" value="{{ $document->document_date?->format('Y-m-d') }}" class="{{ $inputClass }}">
                        </div>

                        <label class="flex items-start gap-3 rounded-2xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                            <input type="checkbox" name="is_active" value="1" class="mt-1 rounded border-gray-300 text-brand-500 focus:ring-brand-500" @checked($document->is_active)>
                            <span>
                                <span class="block font-medium">Documento ativo</span>
                                <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Ao desativar, os blocos deste documento tambem deixam de entrar na base global.</span>
                            </span>
                        </label>

                        <div class="md:col-span-2">
                            <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Observacao</label>
                            <textarea name="observation" rows="3" class="{{ $textareaClass }}">{{ $document->observation }}</textarea>
                        </div>

                        <div class="md:col-span-2">
                            <button class="{{ $softButtonClass }} inline-flex items-center gap-2">
                                <i class="fa-solid fa-floppy-disk"></i>
                                <span>Salvar ajustes</span>
                            </button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 shadow-theme-xs dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-400">
                    Nenhum documento global foi cadastrado ainda.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
