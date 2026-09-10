{{-- resources/views/emails/claim-reviewed.blade.php --}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>Solicitud de perfil · Voleo</title>
</head>

<body style="margin:0; padding:0; background-color:#f1f4ee; font-family:'Inter','Segoe UI',Helvetica,Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f4ee; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border:1px solid #e4e9e0; border-radius:14px; overflow:hidden;">
                    <tr>
                        <td style="background-color:#0e3b2e; padding:22px 28px;">
                            <img src="https://voleo.mx/img/email/logo.png" alt="Voleo" height="20">
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px 28px 8px;">
                            @if($approved)
                            <h1 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#13211c;">¡Tu perfil fue aprobado! 🎾</h1>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#5c6b62;">
                                @if(!empty($name)){{ $name }}, @endif ya vinculamos tus registros de jugador a tu cuenta. Desde tu perfil puedes ver tus torneos, próximos partidos y resultados.
                            </p>
                            @else
                            <h1 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#13211c;">Sobre tu solicitud de perfil</h1>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#5c6b62;">
                                @if(!empty($name)){{ $name }}, @endif revisamos tu solicitud y por ahora no pudimos aprobarla.
                                @if(!empty($reason))<br><br><strong style="color:#13211c;">Motivo:</strong> {{ $reason }}@endif
                            </p>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#5c6b62;">
                                Puedes volver a intentarlo con los datos correctos, o contactar al organizador de tu torneo.
                            </p>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:8px 28px 30px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:10px; background-color:#0e3b2e;">
                                        <a href="{{ $dashboardUrl }}" target="_blank" style="display:inline-block; padding:13px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            {{ $approved ? 'Ver mi perfil' : 'Ir a mi perfil' }}
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;">
                    <tr>
                        <td align="center" style="padding:20px 28px 0;">
                            <img src="https://voleo.mx/img/email/logo_gray.png" alt="Voleo" height="10"> Tu torneo, punto a punto.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>