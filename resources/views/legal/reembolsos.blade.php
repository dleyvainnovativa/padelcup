@extends('layouts.public')
@section('title', 'Política de Reembolsos')
@section('content')
<div class="pub-wrap legal-doc">
    <h1>Política de Reembolsos</h1>
    <p class="legal-updated">Última actualización: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <div class="legal-draft-note">
        <i class="fa-solid fa-circle-info"></i>
        Documento de referencia. Ajústalo a tu operación y revísalo con un abogado; no constituye asesoría legal.
    </div>

    <h2>1. Inscripciones a torneos</h2>
    <p>Las inscripciones se pagan al organizador del torneo a través de la Plataforma. Las condiciones de reembolso (plazos, montos y excepciones) las define cada organizador y pueden variar por torneo.</p>

    <h2>2. Solicitudes</h2>
    <p>Para solicitar un reembolso, contacta directamente al organizador del torneo en el que te inscribiste. PadelCup puede facilitar el proceso técnico del reembolso cuando el organizador lo autorice.</p>

    <h2>3. Comisiones</h2>
    <p>Las comisiones de procesamiento de pago y la comisión de la Plataforma pueden no ser reembolsables, según lo permita el proveedor de pagos y la ley aplicable.</p>

    <h2>4. Cancelación del torneo</h2>
    <p>Si un organizador cancela un torneo, el reembolso de las inscripciones queda a cargo y bajo las políticas de dicho organizador.</p>

    <h2>5. Suscripciones de organizadores</h2>
    <p>Las suscripciones de organizadores se rigen por las condiciones mostradas al momento de la contratación.</p>

    <h2>6. Contacto</h2>
    <p>Dudas sobre reembolsos: <a href="mailto:contacto@padelcup.mx">contacto@padelcup.mx</a>.</p>

    <p class="legal-footer-links">
        <a href="{{ route('legal.terminos') }}">Términos</a> ·
        <a href="{{ route('legal.privacidad') }}">Política de Privacidad</a> ·
        <a href="{{ route('legal.aviso') }}">Aviso de Privacidad</a>
    </p>
</div>
@endsection
