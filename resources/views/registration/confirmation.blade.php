@extends('layouts.public')

@section('title', 'Estado de inscripción')

@section('content')
<div style="margin-bottom:18px;">
    <h1 style="font-size:20px;font-weight:700;letter-spacing:-0.02em;margin:0;">Tu inscripción</h1>
    <div style="color:var(--text-muted);font-size:13px;margin-top:3px;">{{ $registration->category->name }} · {{ $registration->category->tournament->name }}</div>
</div>

<div class="tc-card">
    <div class="tc-card__body">
        @php $status = $registration->status->value; @endphp

        @if($status === 'confirmed')
        <x-pill variant="ok" dot>Confirmada</x-pill>
        <p style="font-size:14px;color:var(--text);margin-top:12px;">
            ¡Listo! Tu pareja quedó inscrita y pagada.
        </p>
        @if($registration->category->whatsapp_group_url)
        <a href="{{ $registration->category->whatsapp_group_url }}" target="_blank"
            class="btn btn-soft mt-2" style="color:#25D366;">
            <i class="fa-brands fa-whatsapp me-1"></i> Unirme al grupo de WhatsApp
        </a>
        @endif
        @elseif($status === 'pending_payment')
        <x-pill variant="warn" dot>Pago pendiente</x-pill>

        @if($registration->invitation && $registration->invitation->isPending())
        @php $inviteUrl = route('quick.show', $registration->invitation); @endphp
        <p style="font-size:14px;color:var(--text);margin-top:12px;">
            Pagaste tu parte. Ahora comparte este enlace con tu compañero/a para que
            complete su inscripción y pague su parte. La pareja se confirma cuando ambos paguen.
        </p>

        <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--radius);padding:12px;margin-top:12px;">
            <div style="font-size:11px;color:var(--text-faint);text-transform:uppercase;letter-spacing:.05em;font-weight:600;margin-bottom:6px;">
                Enlace para tu compañero/a
            </div>
            <input id="invite-url" type="text" readonly value="{{ $inviteUrl }}"
                class="form-control" style="font-size:12px;border-radius:var(--radius);margin-bottom:10px;"
                onclick="this.select()">
            <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-accent btn-sm" id="copy-invite">
                    <i class="fa-solid fa-copy me-1"></i> Copiar enlace
                </button>
                <a class="btn btn-soft btn-sm" style="color:#25D366;" target="_blank"
                    href="https://wa.me/?text={{ urlencode('¡Vamos a jugar '.$registration->category->name.'! Completa tu inscripción aquí: '.$inviteUrl) }}">
                    <i class="fa-brands fa-whatsapp me-1"></i> Enviar por WhatsApp
                </a>
            </div>
        </div>

        <div style="font-size:12px;color:var(--text-faint);margin-top:10px;">
            Vence el {{ $registration->invitation->expires_at->timezone('America/Mexico_City')->translatedFormat('d M, H:i') }} h.
        </div>

        <script>
            document.getElementById('copy-invite')?.addEventListener('click', async (e) => {
                const input = document.getElementById('invite-url');
                try {
                    await navigator.clipboard.writeText(input.value);
                    const btn = e.currentTarget;
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-check me-1"></i> ¡Copiado!';
                    setTimeout(() => {
                        btn.innerHTML = original;
                    }, 1800);
                } catch (_) {
                    input.select();
                    document.execCommand('copy');
                }
            });
        </script>
        @else
        <p style="font-size:14px;color:var(--text-muted);margin-top:12px;">
            Estamos confirmando tu pago. Esto puede tardar unos momentos. Recarga en un momento.
        </p>
        @endif
        @else
        <x-pill variant="bad" dot>{{ $registration->status->label() }}</x-pill>
        <p style="font-size:14px;color:var(--text-muted);margin-top:12px;">
            Esta inscripción no está activa. Si crees que es un error, contacta al organizador.
        </p>
        @endif
    </div>
</div>
@endsection