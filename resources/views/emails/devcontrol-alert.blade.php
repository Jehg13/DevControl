<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $event }} | DevControl</title>
</head>
<body style="margin:0;background:#09090b;color:#e4e4e7;font-family:Arial,Helvetica,sans-serif;">
    <div style="padding:32px 16px;background:#09090b;">
        <div style="max-width:620px;margin:0 auto;overflow:hidden;border:1px solid #27272a;border-radius:18px;background:#111113;">
            <div style="padding:28px 32px;background:linear-gradient(135deg,#1b0b0d,#111113);border-bottom:1px solid #27272a;">
                <div style="font-size:12px;font-weight:700;letter-spacing:3px;color:#ff5b5b;text-transform:uppercase;">
                    DEVCONTROL / NEXUS
                </div>
                <h1 style="margin:14px 0 0;color:#ffffff;font-size:26px;line-height:1.2;">
                    {{ $event }}
                </h1>
                <p style="margin:10px 0 0;color:#a1a1aa;font-size:14px;">
                    Se registró una actividad que requiere tu atención.
                </p>
            </div>

            <div style="padding:28px 32px;">
                <div style="margin-bottom:22px;padding:18px;border:1px solid #3f1d21;border-radius:12px;background:#1a0d0f;">
                    <div style="margin-bottom:7px;color:#71717a;font-size:11px;letter-spacing:1px;text-transform:uppercase;">
                        Proyecto
                    </div>
                    <div style="color:#ffffff;font-size:18px;font-weight:700;">{{ $projectName }}</div>
                    <div style="margin-top:6px;color:#71717a;font-size:12px;">{{ $date }}</div>
                </div>

                <h2 style="margin:0 0 18px;color:#ffffff;font-size:19px;">{{ $title }}</h2>

                @if(count($alerts))
                    @foreach($alerts as $alert)
                        <div style="margin-bottom:14px;padding:14px;border:1px solid #27272a;border-radius:10px;background:#151517;">
                            <div style="color:#ffffff;font-weight:700;">{{ $alert['event'] }}: {{ $alert['title'] }}</div>
                            <div style="margin-top:5px;color:#a1a1aa;font-size:13px;">Proyecto: {{ $alert['projectName'] }}</div>
                            @foreach($alert['details'] as $label => $value)
                                <div style="margin-top:4px;color:#a1a1aa;font-size:12px;">{{ $label }}: {{ $value }}</div>
                            @endforeach
                        </div>
                    @endforeach
                @endif

                @if(count($details))
                    <table role="presentation" style="width:100%;border-collapse:collapse;">
                        @foreach($details as $label => $value)
                            <tr>
                                <td style="padding:11px 0;border-bottom:1px solid #27272a;color:#71717a;font-size:13px;width:38%;vertical-align:top;">
                                    {{ $label }}
                                </td>
                                <td style="padding:11px 0;border-bottom:1px solid #27272a;color:#d4d4d8;font-size:13px;vertical-align:top;">
                                    {{ $value }}
                                </td>
                            </tr>
                        @endforeach
                    </table>
                @endif

                @if($appUrl)
                    <a href="{{ $appUrl }}" style="display:inline-block;margin-top:26px;padding:12px 18px;border-radius:9px;background:#d61f2c;color:#ffffff;font-size:13px;font-weight:700;text-decoration:none;">
                        Abrir DevControl
                    </a>
                @endif
            </div>

            <div style="padding:18px 32px;border-top:1px solid #27272a;color:#52525b;font-size:11px;">
                Este aviso fue generado automáticamente por DevControl. No respondas a este correo.
            </div>
        </div>
    </div>
</body>
</html>
