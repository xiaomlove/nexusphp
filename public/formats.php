<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
stdhead("Downloaded Files");
?>
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<h2>A Handy Guide to Using the Files You've Downloaded</h2>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

Hey guys, here's some info about common files that you can download from the internet,
and a little bit about using these files for their intended purposes. If you're stuck
on what exactly a file is or how to open it maybe your answer lies ahead. If you dont'
find your answer here, then please post in the "Forum". So without further adieu lets
get the show on the road!<br />
</td></tr></table>
</td></tr></table>
<br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<h2>Compression Files</h2>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

<b>.rar .zip .ace .r01 .001</b><br />
<br />
These extensions are quite common and mean that your file(s) are compressed into an "archive".<br />
This is just a way of making the files more compact and easier to download.<br />
<br />
To open any of those archives listed above you can use <a href="http://www.rarsoft.com/download.htm">WinRAR</a> (Make sure you have the latest version) or <a href="http://www.powerarchiver.com/download/">PowerArchiver</a>.<br />
<br />
If those progams aren't working for you and you have a .zip file you can try 
<a href="http://www.winzip.com/download.htm">WinZip</a> (Trial version).<br />
<br />
If the two first mentioned programs aren't working for you and you have a .ace or .001
file you can try <a href="http://www.winace.com/">Winace</a> (Trial version).<br />
<br />
<br /> 
<b>.cbr .cbz</b><br />
<br />
These are usually comic books in an archive format. a .cbr file is actually the same
thing as a .rar file and a .cbz file is the same as a .zip file. However, often when
opening them with WinRAR or WinZip it will disorder your pages. To display these
archives properly it's often best to use <a href="http://www.geocities.com/davidayton/CDisplay">
CDisplay</a>.<br />
<br />
</td></tr></table>
</td></tr></table>
<br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<h2>Multimedia Files</h2>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

<b>.avi .mpg. .mpeg .divx .xvid .wmv</b><br />
<br />
These files are usually movies or TVshows, or a host of other types of media. They can
be viewed using various media players, but I suggest using
<a href="http://www.inmatrix.com/files/zoomplayer_download.shtml">Zoomplayer</a>,
<a href="http://www.bsplayer.org/">BSPlayer</a>, <a href="http://www.videolan.org/vlc/">VLC media player</a>
 or <a href="http://www.microsoft.com/windows/
windowsmedia/default.aspx">Windows Media Player</a>. Also, you'll need to make sure you have
the right codecs to play each individual file. Codecs are a tricky business sometimes so to help
you out with your file and what exact codecs it needs try using <a href="http://www.headbands.com/
gspot/download.html">GSpot</a>. It tells you what codecs you need. Then just look on the net to find
them, below are some common codecs and their download links for quick reference:<br />
<br />
<a href="http://sourceforge.net/project/showfiles.php?group_id=53761&release_id=95213">ffdshow</a> (Recommended! (plays many formats: XviD, DivX, 3ivX, mpeg-4))<br />
<a href="http://nic.dnsalias.com/xvid.html">XviD codec</a><br />
<a href="http://www.divx.com/divx/">DivX codec</a><br />
<a href="http://sourceforge.net/project/showfiles.php?group_id=66022&release_id=178906">ac3filter</a> (for AC3 soundtracks, aka "5.1")<br />
<a href="http://tobias.everwicked.com/oggds.htm">Ogg media codec</a> (for .OGM files)<br />
<br />
Can't find what you're looking for? Check out these sites...<br />
<br />
<a href="http://www.divx-digest.com/">DivX-Digest</a><br />
<a href="http://www.digital-digest.com/">Digital-Digest</a><br />
<a href="http://www.doom9.org/">Doom9</a><br />
<a href="http://www.dvdrhelp.com/">DVD-R Help</a><br />
<br />
<br />
<b>.mov</b><br />
<br />
These are <a href="http://www.apple.com/quicktime/">QuickTime</a> files. Hopefully you
won't have to open these as I hate quicktime, but if you do you can
<a href="http://www.apple.com/quicktime/download/">get it here</a>.
There are however alternatives to the original program,
Check out <a href="http://home.hccnet.nl/h.edskes/finalbuilds.htm">QuickTime Alternative</a>.<br />
<br />
<br />
<b>.ra .rm .ram</b><br />
<br />
These are <a href="http://www.real.com">RealPlayer</a> files. RealPlayer IMO is the
devils work. It installs lord knows what on your system and never really goes away when
you want to uninstall it. Still if you insists you can get the player
<a href="http://service.real.com/downloads.html">here</a>.
There are however alternatives to the original program,
check out <a href="http://home.hccnet.nl/h.edskes/finalbuilds.htm">Real Alternative</a>.<br />
<br />
<br />
<b>vcd/svcd</b><br />
<br />
These can be a pain on some peoples setups, but more so, on your stand-alone DVD player.
For all your vcd needs check out <a href="http://www.dvdrhelp.com">www.dvdrhelp.com</a>.
These guys know their stuff, and can help you with all kinds of media related questions.<br />
<br />
<br />
<b>.mp3 .mp2</b><br />
<br />
Usually music files. Play them with <a href="http://www.winamp.com/">WinAmp</a>.<br />
<br />
<br />
<b>.ogm .ogg</b><br />
<br />
Ogg Vorbis media files. You can find out more about them and download applications
<a href="http://www.vorbis.com/download.psp">here</a>.
This filetype is another music file format, but can be used for various media. You will
probably want to download the <a href="http://tobias.everwicked.com/oggds.htm">
DirectShow Ogg filter</a> to play back OGM files. Any new version of
<a href="http://www.winamp.com">WinAmp</a> will also do.<br />
<br />
</td></tr></table>
</td></tr></table>
<br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<h2>CD Image Files</h2>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

<b>.bin and .cue</b><br />
<br />
These are your standard images of a CD, and are used quite alot these days. To open them
you have a couple options. You can burn them using <a href="http://www.ahead.de">Nero</a>
(Trial Version) or <a href="http://www.alcohol-software.com/">Alcohol 120%</a>,
but this proves to be soooooooo problematic for a lot of people. You should also consult
this tutorial for burning images with various software programs You can also use
<a href="http://www.daemon-tools.cc/portal/portal.php">Daemon Tools</a>, which lets you
mount the image to a "virtual cd-rom", so basically it tricks your computer into thinking
that you have another cd-rom and that you're putting a cd with your image file on it into
this virtual cd-rom, it's great cuz you'll never make a bad cd again, Alcohol 120% also
sports a virtual cd-rom feature. Finally, if you're still struggling to access the files
contained within any given image file you can use <a href="http://cdmage.cjb.net/">CDMage</a>
to extract the files and then burn them, or just access them from your hard drive. You can
also use <a href="http://www.vcdgear.com/">VCDGear</a> to extract the mpeg contents of a
SVCD or VCD image file such as bin/cue.<br />
<br />
<br />
<b>.iso</b><br />
<br />
Another type of image file that follows similar rules as .bin and .cue, only you extract
or create them using <a href="http://www.winiso.com">WinISO</a> or
<a href="http://ww.smart-projects.net/isobuster/">ISOBuster.</a> Sometimes converting a
problematic .bin and .cue file to an .iso can help you burn it to a cd.<br />
<br />
<br />
<b>.ccd .img .sub</b><br />
<br />
All these files go together and are in the <a href="http://www.elby.ch/english/products/
clone_cd/index.html"> CloneCD</a> format. CloneCD is like most other CD-Burning programs,
see the .bin and .cue section if you're having problems with these files.<br />
<br />
</td></tr></table>
</td></tr></table>
<br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<h2>Other Files</h2>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

<b>.txt .doc</b><br />
<br />
These are text files. .txt files can be opened with notepad or watever you default text
editor happens to be, and .doc are opened with Microsoft Word.<br />
<br />
<br />
<b>.nfo</b><br />
<br />
These contain information about the file you just downloaded, and it's HIGHLY recommended
that you read these! They are plain text files, often with ascii-art. You can open them
with Notepad, Wordpad, <a href="http://www.damn.to/software/nfoviewer.html">DAMN NFO Viewer</a>
or <a href="http://www.ultraedit.com/">UltraEdit</a>.<br />
<br />
<br />
<b>.pdf</b><br />
<br />
Opened with <a href="http://www.adobe.com/products/acrobat/main.html">Adobe Acrobat Reader</a>.<br />
<br />
<br />
<b>.jpg .gif .tga .psd</b><br />
<br />
Basic image files. These files generally contain pictures, and can be opened with Adobe
Photoshop or whatever your default image viewer is.<br />
<br />
<br />
<b>.sfv</b><br />
<br />
Checks to make sure that your multi-volume archives are complete. This just lets you know
if you've downloaded something complete or not. (This is not really an issue when DL:ing
via torrent.) You can open/activate these files with <a href="http://www.traction-software.co.uk/SFVChecker/">
SFVChecker</a> (Trial version) or <a href="http://www.big-o-software.com/products/hksfv/">hkSFV</a> for example.<br />
<br />
<br />
<p><b>.par</b></p>
This is a parity file, and is often used when downloading from newsgroups. These files can
fill in gaps when you're downloading a multi-volume archive and get corrupted or missing parts.
Open them with <a href="http://www.pbclements.co.uk/QuickPar/">QuickPar</a>.
<br />
</td></tr></table>
</td></tr></table>
<br />
<table class=main width=737 border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
<table width=100% border=1 cellspacing=0 cellpadding=10><tr><td class=text> 

If you have any suggestion/changes <a href=staff.php><b>PM</b></a> one of the Admins/SysOp!<br />
<br />
This file was originally written by hussdiesel at filesoup, then edited by Rhomboid and re-edited by us.<br />
</td></tr></table>
</td></tr></table>
<br />
<?php
