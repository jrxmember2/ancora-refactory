@extends('layouts.app')

@section('content')
<x-ancora.section-header :title="$proposal ? 'Editar proposta' : 'Nova proposta'" subtitle="Formulário principal portado para a nova base Laravel." />
<form method="post" action="{{ $action }}" class="space-y-6">
    @csrf
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-theme-xs dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Data da proposta</label>
                <input type="date" name="proposal_date" value="{{ old('proposal_date', optional($proposal?->proposal_date)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Cliente</label>
                <input type="text" name="client_name" value="{{ old('client_name', $proposal?->client_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Administradora / síndico</label>
                <select name="administradora_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($administradoras as $item)
                        <option value="{{ $item->id }}" @selected((int) old('administradora_id', $proposal?->administradora_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Serviço</label>
                <select name="service_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($servicos as $item)
                        <option value="{{ $item->id }}" @selected((int) old('service_id', $proposal?->service_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor da proposta</label>
                <input type="text" name="proposal_total" value="{{ old('proposal_total', $proposal?->proposal_total) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Valor fechado</label>
                <input type="text" name="closed_total" value="{{ old('closed_total', $proposal?->closed_total) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Solicitante</label>
                <input type="text" name="requester_name" value="{{ old('requester_name', $proposal?->requester_name) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Telefone</label>
                <input type="text" name="requester_phone" value="{{ old('requester_phone', $proposal?->requester_phone) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">E-mail</label>
                <input type="email" name="contact_email" value="{{ old('contact_email', $proposal?->contact_email) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de envio</label>
                <select name="send_method_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($formasEnvio as $item)
                        <option value="{{ $item->id }}" @selected((int) old('send_method_id', $proposal?->send_method_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <select name="response_status_id" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white">
                    <option value="">Selecione</option>
                    @foreach($statusRetorno as $item)
                        <option value="{{ $item->id }}" @selected((int) old('response_status_id', $proposal?->response_status_id) === (int) $item->id)>{{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Follow-up</label>
                <input type="date" name="followup_date" value="{{ old('followup_date', optional($proposal?->followup_date)->format('Y-m-d')) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div>
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Validade (dias)</label>
                <input type="number" name="validity_days" value="{{ old('validity_days', $proposal?->validity_days ?? 30) }}" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Indicação</label>
                <div class="grid grid-cols-1 gap-4 md:grid-cols-[180px,1fr]">
                    <label class="inline-flex h-11 items-center gap-3 rounded-xl border border-gray-300 px-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200"><input type="checkbox" name="has_referral" value="1" @checked((bool) old('has_referral', $proposal?->has_referral))> Houve indicação</label>
                    <input type="text" name="referral_name" value="{{ old('referral_name', $proposal?->referral_name) }}" placeholder="Nome da indicação" class="h-11 w-full rounded-xl border border-gray-300 bg-transparent px-4 text-sm text-gray-800 dark:border-gray-700 dark:text-white" />
                </div>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Motivo da recusa</label>
                <textarea name="refusal_reason" rows="3" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ old('refusal_reason', $proposal?->refusal_reason) }}</textarea>
            </div>
            <div class="md:col-span-2 xl:col-span-3">
                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Observações</label>
                <textarea name="notes" rows="4" class="w-full rounded-xl border border-gray-300 bg-transparent px-4 py-3 text-sm text-gray-800 dark:border-gray-700 dark:text-white">{{ old('notes', $proposal?->notes) }}</textarea>
            </div>
        </div>
    </div>
    <div class="flex flex-wrap gap-3">
        <button class="inline-flex items-center gap-2 rounded-xl bg-brand-500 px-5 py-3 text-sm font-medium text-white hover:bg-brand-600">{{ $submitLabel }}</button>
        @if($proposal)
            <a href="{{ route('propostas.show', $proposal) }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Cancelar</a>
        @else
            <a href="{{ route('propostas.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-5 py-3 text-sm font-medium text-gray-700 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-200">Voltar</a>
        @endif
    </div>
</form>
@endsection
