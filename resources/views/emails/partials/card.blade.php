@php
    $brandName = $brandName ?? \App\Support\MailSettings::fromName();
    $appUrl = $appUrl ?? config('app.url');
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#eef2f7;margin:0;padding:0;width:100%;">
    <tr>
        <td align="center" style="padding:40px 16px;">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #dbe3ef;border-radius:10px;overflow:hidden;">
                <tr>
                    <td style="padding:28px 36px 24px;background-color:#ffffff;border-bottom:3px solid #2563eb;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td style="font-size:22px;font-weight:700;color:#0f172a;letter-spacing:-0.02em;line-height:1.3;">
                                    {{ $brandName }}
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:36px;color:#334155;font-size:15px;line-height:1.75;background-color:#ffffff;">
                        <div style="color:#334155;font-size:15px;line-height:1.75;">
                            {!! $htmlBody !!}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 36px;background-color:#f8fafc;border-top:1px solid #e2e8f0;text-align:center;">
                        <p style="margin:0 0 6px;font-size:12px;line-height:1.6;color:#64748b;">
                            &copy; {{ date('Y') }} {{ $brandName }}. Mọi quyền được bảo lưu.
                        </p>
                        @if (filled($appUrl))
                            <p style="margin:0;font-size:12px;line-height:1.6;">
                                <a href="{{ $appUrl }}" style="color:#2563eb;text-decoration:none;">{{ parse_url($appUrl, PHP_URL_HOST) ?: $appUrl }}</a>
                            </p>
                        @endif
                    </td>
                </tr>
            </table>
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
                <tr>
                    <td style="padding:16px 8px 0;text-align:center;font-size:11px;line-height:1.5;color:#94a3b8;">
                        Email này được gửi từ hệ thống {{ $brandName }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
