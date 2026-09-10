{{-- resources/views/emails/reset-password.blade.php
     Branded Spanish password-reset email for Voleo.
     Email HTML: inline styles + tables only (clients strip <style> and don't
     support CSS variables), so Voleo colors are hardcoded here. --}}
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>Restablece tu contraseña</title>
</head>

<body style="margin:0; padding:0; background-color:#f1f4ee; font-family:'Inter','Segoe UI',Helvetica,Arial,sans-serif; -webkit-font-smoothing:antialiased;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f1f4ee; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px; background-color:#ffffff; border:1px solid #e4e9e0; border-radius:14px; overflow:hidden;">

                    {{-- Brand bar (forest) --}}
                    <tr>
                        <td style="background-color:#0e3b2e; padding:22px 28px;">
                            <img src="https://voleo.mx/img/email/logo.png" alt="Voleo" height="20">
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:32px 28px 8px;">
                            <h1 style="margin:0 0 12px; font-size:20px; font-weight:700; color:#13211c;">
                                Restablece tu contraseña
                            </h1>
                            <p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#5c6b62;">
                                @if(!empty($name))Hola {{ $name }},@else Hola,@endif recibimos una solicitud para restablecer la contraseña de tu cuenta en Voleo. Da clic en el botón para elegir una nueva.
                            </p>
                        </td>
                    </tr>

                    {{-- CTA button --}}
                    <tr>
                        <td align="center" style="padding:8px 28px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="border-radius:10px; background-color:#0e3b2e;">
                                        <a href="{{ $url }}" target="_blank"
                                            style="display:inline-block; padding:13px 28px; font-size:15px; font-weight:600; color:#ffffff; text-decoration:none; border-radius:10px;">
                                            Restablecer contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    {{-- Meta / fallback link --}}
                    <tr>
                        <td style="padding:0 28px 28px;">
                            <p style="margin:0 0 16px; font-size:13px; line-height:1.6; color:#97a399;">
                                Este enlace caduca en {{ $minutes }} minutos. Si no solicitaste el cambio, puedes ignorar este correo — tu contraseña seguirá igual.
                            </p>
                            <p style="margin:0 0 6px; font-size:12px; color:#97a399;">
                                ¿El botón no funciona? Copia y pega este enlace en tu navegador:
                            </p>
                            <p style="margin:0; font-size:12px; line-height:1.5; word-break:break-all;">
                                <a href="{{ $url }}" style="color:#0e3b2e; text-decoration:underline;">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>

                </table>

                {{-- Footer --}}
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;">
                    <tr>
                        <td align="center" style="padding:20px 28px 0;">
                            <p style="margin:0; font-size:12px; color:#97a399;">
                                <img src="https://voleo.mx/img/email/logo_gray.png" alt="Voleo" height="10"> Tu torneo, punto a punto.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>
</body>

</html>