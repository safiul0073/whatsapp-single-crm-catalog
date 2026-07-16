@extends('emails.layout')

@section('content')
    @php
        $primaryColor = setting('primary_color', '#5096f2');
    @endphp

    <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #111827;">
        Password Changed
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        Hello {{ $user->name }},
    </p>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        Your password was successfully changed. Here are the details:
    </p>

    {{-- Details box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 4px 0; font-size: 13px; color: #6b7280; width: 80px;">Time</td>
                        <td style="padding: 4px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $changedAt }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-size: 13px; color: #6b7280; width: 80px;">IP Address</td>
                        <td style="padding: 4px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $ipAddress }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Warning box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #fef3c7; border-radius: 6px; border: 1px solid #f59e0b;">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0; font-size: 14px; line-height: 22px; color: #92400e;">
                    <strong>Didn't make this change?</strong> If you did not change your password, your account may be compromised. Please reset your password immediately and contact our support team.
                </p>
            </td>
        </tr>
    </table>

    {{-- Action button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
                <a href="{{ $resetUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    Reset Password
                </a>
            </td>
        </tr>
    </table>
@endsection
