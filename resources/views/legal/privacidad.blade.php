@extends('layouts.public')
@section('title', 'Política de Privacidad')
@section('content')
<div class="pub-wrap legal-doc">
    <h1>Política de Privacidad</h1>
    <p class="legal-updated">Última actualización: {{ now()->translatedFormat('d \d\e F \d\e Y') }}</p>

    <div class="legal-draft-note">
        <i class="fa-solid fa-circle-info"></i>
        Documento de referencia. Revísalo con un abogado antes de publicarlo; no constituye asesoría legal.
    </div>

    <h2>1. Datos que recopilamos</h2>
    <p>Recopilamos: datos de cuenta (nombre, correo, teléfono), datos de jugadores inscritos por los organizadores (nombre, correo, teléfono), información de torneos, y datos de pago procesados por nuestros proveedores (no almacenamos números completos de tarjeta).</p>

    <h2>2. Cómo usamos los datos</h2>
    <p>Usamos los datos para operar la Plataforma: gestionar inscripciones, generar calendarios y resultados, mostrar páginas públicas de torneos, procesar pagos y comunicarnos contigo sobre el servicio.</p>

    <h2>3. Páginas públicas</h2>
    <p>Los torneos publicados muestran información pública como nombres de jugadores, parejas, resultados y calendarios. Los organizadores deciden qué torneos hacen públicos.</p>

    <h2>4. Compartir datos</h2>
    <p>Compartimos datos únicamente con proveedores necesarios para operar (por ejemplo, procesadores de pago y hosting), o cuando la ley lo exige. No vendemos tus datos personales.</p>

    <h2>5. Conservación</h2>
    <p>Conservamos los datos mientras tu cuenta esté activa o según sea necesario para prestar el servicio y cumplir obligaciones legales.</p>

    <h2>6. Seguridad</h2>
    <p>Aplicamos medidas razonables para proteger los datos. Ningún sistema es 100% seguro, pero trabajamos para minimizar riesgos.</p>

    <h2>7. Tus derechos (ARCO)</h2>
    <p>Conforme a la legislación mexicana, tienes derecho a Acceder, Rectificar, Cancelar u Oponerte al tratamiento de tus datos personales. Consulta el <a href="{{ route('legal.aviso') }}">Aviso de Privacidad</a> para el procedimiento.</p>

    <h2>8. Menores de edad</h2>
    <p>Algunos torneos pueden incluir jugadores menores de edad inscritos por organizadores o tutores. Los organizadores son responsables de contar con el consentimiento correspondiente.</p>

    <h2>9. Cambios</h2>
    <p>Podemos actualizar esta Política. Los cambios se publican en esta página.</p>

    <h2>10. Contacto</h2>
    <p>Para ejercer tus derechos o resolver dudas: <a href="mailto:privacidad@padelcup.mx">privacidad@padelcup.mx</a>.</p>

    <p class="legal-footer-links">
        <a href="{{ route('legal.terminos') }}">Términos</a> ·
        <a href="{{ route('legal.aviso') }}">Aviso de Privacidad</a> ·
        <a href="{{ route('legal.reembolsos') }}">Reembolsos</a>
    </p>
</div>
@endsection
