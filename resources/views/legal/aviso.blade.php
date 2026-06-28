@extends('layouts.public')
@section('title', 'Aviso de Privacidad')
@section('content')
<div class="pub-wrap legal-doc">
    <h1>Aviso de Privacidad</h1>
    <p class="legal-updated">Última actualización: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <div class="legal-draft-note">
        <i class="fa-solid fa-circle-info"></i>
        Plantilla conforme a la LFPDPPP. Complétala con los datos del responsable y revísala con un abogado antes de publicarla; no constituye asesoría legal.
    </div>

    <h2>Responsable</h2>
    <p>[Nombre o razón social del responsable], con domicilio en [domicilio], es responsable del tratamiento de tus datos personales conforme a la Ley Federal de Protección de Datos Personales en Posesión de los Particulares (LFPDPPP).</p>

    <h2>Datos que tratamos</h2>
    <p>Datos de identificación y contacto (nombre, correo, teléfono) y, en su caso, datos necesarios para procesar pagos a través de terceros.</p>

    <h2>Finalidades</h2>
    <p>Primarias: gestionar inscripciones y torneos, mostrar información pública del torneo, procesar pagos y dar soporte. Secundarias: mejoras del servicio y comunicaciones informativas (puedes oponerte a estas últimas).</p>

    <h2>Transferencias</h2>
    <p>Podemos compartir datos con proveedores de pago y de infraestructura estrictamente para operar el servicio. No realizamos transferencias que requieran tu consentimiento sin obtenerlo, salvo las excepciones de ley.</p>

    <h2>Derechos ARCO</h2>
    <p>Puedes ejercer tus derechos de Acceso, Rectificación, Cancelación y Oposición, así como revocar tu consentimiento, enviando una solicitud a <a href="mailto:privacidad@padelcup.mx">privacidad@padelcup.mx</a> con tu nombre, el derecho que deseas ejercer y la información que permita atender tu solicitud.</p>

    <h2>Cambios al aviso</h2>
    <p>Cualquier modificación a este Aviso se publicará en esta página.</p>

    <p class="legal-footer-links">
        <a href="{{ route('legal.terminos') }}">Términos</a> ·
        <a href="{{ route('legal.privacidad') }}">Política de Privacidad</a> ·
        <a href="{{ route('legal.reembolsos') }}">Reembolsos</a>
    </p>
</div>
@endsection
