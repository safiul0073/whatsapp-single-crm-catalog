<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $replySubject }}</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <p>Hi {{ $customerName }},</p>

    <div style="white-space: pre-wrap;">{{ $replyMessage }}</div>

    <p>{{ $senderName }}</p>

    @if($originalMessage)
    <div style="border-left: 3px solid #ddd; margin-top: 30px; padding-left: 12px; color: #777; font-size: 13px;">
        <p style="margin: 0 0 6px 0;"><strong>Your original message:</strong></p>
        <div style="white-space: pre-wrap;">{{ $originalMessage }}</div>
    </div>
    @endif
</body>
</html>
