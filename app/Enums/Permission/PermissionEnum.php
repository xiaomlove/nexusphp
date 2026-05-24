<?php

namespace App\Enums\Permission;

enum PermissionEnum: string {
    case UPLOAD_TO_SPECIAL_SECTION = 'uploadspecial';
    case BE_ANONYMOUS = 'beanonymous';
    case TORRENT_VIEW_SPECIAL = 'view_special_torrent';
    case TORRENT_SET_HR = 'torrent_hr';
    case TORRENT_SET_PRICE = 'torrent-set-price';
    case TORRENT_SET_STICKY = 'torrentsticky';
    case TORRENT_MANAGE = 'torrentmanage';
    case TORRENT_APPROVAL_ALLOW_AUTOMATIC = 'torrent-approval-allow-automatic';
    case TORRENT_SET_SPECIAL_TAG = 'torrent-set-special-tag';
    case UPLOAD = 'upload';
    case MANAGE_USER_BASIC_INFO = "prfmanage";
    case MANAGE_USER_CONFIDENTIAL_INFO = "cruprfmanage";
    case VIEW_USER_CONFIDENTIAL_INFO = "userprofile";
    case VIEW_USER_HISTORY = "viewhistory";

}
