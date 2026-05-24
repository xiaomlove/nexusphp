<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
stdhead("Video Formats");
?>
<table class=main width=940 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<h2>Downloaded a movie and don't know what CAM/TS/TC/SCR means?</h2>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

<b>CAM -</b><br />
<br />
A cam is a theater rip usually done with a digital video camera. A mini tripod is
sometimes used, but a lot of the time this wont be possible, so the camera make shake.
Also seating placement isn't always idle, and it might be filmed from an angle.
If cropped properly, this is hard to tell unless there's text on the screen, but a lot
of times these are left with triangular borders on the top and bottom of the screen.
Sound is taken from the onboard microphone of the camera, and especially in comedies,
laughter can often be heard during the film. Due to these factors picture and sound
quality are usually quite poor, but sometimes we're lucky, and the theater will be'
fairly empty and a fairly clear signal will be heard.<br />
<br />
<br />
<b>TELESYNC (TS) -</b><br />
<br />
A telesync is the same spec as a CAM except it uses an external audio source (most
likely an audio jack in the chair for hard of hearing people). A direct audio source
does not ensure a good quality audio source, as a lot of background noise can interfere.
A lot of the times a telesync is filmed in an empty cinema or from the projection booth
with a professional camera, giving a better picture quality. Quality ranges drastically,
check the sample before downloading the full release. A high percentage of Telesyncs
are CAMs that have been mislabeled.<br />
<br />
<br />
<b>TELECINE (TC) -</b><br />
<br />
A telecine machine copies the film digitally from the reels. Sound and picture should
be very good, but due to the equipment involved and cost telecines are fairly uncommon.
Generally the film will be in correct aspect ratio, although 4:3 telecines have existed.
A great example is the JURASSIC PARK 3 TC done last year. TC should not be confused with
TimeCode , which is a visible counter on screen throughout the film.<br />
<br />
<br />
<b>SCREENER (SCR) -</b><br />
<br />
A pre VHS tape, sent to rental stores, and various other places for promotional use.
A screener is supplied on a VHS tape, and is usually in a 4:3 (full screen) a/r, although
letterboxed screeners are sometimes found. The main draw back is a "ticker" (a message
that scrolls past at the bottom of the screen, with the copyright and anti-copy
telephone number). Also, if the tape contains any serial numbers, or any other markings
that could lead to the source of the tape, these will have to be blocked, usually with a
black mark over the section. This is sometimes only for a few seconds, but unfortunately
on some copies this will last for the entire film, and some can be quite big. Depending
on the equipment used, screener quality can range from excellent if done from a MASTER
copy, to very poor if done on an old VHS recorder thru poor capture equipment on a copied
tape. Most screeners are transferred to VCD, but a few attempts at SVCD have occurred,
some looking better than others.<br />
<br />
<br />
<b>DVD-SCREENER (DVDscr) -</b><br />
<br />
Same premise as a screener, but transferred off a DVD. Usually letterbox , but without
the extras that a DVD retail would contain. The ticker is not usually in the black bars,
and will disrupt the viewing. If the ripper has any skill, a DVDscr should be very good.
Usually transferred to SVCD or DivX/XviD.<br />
<br />
<br />
<b>DVDRip -</b><br />
<br />
A copy of the final released DVD. If possible this is released PRE retail (for example,
Star Wars episode 2) again, should be excellent quality. DVDrips are released in SVCD
and DivX/XviD.<br />
<br />
<br />
<b>VHSRip -</b><br />
<br />
Transferred off a retail VHS, mainly skating/sports videos and XXX releases.<br />
<br />
<br />
<b>TVRip -</b><br />
<br />
TV episode that is either from Network (capped using digital cable/satellite boxes are
preferable) or PRE-AIR from satellite feeds sending the program around to networks a few
days earlier (do not contain "dogs" but sometimes have flickers etc) Some programs such
as WWF Raw Is War contain extra parts, and the "dark matches" and camera/commentary
tests are included on the rips. PDTV is capped from a digital TV PCI card, generally
giving the best results, and groups tend to release in SVCD for these. VCD/SVCD/DivX/XviD
rips are all supported by the TV scene.<br />
<br />
<br />
<b>WORKPRINT (WP) -</b><br />
<br />
A workprint is a copy of the film that has not been finished. It can be missing scenes,
music, and quality can range from excellent to very poor. Some WPs are very different
from the final print (Men In Black is missing all the aliens, and has actors in their
places) and others can contain extra scenes (Jay and Silent Bob) . WPs can be nice
additions to the collection once a good quality final has been obtained.<br />
<br />
<br />
<b>DivX Re-Enc -</b><br />
<br />
A DivX re-enc is a film that has been taken from its original VCD source, and re-encoded
into a small DivX file. Most commonly found on file sharers, these are usually labeled
something like Film.Name.Group(1of2) etc. Common groups are SMR and TND. These aren't
really worth downloading, unless you're that unsure about a film u only want a 200mb copy
of it. Generally avoid.<br />
<br />
<br />
<b>Watermarks -</b><br />
<br />
A lot of films come from Asian Silvers/PDVD (see below) and these are tagged by the
people responsible. Usually with a letter/initials or a little logo, generally in one
of the corners. Most famous are the "Z" "A" and "Globe" watermarks.<br />
<br />
<br />
<b>Asian Silvers / PDVD -</b><br />
<br />
These are films put out by eastern bootleggers, and these are usually bought by some
groups to put out as their own. Silvers are very cheap and easily available in a lot of
countries, and its easy to put out a release, which is why there are so many in the scene
at the moment, mainly from smaller groups who don't last more than a few releases. PDVDs
are the same thing pressed onto a DVD. They have removable subtitles, and the quality is
usually better than the silvers. These are ripped like a normal DVD, but usually released
as VCD.<br />
<br />
<br />
<b>Scene Tags...</b><br />
<br />
<b>PROPER -</b><br />
<br />
Due to scene rules, whoever releases the first Telesync has won that race (for example).
But if the quality of that release is fairly poor, if another group has another telesync
(or the same source in higher quality) then the tag PROPER is added to the folder to
avoid being duped. PROPER is the most subjective tag in the scene, and a lot of people
will generally argue whether the PROPER is better than the original release. A lot of
groups release PROPERS just out of desperation due to losing the race. A reason for the
PROPER should always be included in the NFO.<br />
<br />
<br />
<b>LIMITED -</b><br />
<br />
A limited movie means it has had a limited theater run, generally opening in less than
250 theaters, generally smaller films (such as art house films) are released as limited.<br />
<br />
<br />
<b>INTERNAL -</b><br />
<br />
An internal release is done for several reasons. Classic DVD groups do a lot of INTERNAL
releases, as they wont be dupe'd on it. Also lower quality theater rips are done INTERNAL
so not to lower the reputation of the group, or due to the amount of rips done already.
An INTERNAL release is available as normal on the groups affiliate sites, but they can't
be traded to other sites without request from the site ops. Some INTERNAL releases still
trickle down to IRC/Newsgroups, it usually depends on the title and the popularity.
Earlier in the year people referred to Centropy going "internal". This meant the group
were only releasing the movies to their members and site ops. This is in a different
context to the usual definition.<br />
<br />
<br />
<b>STV -</b><br />
<br />
Straight To Video. Was never released in theaters, and therefore a lot of sites do not
allow these.<br />
<br />
<br />
<b>ASPECT RATIO TAGS -</b><br />
<br />
These are *WS* for widescreen (letterbox) and *FS* for Fullscreen.<br />
<br />
<br />
<b>REPACK -</b><br />
<br />
If a group releases a bad rip, they will release a Repack which will fix the problems.<br />
<br />
<br />
<b>NUKED -</b><br />
<br />
A film can be nuked for various reasons. Individual sites will nuke for breaking their
rules (such as "No Telesyncs") but if the film has something extremely wrong with it
(no soundtrack for 20mins, CD2 is incorrect film/game etc) then a global nuke will occur,
and people trading it across sites will lose their credits. Nuked films can still reach
other sources such as p2p/usenet, but its a good idea to check why it was nuked first in
case. If a group realise there is something wrong, they can request a nuke.<br />
<br />
<br />
<b>NUKE REASONS...</b><br />
<br />
this is a list of common reasons a film can be nuked for (generally DVDRip)<br />
<br />
<b>BAD A/R</b> = bad aspect ratio, ie people appear too fat/thin<br />
<b>BAD IVTC</b> = bad inverse telecine. process of converting framerates was incorrect.<br />
<b>INTERLACED</b> = black lines on movement as the field order is incorrect.<br />
<br />
<br />
<b>DUPE -</b><br />
<br />
Dupe is quite simply, if something exists already, then theres no reason for it to exist
again without proper reason.<br />
<br />
<br />
</td></tr></table>
</td></tr></table>
<br />
<?php
stdfoot();
