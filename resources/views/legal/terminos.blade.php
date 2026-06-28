@extends('layouts.public')
@section('title', 'Términos y Condiciones')
@section('content')
<div class="pub-wrap legal-doc">
    <h1>Términos y Condiciones</h1>
    <p class="legal-updated">Última actualización: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <div class="legal-draft-note">
        <i class="fa-solid fa-circle-info"></i>
        Documento de referencia. Revísalo con un abogado antes de publicarlo; no constituye asesoría legal.
    </div>

    <h2>1. Aceptación</h2>
    <p>Al usar PadelCup («la Plataforma»), aceptas estos Términos y Condiciones. Si no estás de acuerdo, no utilices la Plataforma.</p>

    <h2>2. Descripción del servicio</h2>
    <p>PadelCup es una plataforma para la organización y gestión de torneos de pádel: inscripciones, generación de grupos y llaves, calendarios, resultados y páginas públicas de seguimiento. Los organizadores («Managers») crean y administran torneos; los jugadores se inscriben y participan.</p>

    <h2>3. Cuentas</h2>
    <p>Para ciertas funciones necesitas una cuenta. Eres responsable de la información que proporcionas y de la actividad en tu cuenta. Debes ofrecer datos veraces y mantenerlos actualizados.</p>

    <h2>4. Pagos</h2>
    <p>Los pagos de inscripción se procesan a través de proveedores externos (por ejemplo, Stripe). PadelCup puede cobrar una comisión por transacción y/o una suscripción a organizadores. Los montos y comisiones aplicables se muestran antes de confirmar el pago.</p>

    <h2>5. Reembolsos</h2>
    <p>Las condiciones de reembolso se describen en nuestra <a href="{{ route('legal.reembolsos') }}">Política de Reembolsos</a>. Salvo que la ley aplicable indique lo contrario, los reembolsos quedan sujetos a las políticas del organizador del torneo.</p>

    <h2>6. Responsabilidades del organizador</h2>
    <p>El organizador es responsable de la veracidad de la información del torneo, del manejo de las inscripciones y pagos asociados, del cumplimiento de las reglas del torneo y de la relación con sus jugadores. PadelCup es una herramienta y no es parte del contrato entre organizador y jugadores.</p>

    <h2>7. Conducta del usuario</h2>
    <p>No puedes usar la Plataforma para fines ilícitos, para vulnerar derechos de terceros, ni para cargar contenido ofensivo, fraudulento o que infrinja propiedad intelectual.</p>

    <h2>8. Propiedad intelectual</h2>
    <p>La Plataforma, su marca y su software son propiedad de PadelCup o de sus licenciantes. El contenido que suben los usuarios sigue siendo de sus titulares, quienes otorgan a PadelCup una licencia para mostrarlo según el funcionamiento del servicio.</p>

    <h2>9. Limitación de responsabilidad</h2>
    <p>La Plataforma se ofrece «tal cual». En la medida permitida por la ley, PadelCup no será responsable por daños indirectos o pérdidas derivadas del uso o la imposibilidad de uso del servicio.</p>

    <h2>10. Cambios</h2>
    <p>Podemos actualizar estos Términos. Los cambios entran en vigor al publicarse en esta página. El uso continuado implica aceptación de los nuevos Términos.</p>

    <h2>11. Contacto</h2>
    <p>Para dudas sobre estos Términos, escríbenos a <a href="mailto:contacto@padelcup.mx">contacto@padelcup.mx</a>.</p>

    <p class="legal-footer-links">
        <a href="{{ route('legal.privacidad') }}">Política de Privacidad</a> ·
        <a href="{{ route('legal.aviso') }}">Aviso de Privacidad</a> ·
        <a href="{{ route('legal.reembolsos') }}">Reembolsos</a>
    </p>
</div>
@endsection
