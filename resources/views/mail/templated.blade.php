<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>{{ config('platform.brand.name') }}</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#1e293b;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f5f7fb;padding:32px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;">
                    <tr>
                        <td style="padding:0 8px 18px;">
                            <span style="display:inline-block;width:32px;height:32px;border-radius:9px;background:#c54727;vertical-align:middle;"></span>
                            <span style="font-size:20px;font-weight:800;color:#0a1628;vertical-align:middle;margin-left:8px;">{{ config('platform.brand.name') }}</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#ffffff;border:1px solid #e3e8ef;border-radius:18px;padding:32px;font-size:15px;line-height:1.65;">
                            {!! $body !!}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 8px;font-size:12px;line-height:1.6;color:#64748b;">
                            {{ config('platform.brand.legal_name') }} · {{ config('platform.brand.support_email') }}<br>
                            {{ $locale === 'fr' ? 'Nous ne vous demanderons jamais vos identifiants ni un paiement par e-mail en dehors de votre espace client.' : 'We will never ask for your password or for a payment by email outside your account.' }}
                            @if ($unsubscribeUrl)
                                <br><a href="{{ $unsubscribeUrl }}" style="color:#64748b;">{{ $locale === 'fr' ? 'Se désabonner de ces e-mails' : 'Unsubscribe from these emails' }}</a>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
