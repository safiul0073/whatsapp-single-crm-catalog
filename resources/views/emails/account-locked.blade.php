@extends('emails.layout')

@section('content')
    @php
        $primaryColor = setting('primary_color', '#5096f2');
    @endphp

    <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #111827;">
        Account Temporarily Locked
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        We detected multiple failed login attempts for the account associated with <strong>{{ $email }}</strong>.
    </p>

    {{-- Warning box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #fee2e2; border-radius: 6px; border: 1px solid #ef4444;">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0; font-size: 14px; line-height: 22px; color: #991b1b;">
                    <strong>Your account has been temporarily locked</strong> for {{ $minutes }} {{ $minutes === 1 ? 'minute' : 'minutes' }} due to {{ $maxAttempts }} consecutive failed login attempts.
                </p>
            </td>
        </tr>
    </table>

    {{-- Details box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 4px 0; font-size: 13px; color: #6b7280; width: 100px;">IP Address</td>
                        <td style="padding: 4px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $ip }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; font-size: 13px; color: #6b7280; width: 100px;">Locked Until</td>
                        <td style="padding: 4px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $lockedUntil }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 24px; color: #374151;">
        If this was you, please wait for the lockout to expire and try again. If you've forgotten your password, you can reset it below.
    </p>

    {{-- Action button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
                <a href="{{ $resetUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    Reset Password
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; line-height: 20px; color: #6b7280;">
        If you did not attempt to log in, someone else may be trying to access your account. We recommend changing your password as a precaution.
    </p>
@endsection
