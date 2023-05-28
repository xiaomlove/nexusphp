<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
stdhead();
begin_main_frame();
insert_smilies_frame();
end_main_frame();
stdfoot();
