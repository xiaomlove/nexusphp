<?php

namespace App\Models;


class Team extends NexusModel
{
    public static function getLabelName()
    {
        return nexus_trans('searchbox.sub_category_team_label');
    }
}
