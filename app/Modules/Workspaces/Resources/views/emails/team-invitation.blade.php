@extends('emails.layout')

@section('content')
    @php $primaryColor = setting('primary_color', '#2148ff'); @endphp

    <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #111827;">
        {{ __('You have been invited to join :workspace', ['workspace' => $workspace->name]) }}
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        {{ __('Hello,') }}
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 24px; color: #374151;">
        {{ __(':inviter has invited you to join :workspace as a :role. Click the button below to create your account and accept the invitation.', ['inviter' => $invitedBy->name, 'workspace' => $workspace->name, 'role' => $invitation->role->label()]) }}
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
                <a href="{{ $acceptUrl }}" target="_blank"
                    style="display: inline-block; padding: 12px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    {{ __('Accept Invitation') }}
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 20px 0 0; font-size: 13px; line-height: 20px; color: #6b7280;">
        {{ __('This invitation will expire on :date.', ['date' => $invitation->expires_at?->toFormattedDateString() ?? __('in 7 days')]) }}
    </p>

    <p style="margin: 12px 0 0; font-size: 13px; line-height: 20px; color: #6b7280;">
        {{ __('If the button does not work, copy and paste this URL into your browser:') }}
        <a href="{{ $acceptUrl }}" style="color: {{ $primaryColor }}; word-break: break-all;">{{ $acceptUrl }}</a>
    </p>
@endsection
