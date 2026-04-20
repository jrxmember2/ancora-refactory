@extends('portal.layouts.app')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
    <div>
        <a href="{{ route('portal.demands.index') }}" class="text-sm font-semibold text-[#941415]">Voltar às solicitações</a>
        <h1 class="mt-2 text-3xl font-semibold text-gray-950">{{ $demand->subject }}</h1>
        <p class="mt-2 text-sm text-gray-500">{{ $demand->protocol }} · {{ $demand->category?->name ?: 'Sem categoria' }}</p>
    </div>
    <span class="w-fit rounded-full bg-[#f7f2ec] px-4 py-2 text-sm font-semibold text-[#941415]">{{ $statusLabels[$demand->status] ?? $demand->status }}</span>
</div>

<section class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
    <h2 class="text-lg font-semibold text-gray-950">Conversa</h2>
    <div class="mt-5 space-y-4">
        @foreach($demand->publicMessages as $message)
            <div class="rounded-2xl {{ $message->sender_type === 'client' ? 'bg-[#f7f2ec]' : 'border border-[#eadfd5] bg-white' }} p-5">
                <div class="flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
                    <div class="font-semibold text-gray-950">{{ $message->senderName() }}</div>
                    <div class="text-xs text-gray-500">{{ $message->created_at?->format('d/m/Y H:i') }}</div>
                </div>
                <div class="mt-3 whitespace-pre-line text-sm leading-6 text-gray-700">{{ $message->message }}</div>
                @if($message->attachments->count())
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach($message->attachments as $attachment)
                            <a href="{{ route('portal.demands.attachments.download', [$demand, $attachment]) }}" class="rounded-xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-600">{{ $attachment->original_name }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</section>

@if($clientPortalUser->can_open_demands && !in_array($demand->status, ['concluida', 'cancelada'], true))
    <section class="mt-6 rounded-3xl border border-[#eadfd5] bg-white p-6 shadow-sm">
        <h2 class="text-lg font-semibold text-gray-950">Responder</h2>
        <form method="post" action="{{ route('portal.demands.reply', $demand) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf
            <textarea name="message" rows="5" required class="w-full rounded-2xl border border-gray-200 px-4 py-3 text-sm outline-none focus:border-[#941415]" placeholder="Escreva sua resposta"></textarea>
            <input type="file" name="files[]" multiple class="w-full rounded-2xl border border-dashed border-gray-300 bg-[#f7f2ec] px-4 py-4 text-sm">
            <button class="rounded-2xl bg-[#941415] px-5 py-3 text-sm font-semibold text-white">Enviar resposta</button>
        </form>
    </section>
@endif
@endsection
