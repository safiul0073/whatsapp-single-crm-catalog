@extends('emails.layout')

@section('content')
    @php $primaryColor = setting('primary_color', '#2148ff'); @endphp

    <h2 style="margin: 0 0 16px; font-size: 22px; font-weight: 700; color: #111827;">
        New Reply on Your Ticket
    </h2>

    <p style="margin: 0 0 16px; font-size: 15px; line-height: 24px; color: #374151;">
        Hello {{ $user->name }},
    </p>

    <p style="margin: 0 0 20px; font-size: 15px; line-height: 24px; color: #374151;">
        A new reply has been added to your support ticket. Click below to view the full conversation and respond.
    </p>

    {{-- Ticket details box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px; background-color: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
        <tr>
            <td style="padding: 16px 20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        <td style="padding: 5px 0; font-size: 13px; color: #6b7280; width: 90px;">Ticket ID</td>
                        <td style="padding: 5px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $ticket->formatted_id }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; font-size: 13px; color: #6b7280; width: 90px;">Subject</td>
                        <td style="padding: 5px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $ticket->subject }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; font-size: 13px; color: #6b7280; width: 90px;">Status</td>
                        <td style="padding: 5px 0; font-size: 13px; color: #111827; font-weight: 600;">{{ $ticket->status_label }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Action button --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center" style="border-radius: 6px; background-color: {{ $primaryColor }};">
                <a href="{{ $ticketUrl }}" target="_blank"
                    style="display: inline-block; padding: 12px 32px; font-size: 15px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 6px;">
                    View Conversation
                </a>
            </td>
        </tr>
    </table>
@endsection
