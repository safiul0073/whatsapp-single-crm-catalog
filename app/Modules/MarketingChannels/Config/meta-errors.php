<?php

/*
| Plain-language explanations for Meta Graph API errors. Matched by "code:subcode", then "code",
| then a "from-to" code range. Keys: title, message, action, link (optional), reconnect, retryable.
*/

$reconnect = [
    'title' => 'Meta access expired',
    'message' => 'The connection to Meta is no longer valid. This happens when a password changes, access is removed in Business Settings, or the login expires.',
    'action' => 'Reconnect with Meta to restore access. Your messages and settings are kept.',
    'reconnect' => true,
];

$permission = [
    'title' => 'A Meta permission is missing',
    'message' => 'Meta refused because this login did not allow everything the app needs.',
    'action' => 'Reconnect with Meta and keep every permission checked in the Meta window.',
    'reconnect' => true,
];

$rateLimit = [
    'title' => 'Meta is limiting requests',
    'message' => 'Too many requests were sent to Meta in a short time.',
    'action' => 'Nothing to do. It is retried automatically in a few minutes.',
    'retryable' => true,
];

$template = [
    'title' => 'Template problem',
    'message' => 'Meta rejected the message template: it may be paused, disabled, not approved yet, or the variables do not match.',
    'action' => 'Open Templates, sync from Meta, and check the template status and its variables.',
    'link' => 'https://business.facebook.com/wa/manage/message-templates/',
];

$registration = [
    'title' => 'Phone number not ready',
    'message' => 'The WhatsApp number is not registered with the Cloud API or its two-step PIN does not match.',
    'action' => 'Reconnect WhatsApp with Meta to register the number again.',
    'reconnect' => true,
];

return [
    'codes' => [
        '190' => $reconnect,
        '102' => $reconnect,
        '10' => $permission,
        '200' => $permission,
        '4' => $rateLimit,
        '17' => $rateLimit,
        '32' => $rateLimit,
        '613' => $rateLimit,
        '80004' => $rateLimit,
        '80007' => $rateLimit,
        '130429' => $rateLimit,
        '131056' => [
            'title' => 'Too many messages to this person',
            'message' => 'Meta limits how many messages one business can send to the same person in a short time.',
            'action' => 'Wait a little before messaging this contact again.',
            'retryable' => true,
        ],
        '100' => [
            'title' => 'Meta could not find this item',
            'message' => 'An ID sent to Meta is wrong or the item was removed (for example a Page, number or template deleted in Meta).',
            'action' => 'Re-check the channel. If the asset was removed in Meta, reconnect and pick it again.',
        ],
        '368' => [
            'title' => 'Temporarily blocked by Meta',
            'message' => 'Meta blocked this action for a policy reason.',
            'action' => 'Check Account Quality in Meta Business Suite for the reason and how to appeal.',
            'link' => 'https://business.facebook.com/accountquality',
        ],
        '131031' => [
            'title' => 'WhatsApp account locked',
            'message' => 'Meta locked this WhatsApp Business Account, usually for a policy violation or failed verification.',
            'action' => 'Open WhatsApp Manager to see the reason and request a review.',
            'link' => 'https://business.facebook.com/wa/manage/home/',
        ],
        '131042' => [
            'title' => 'Payment problem',
            'message' => 'Meta cannot charge for this message: there is no valid payment method, or the payment failed.',
            'action' => 'Add or fix the payment method in WhatsApp Manager → Payment settings.',
            'link' => 'https://business.facebook.com/billing_hub/payment_settings/',
        ],
        '141006' => [
            'title' => 'Payment method needed',
            'message' => 'The WhatsApp Business Account has no working payment method.',
            'action' => 'Add a payment method in WhatsApp Manager → Payment settings.',
            'link' => 'https://business.facebook.com/billing_hub/payment_settings/',
        ],
        '131047' => [
            'title' => 'The 24-hour reply window has closed',
            'message' => 'More than 24 hours passed since this customer last messaged you, so WhatsApp only allows an approved template.',
            'action' => 'Send an approved template message instead. A normal reply works again once the customer answers.',
            'window' => true,
        ],
        '131026' => [
            'title' => 'Message could not be delivered',
            'message' => 'This person cannot receive the message: the number may not use WhatsApp, or they have not accepted the latest terms.',
            'action' => 'Check the number with the customer or contact them on another channel.',
        ],
        '131049' => [
            'title' => 'Meta held back this message',
            'message' => 'Meta did not deliver this marketing message to keep engagement healthy for this person.',
            'action' => 'Try again later, or send something more relevant. This is not an error in your setup.',
        ],
        '131051' => [
            'title' => 'Message type not supported',
            'message' => 'WhatsApp does not support this kind of message.',
            'action' => 'Send text, media or a template instead.',
        ],
        '135000' => [
            'title' => 'Meta could not send the message',
            'message' => 'Meta returned a general error.',
            'action' => 'Try again. If it keeps failing, share the support code with us.',
            'retryable' => true,
        ],
        '551' => [
            'title' => 'This person is not available',
            'message' => 'Meta says this person cannot receive messages from your Page right now.',
            'action' => 'Wait for the customer to message you, or reach them on another channel.',
        ],
        '10:2018278' => [
            'title' => 'Outside the messaging window',
            'message' => 'Messenger and Instagram allow normal replies only within 24 hours of the customer\'s last message.',
            'action' => 'Wait for the customer to write again before replying.',
            'window' => true,
        ],
        '10:2018065' => $permission,
        '10:2534022' => [
            'title' => 'Instagram message access is off',
            'message' => 'The Instagram account has not allowed connected apps to read and send messages.',
            'action' => 'In the Instagram app: Settings → Messages and story replies → Message controls → Connected tools → turn on "Allow access to messages".',
        ],
        '230' => [
            'title' => 'Instagram message access is off',
            'message' => 'The Instagram account has not allowed connected apps to read and send messages.',
            'action' => 'In the Instagram app: Settings → Messages and story replies → Message controls → Connected tools → turn on "Allow access to messages".',
        ],
    ],

    'ranges' => [
        ['from' => 200, 'to' => 299, 'entry' => $permission],
        ['from' => 132000, 'to' => 132016, 'entry' => $template],
        ['from' => 133000, 'to' => 133010, 'entry' => $registration],
    ],
];
