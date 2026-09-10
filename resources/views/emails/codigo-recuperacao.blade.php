<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de recuperação</title>
</head>
<body style="margin:0; padding:0; background-color:#f8f9fa; font-family:Arial, Helvetica, sans-serif; color:#212529;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa; padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px; background-color:#ffffff; border-radius:8px; overflow:hidden; box-shadow:0 2px 10px rgba(0,0,0,0.08);">
                    <tr>
                        <td style="background:linear-gradient(to right,#000000,#333333); padding:24px 32px;">
                            <span style="color:#ffffff; font-size:22px; font-weight:bold;">Drinkerito</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 16px; font-size:20px; color:#212529;">Recuperação de senha</h1>

                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#495057;">
                                Use o código abaixo para criar uma senha nova. Ele vale por
                                {{ $minutosValidade }} minutos.
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 24px;">
                                <tr>
                                    <td style="background-color:#f1f3f5; border:1px solid #dee2e6; border-radius:8px; padding:16px 28px;">
                                        <span style="font-size:32px; font-weight:bold; letter-spacing:8px; color:#212529;">{{ $codigo }}</span>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px; font-size:14px; line-height:1.6; color:#6c757d;">
                                Se não foi você que pediu, pode ignorar este e-mail — sua senha continua a mesma.
                            </p>
                            <p style="margin:0; font-size:14px; line-height:1.6; color:#6c757d;">
                                Nunca compartilhe este código com ninguém.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8f9fa; padding:16px 32px; border-top:1px solid #dee2e6;">
                            <span style="font-size:12px; color:#868e96;">Drinkerito — sua rede social de receitas de bebidas</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
