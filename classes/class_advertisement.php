<?php

use Nexus\Database\NexusDB;

class ADVERTISEMENT
{
    public $userid;

    public $showad;

    public $userrow = [];

    public $adrow = [];

    public function __construct($userid)
    {
        $this->userid = $userid;
        $this->set_userrow();
        $this->set_showad();
        $this->set_adrow();
    }

    public function set_userrow()
    {
        $userid = $this->userid;
        $row = get_user_row($userid);
        $this->userrow = $row;
    }

    public function enable_ad()
    {
        global $enablead_advertisement;
        if ($enablead_advertisement == 'yes') {
            return true;
        } else {
            return false;
        }
    }

    public function show_ad()
    {
        if (! $this->enable_ad()) {
            return false;
        } else {
            if ($this->userrow && $this->userrow['noad'] == 'yes') {
                return false;
            } else {
                return true;
            }
        }
    }

    public function set_showad()
    {
        $showad = $this->show_ad();
        $this->showad = $showad;
    }

    public function set_adrow()
    {
        global $Cache;
        if (! $arr = $Cache->get_value('current_ad_array')) {
            $arr = [];
            $now = date('Y-m-d H:i:s');
            $validpos = $this->get_validpos();
            foreach ($validpos as $pos) {
                $adarray = NexusDB::table('advertisements')
                    ->where('enabled', 1)
                    ->where('position', (string) $pos)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('starttime')->orWhere('starttime', '<', $now);
                    })
                    ->where(function ($q) use ($now) {
                        $q->whereNull('endtime')->orWhere('endtime', '>', $now);
                    })
                    ->orderBy('displayorder', 'asc')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->pluck('code')
                    ->all();
                $arr[$pos] = $adarray;
            }
            $Cache->cache_value('current_ad_array', $arr, 3600);
        }
        $this->adrow = $arr;
    }

    public function get_validpos()
    {
        return ['header', 'footer', 'belownav', 'belowsearchbox', 'torrentdetail', 'comment', 'interoverforums', 'forumpost', 'popup'];
    }

    public function get_ad($pos)
    {
        $validpos = $this->get_validpos();
        if (in_array($pos, $validpos) && $this->showad) {
            return $this->adrow[$pos];
        } else {
            return '';
        }
    }
}
