<?php

return [
    'invalid_inviter' => 'Invalid inviter! The invite code is banned!',
    'fields' => [
        'inviter' => 'Sender',
        'invitee' => 'Receive email',
        'time_invited' => 'Send time',
        'valid' => 'Valid',
        'invitee_register_uid' => 'Registered UID',
        'invitee_register_email' => 'Registered email',
        'invitee_register_username' => 'Registered username',
        'expired_at' => 'hash expired at',
    ],
    'send_deny_reasons' => [
        'invite_system_closed' => 'Invite system is closed',
        'no_permission' => 'Require :class or above to send invitations',
        'invite_not_enough' => 'Invites not enough',
    ],
    'send_allow_text' => 'Invite someone',
];
