<?php

use App\Models\Faq;
use App\Models\Setting;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
// loggedinorreturn();

stdhead($lang_faq['head_faq']);
$Cache->new_page('faq', 900, true);
if (! $Cache->get_page()) {
    $Cache->add_whole_row();
    // make_folder("cache/" , get_langfolder_cookie());
    // cache_check ('faq');
    begin_main_frame();

    begin_frame($lang_faq['text_welcome_to'].$SITENAME.' - '.$SLOGAN);
    echo sprintf($lang_faq['text_welcome_content_one'].sprintf($lang_faq['text_welcome_content_two'], Setting::getSiteName(), Setting::getSiteName()));
    end_frame();

    $lang_id = get_guest_lang_id();
    $is_rulelang = NexusDB::table('language')->where('id', (int) $lang_id)->value('rule_lang');
    if (! $is_rulelang) {
        $lang_id = 6; // English
    }
    $res = Faq::query()->where('type', 'categ')->where('lang_id', $lang_id)->orderBy('order')->get()->toArray();
    foreach ($res as $arr) {
        $faq_categ[$arr['link_id']]['title'] = $arr['question'];
        $faq_categ[$arr['link_id']]['flag'] = $arr['flag'];
        $faq_categ[$arr['link_id']]['link_id'] = $arr['link_id'];
    }

    $res = Faq::query()->where('type', 'item')->where('lang_id', $lang_id)->get()->toArray();
    foreach ($res as $arr) {
        $faq_categ[$arr['categ']]['items'][$arr['id']]['question'] = $arr['question'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['answer'] = $arr['answer'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['flag'] = $arr['flag'];
        $faq_categ[$arr['categ']]['items'][$arr['id']]['link_id'] = $arr['link_id'];
    }

    if (isset($faq_categ)) {
        // gather orphaned items
        /*
        foreach ($faq_categ as $id => $temp)
        {
            if (!array_key_exists("title", $faq_categ[$id]))
            {
                foreach ($faq_categ[$id]['items'] as $id2 => $temp)
                {
                    $faq_orphaned[$id2]['question'] = $faq_categ[$id]['items'][$id2]['question'];
                    $faq_orphaned[$id2][answer] = $faq_categ[$id]['items'][$id2][answer];
                    $faq_orphaned[$id2]['flag'] = $faq_categ[$id]['items'][$id2]['flag'];
                    unset($faq_categ[$id]);
                }
            }
        }
        */

        begin_frame('<span id="top">'.$lang_faq['text_contents'].'</span>');
        foreach ($faq_categ as $id => $temp) {
            if ($faq_categ[$id]['flag'] == '1') {
                echo '<ul><li><a href="#id'.$faq_categ[$id]['link_id'].'"><b>'.$faq_categ[$id]['title']."</b></a><ul>\n";
                if (array_key_exists('items', $faq_categ[$id])) {
                    foreach ($faq_categ[$id]['items'] as $id2 => $temp) {
                        if ($faq_categ[$id]['items'][$id2]['flag'] == '1') {
                            echo '<li><a href="#id'.$faq_categ[$id]['items'][$id2]['link_id'].'" class="faqlink">'.$faq_categ[$id]['items'][$id2]['question']."</a></li>\n";
                        } elseif ($faq_categ[$id]['items'][$id2]['flag'] == '2') {
                            echo '<li><a href="#id'.$faq_categ[$id]['items'][$id2]['link_id'].'" class="faqlink">'.$faq_categ[$id]['items'][$id2]['question']."</a> <img class=\"faq_updated\" src=\"pic/trans.gif\" alt=\"Updated\" /></li>\n";
                        } elseif ($faq_categ[$id]['items'][$id2]['flag'] == '3') {
                            echo '<li><a href="#id'.$faq_categ[$id]['items'][$id2]['link_id'].'" class="faqlink">'.$faq_categ[$id]['items'][$id2]['question']."</a> <img class=\"faq_new\" src=\"pic/trans.gif\" alt=\"New\" /></li>\n";
                        }
                    }
                }
                echo '</ul></li></ul><br />';
            }
        }
        end_frame();

        foreach ($faq_categ as $id => $temp) {
            if ($faq_categ[$id]['flag'] == '1') {
                $frame = $faq_categ[$id]['title'].' - <a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="Top" /></a>';
                begin_frame($frame);
                echo '<span id="id'.$faq_categ[$id]['link_id'].'"></span>';
                if (array_key_exists('items', $faq_categ[$id])) {
                    foreach ($faq_categ[$id]['items'] as $id2 => $temp) {
                        if ($faq_categ[$id]['items'][$id2]['flag'] != '0') {
                            echo '<br /><span id="id'.$faq_categ[$id]['items'][$id2]['link_id'].'"><b>'.$faq_categ[$id]['items'][$id2]['question']."</b></span><br />\n";
                            echo '<br />'.$faq_categ[$id]['items'][$id2]['answer']."\n<br /><br />\n";
                        }
                    }
                }
                end_frame();
            }
        }
    }
    end_main_frame();
    $Cache->end_whole_row();
    $Cache->cache_page();
}
echo $Cache->next_row();
// cache_save ('faq');
stdfoot();
