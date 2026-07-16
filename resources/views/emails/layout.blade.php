@php
    $appName = setting('site_name', config('app.name', 'Admin Panel'));
    $primaryColor = setting('primary_color', '#5096f2');
    $logoUrl = setting('site_logo') ? media_url(setting('site_logo')) : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $appName }}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
</head>
<body style="margin: 0; padding: 0; background-color: #f4f5f7; font-family: Arial, Helvetica, sans-serif; -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;">
    {{-- Wrapper table for full-width background --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f5f7;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                {{-- Main container --}}
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; width: 100%; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);">
                    {{-- Header --}}
                    <tr>
                        <td align="center" style="padding: 32px 40px; background-color: {{ $primaryColor }};">
                            @if($logoUrl)
                                <img src="{{ $logoUrl }}" alt="{{ $appName }}" style="max-height: 48px; max-width: 200px; width: auto; display: block;">
                            @else
                                <h1 style="margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">{{ $appName }}</h1>
                            @endif
                        </td>
                    </tr>

                    {{-- Body content --}}
                    <tr>
                        <td style="padding: 40px;">
                            @yield('content')
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td style="padding: 24px 40px; background-color: #f9fafb; border-top: 1px solid #e5e7eb;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                <tr>
                                    <td align="center" style="padding-bottom: 12px;">
                                        <p style="margin: 0; font-size: 13px; line-height: 20px; color: #6b7280;">
                                            &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center">
                                        <p style="margin: 0; font-size: 12px; line-height: 18px; color: #9ca3af;">
                                            If you no longer wish to receive these emails, you may
                                            <a href="#" style="color: {{ $primaryColor }}; text-decoration: underline;">unsubscribe</a>.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
