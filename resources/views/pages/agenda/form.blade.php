@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$title" subtitle="Defina o tipo, a data/hora, o responsavel e o vinculo do compromisso.">
    <a href="{{ route('agenda.calendar') }}" class="rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
</x-ancora.section-header>

@if(session('error'))
    <div class="mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">{{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-xl border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-700 dark:border-error-800 dark:bg-error-500/10 dark:text-error-300">
        <ul class="list-disc pl-5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

@if(!empty($parentProcess ?? null))
    <div class="mb-4 rounded-xl border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-700 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-200">
        Prazo vinculado ao processo <strong>{{ $parentProcess->process_number }}</strong>.
    </div>
@endif

@include('pages.agenda.partials._form', ['inModal' => false])
@endsection
