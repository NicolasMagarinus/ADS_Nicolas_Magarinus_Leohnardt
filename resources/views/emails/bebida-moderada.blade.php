<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $aprovada ? 'Receita aprovada' : 'Receita não aprovada' }}</title>
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
                            <h1 style="margin:0 0 16px; font-size:20px; color:#212529;">
                                {{ $aprovada ? 'Sua receita entrou no catálogo!' : 'Sua receita não foi aprovada' }}
                            </h1>

                            <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                Olá, {{ $nome }}.
                            </p>

                            @if($aprovada)
                                <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                    A receita de <strong>{{ $cadastro->nm_bebida }}</strong> que você enviou foi
                                    aprovada e já aparece na busca do Drinkerito. Obrigado por contribuir!
                                </p>
                            @else
                                <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                    A receita de <strong>{{ $cadastro->nm_bebida }}</strong> que você enviou não foi
                                    aprovada desta vez.
                                </p>

                                @if($cadastro->ds_motivo_rejeicao)
                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 16px;">
                                        <tr>
                                            <td style="background-color:#f8d7da; border-left:4px solid #dc3545; padding:16px; font-size:14px; line-height:1.6; color:#58151c;">
                                                <strong>Motivo:</strong> {{ $cadastro->ds_motivo_rejeicao }}
                                            </td>
                                        </tr>
                                    </table>
                                @endif

                                <p style="margin:0 0 16px; font-size:15px; line-height:1.6;">
                                    Você pode ajustar o que for preciso e enviar de novo — é só cadastrar a receita
                                    outra vez.
                                </p>
                            @endif

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0;">
                                <tr>
                                    <td style="background-color:#000000; border-radius:6px;">
                                        <a href="{{ $url }}" style="display:inline-block; padding:12px 24px; color:#ffffff; font-size:15px; font-weight:bold; text-decoration:none;">
                                            {{ $aprovada ? 'Ver a receita no catálogo' : 'Abrir meu perfil' }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0; font-size:13px; line-height:1.6; color:#6c757d;">
                                Se o botão não funcionar, copie este endereço no navegador:<br>
                                <span style="word-break:break-all;">{{ $url }}</span>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:#f8f9fa; padding:16px 32px; font-size:12px; color:#6c757d;">
                            Você recebeu este e-mail porque enviou uma receita ao Drinkerito.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
