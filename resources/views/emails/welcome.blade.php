{{-- resources/views/emails/welcome.blade.php
     Branded Spanish welcome email for Voleo. Inline styles + tables (email
     clients strip <style> and don't support CSS vars); Voleo hex hardcoded. --}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>Bienvenido a Voleo</title>
</head>

<body style="margin:0; padding:0; background-color:#f1f4ee; font-family:'Inter','Segoe UI',Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f4ee; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border:1px solid #e4e9e0; border-radius:14px; overflow:hidden;">

                    {{-- Brand hero (lime, deck-style) --}}
                    <tr>
                        <td style="background-color:#d9f27a; padding:34px 28px 30px; position:relative;">
                            <img src="https://voleo.mx/img/email/logo_dark.png" alt="Voleo" height="20">
                            <p style="margin:14px 0 0; font-size:22px; font-weight:800; line-height:1.2; color:#0E4038;">
                                Tu torneo,<br>punto a punto.
                            </p>
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:30px 28px 8px;">
                            <h1 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#13211c;">
                                @if(!empty($name)){{ $name }}, ¡te damos la bienvenida! @else ¡Te damos la bienvenida! @endif
                            </h1>
                            <p style="margin:0 0 14px; font-size:14px; line-height:1.6; color:#5c6b62;">
                                Tu cuenta en Voleo ya está lista. Desde aquí puedes inscribirte a torneos de pádel, seguir tus partidos y ver resultados y tablas en tiempo real.
                            </p>
                            @if($viaSocial)
                            <p style="margin:0 0 14px; font-size:14px; line-height:1.6; color:#5c6b62;">
                                Entraste con tu cuenta social, así que no necesitas contraseña — solo vuelve a usar el mismo botón para acceder.
                            </p>
                            @endif
                        </td>
                    </tr>

                    {{-- CTA --}}
                    <tr>
                        <td align="center" style="padding:8px 28px 22px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:10px; background-color:#0e3b2e;">
                                        <a href="{{ $directoryUrl }}" target="_blank"
                                            style="display:inline-block; padding:13px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            Explorar torneos
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Quick "what you can do" list --}}
                    <tr>
                        <td style="padding:0 28px 28px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="padding:10px 0; border-top:1px solid #e4e9e0; font-size:13.5px; color:#13211c;">
                                        <strong style="color:#0e3b2e;">Inscríbete</strong> a una categoría y paga en línea en minutos.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-top:1px solid #e4e9e0; font-size:13.5px; color:#13211c;">
                                        <strong style="color:#0e3b2e;">Encuentra tu partido</strong> con el buscador del calendario.
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding:10px 0; border-top:1px solid #e4e9e0; border-bottom:1px solid #e4e9e0; font-size:13.5px; color:#13211c;">
                                        <strong style="color:#0e3b2e;">Sigue los resultados</strong> y las tablas en vivo.
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                </table>

                {{-- Footer --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;">
                    <tr>
                        <td align="center" style="padding:20px 28px 0;">
                            <p style="margin:0 0 4px; font-size:12px; color:#97a399;">
                                <img src="https://voleo.mx/img/email/logo_gray.png" alt="Voleo" height="10"> Tu torneo, punto a punto.
                            </p>
                            <p style="margin:0; font-size:11px; color:#97a399;">
                                Recibes este correo porque creaste una cuenta en Voleo.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>