@extends('emails.layout')

@section('content')
    @php $primaryColor = setting('primary_color', '#2148ff'); @endphp

    <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #111827;">
        {{ __('Welcome to :workspace', ['workspace' => $workspace->name]) }}
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        {{ __('Hello :name,', ['name' => $member->name]) }}
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 24px; color: #374151;">
        {{ __('You have been added to :workspace by :inviter. Your account is ready to use.', ['workspace' => $workspace->name, 'inviter' => $invitedBy->name]) }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 5px 0; font-size: 13px; color: #6b7280; width: 120px;">{{ __('Email') }}</td>
                        <td style="padding: 5px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $member->email }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; font-size: 13px; color: #6b7280; width: 120px;">{{ __('Password') }}</td>
                        <td style="padding: 5px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $plainPassword }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
                <a href="{{ $loginUrl }}" target="_blank"
                    style="display: inline-block; padding: 12px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    {{ __('Sign in to your account') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 0; font-size: 13px; line-height: 20px; color: #6b7280;">
        {{ __('For security, we recommend changing your password after your first login.') }}
    </p>
@endsection
