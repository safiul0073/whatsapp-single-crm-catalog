@extends('emails.layout')

@section('content')
    @php
        $primaryColor = setting('primary_color', '#5096f2');
    @endphp

    <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #111827;">
        Welcome, {{ $user->name }}!
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        Thank you for joining <strong>{{ setting('site_name', config('app.name', 'Admin Panel')) }}</strong>. We're excited to have you on board.
    </p>

    <p style="margin: 0 0 24px; font-size: 15px; line-height: 24px; color: #374151;">
        Your account has been created and is ready to use. Click the button below to get started.
    </p>

    {{-- Action button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
        <tr>
            <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
                <a href="{{ $dashboardUrl }}" target="_blank" style="display: inline-block; padding: 12px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    Get Started
                </a>
            </td>
        </tr>
    </table>

    <p style="margin: 0; font-size: 13px; line-height: 20px; color: #6b7280;">
        If the button above doesn't work, copy and paste the following URL into your browser:<br>
        <a href="{{ $dashboardUrl }}" style="color: {{ $primaryColor }}; word-break: break-all;">{{ $dashboardUrl }}</a>
    </p>
@endsection
