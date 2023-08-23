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
        'time_invited_begin' => 'Send time gte',
        'time_invited_end' => 'Send time lte',
    ],
    'send_deny_reasons' => [
        'invite_system_closed' => 'Invite system is closed',
        'no_permission' => 'Require :class or above to send invitations',
        'invite_not_enough' => 'Invites not enough',
    ],
    'send_allow_text' => 'Invite someone',
    'pre_register_username' => 'Pre-register username',
    'pre_register_username_help' => 'Username and email will not be changed when user registers with this invitation code',
    'require_pre_register_username' => "Pre-register username can't be empty",
];
