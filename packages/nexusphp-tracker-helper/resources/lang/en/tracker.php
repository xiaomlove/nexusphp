<?php

return [
    'internal_host' => "Tracker's intranet address",
    'internal_host_help' => 'Full address with protocol + domain name + port, e.g. http://127.0.0.1:7777',
    'is_frontend_use_tracker_api' => 'Whether or not the frontend is using the Tracker interface',
    'is_frontend_use_tracker_api_help' => 'If it is used, the number of seeds/downloads on the seed list page, and the list of companions on the seed details page will use the Tracker interface to get real-time data. Otherwise the data displayed is slightly lagged in the database',

    'is_test_mode' => 'Whether or not in test mode',
    'is_test_mode_help' => "Requires traffic mirroring configuration in Nginx, don't enable it if you're not sure what it is",
    'test_mode_uid' => 'Test user UID',
    'test_mode_uid_help' => 'One line one or English comma separated. In test mode, the data of users located in this list are synchronized to the database, and the data of other users are not synchronized. In non-test mode the data here is ignored and all user data is synchronized to the database at regular intervals.',

    'alarm_subject' => 'Go-Tracker Service Exception',
    'alarm_content' => "The :alarm_count alarm!<br/>API: :status_api is not returning properly, please check! <br/> The latest status is as follows:<br/> :latest_status",
];
