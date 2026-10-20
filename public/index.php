<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path());
loggedinorreturn(true);
$userid = $CURUSER["id"];
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($showpolls_main == "yes")
	{
		$choice = $_POST["choice"];
		if ($CURUSER && $choice != "" && $choice < 256 && $choice == floor($choice))
		{
			$res = sql_query("SELECT * FROM polls ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_assoc($res) or die($lang_index['std_no_poll']);
			$pollid = $arr["id"];

			$hasvoted = get_row_count("pollanswers","WHERE pollid=".sqlesc($pollid)." && userid=".sqlesc($CURUSER["id"]));
			if ($hasvoted)
				stderr($lang_index['std_error'],$lang_index['std_duplicate_votes_denied']);
			sql_query("INSERT INTO pollanswers VALUES(0, ".sqlesc($pollid).", ".sqlesc($CURUSER["id"]).", ".sqlesc($choice).")") or sqlerr(__FILE__, __LINE__);
			$Cache->delete_value('current_poll_content');
			$Cache->delete_value('current_poll_result', true);
			if (mysql_affected_rows() != 1)
			stderr($lang_index['std_error'], $lang_index['std_vote_not_counted']);
			//add karma
			KPS("+",$pollvote_bonus,$userid);

			header("Location: " . get_protocol_prefix() . "$BASEURL/");
			die;
		}
		else
		stderr($lang_index['std_error'], $lang_index['std_option_unselected']);
	}
}
stdhead($lang_index['head_home']);
begin_main_frame();

// ------------- start: Codex home poster wall ------------------//
$codexOfficialTagId = 3;
$codexPosterRows = array();
$codexPosterLimit = 50;
if (!empty($CURUSER['torrentsperpage'])) {
    $codexPosterLimit = (int)$CURUSER['torrentsperpage'];
} elseif (!empty($torrentsperpage_main)) {
    $codexPosterLimit = (int)$torrentsperpage_main;
}
$codexPosterLimit = max(12, min(80, $codexPosterLimit));
$codexPosterNormalizeTitle = static function ($name) {
    $name = trim((string)$name);
    $normalized = preg_replace('/(?:[ ._-]*S\d{1,2}(?:E\d{1,4}(?:[-_]?E?\d{1,4})?)?\b|[ ._-]*第\s*\d{1,4}(?:[-~至]\d{1,4})?\s*集)/iu', '', $name);
    $normalized = preg_replace('/[ ._-]+$/u', '', (string)$normalized);
    return trim((string)$normalized);
};
$codexPosterSql = "SELECT t.id, t.name, t.small_descr, t.cover, t.seeders
    FROM torrents AS t
    INNER JOIN (
        SELECT MAX(t2.id) AS id
        FROM torrents AS t2
        INNER JOIN torrent_tags AS tt2 ON t2.id = tt2.torrent_id
        WHERE t2.visible = 'yes'
          AND t2.banned = 'no'
          AND t2.cover <> ''
          AND tt2.tag_id = " . sqlesc($codexOfficialTagId) . "
        GROUP BY t2.cover
        ORDER BY MAX(t2.added) DESC
        LIMIT " . (int)$codexPosterLimit . "
    ) AS picked ON t.id = picked.id
    ORDER BY t.added DESC";
$codexCacheFile=sys_get_temp_dir()."/nexus_codex_poster.cache";
$codexPosterRows=array();
if(is_file($codexCacheFile) && time()-filemtime($codexCacheFile)<300) $codexPosterRows=@unserialize(file_get_contents($codexCacheFile));
if(!is_array($codexPosterRows)||!$codexPosterRows){$codexPosterRows=array();$codexPosterResult=sql_query($codexPosterSql) or sqlerr(__FILE__,__LINE__);while($codexPosterRow=mysql_fetch_assoc($codexPosterResult))$codexPosterRows[]=$codexPosterRow;@file_put_contents($codexCacheFile,serialize($codexPosterRows),LOCK_EX);}
$codexSeenCovers=array();
$codexSeenPosterGroups=array();
foreach($codexPosterRows as $codexPosterRow){
    $codexCoverKey = trim($codexPosterRow['cover']);
    if ($codexCoverKey == '' || isset($codexSeenCovers[$codexCoverKey])) continue;
    $codexSeenCovers[$codexCoverKey] = true;
    $codexOriginalTitle = trim((string)$codexPosterRow['name']);
    $codexGroupTitle = $codexPosterNormalizeTitle($codexOriginalTitle);
    $codexIsEpisode = $codexGroupTitle !== $codexOriginalTitle;
    $codexGroupKey = strtolower($codexIsEpisode ? 'tv:' . $codexGroupTitle : 'title:' . $codexOriginalTitle);
    if (isset($codexSeenPosterGroups[$codexGroupKey])) continue;
    $codexSeenPosterGroups[$codexGroupKey] = true;
    $codexPosterRow['poster_group_title'] = $codexIsEpisode ? $codexGroupTitle : $codexOriginalTitle;
    $codexPosterRow['poster_group_is_episode'] = $codexIsEpisode;
    $codexPosterRows[] = $codexPosterRow;
}
if (count($codexPosterRows) > 0) {
    $codexHeroRows = array_slice($codexPosterRows, 0, min(8, count($codexPosterRows)));
    $codexStripRows = $codexPosterRows;
    ?>
    <style type="text/css">
        .nova-poster-wall {
            width: 1455px;
            max-width: 100%;
            box-sizing: border-box;
            margin: 0 auto 18px auto;
            color: #fff;
            font-family: "Inter", "Segoe UI", "Microsoft YaHei", Arial, sans-serif;
        }
        .nova-poster-hero {
            position: relative;
            width: 100%;
            aspect-ratio: 1164 / 650;
            min-height: 0;
            overflow: hidden;
            border-radius: 16px;
            background: #0d1720;
            box-shadow: 0 16px 42px rgba(15, 35, 45, .20);
            isolation: isolate;
        }
        .nova-poster-slide {
            position: absolute;
            inset: 0;
            display: flex;
            align-items: center;
            padding: 44px 98px 119px 98px;
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            opacity: 0;
            transform: scale(1.012);
            transition: opacity .28s ease, transform .45s ease;
            pointer-events: none;
        }
        .nova-poster-slide.is-active {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }
        .nova-poster-bg {
            display: none !important;
        }
        .nova-poster-slide::before {
            content: "";
            position: absolute;
            inset: 0;
            z-index: 1;
            background:
                radial-gradient(circle at 72% 36%, rgba(255,255,255,.10), transparent 24%),
                linear-gradient(90deg, rgba(5, 10, 18, .90) 0%, rgba(5, 10, 18, .64) 45%, rgba(5, 10, 18, .30) 100%);
        }
        .nova-poster-copy {
            position: relative;
            z-index: 3;
            width: 50%;
            max-width: 813px;
            min-width: 469px;
            text-shadow: 0 3px 16px rgba(0,0,0,.58);
        }
        .nova-poster-frame {
            display: none !important;
        }
        .nova-poster-frame img {
            display: none !important;
        }
        .nova-poster-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 6px 14px;
            margin-bottom: 13px;
            border-radius: 999px;
            background: rgba(255,255,255,.16);
            border: 1px solid rgba(255,255,255,.18);
            backdrop-filter: blur(10px);
            font-size: 16px;
            letter-spacing: .06em;
        }
        .nova-poster-title {
            margin: 0 0 12px 0 !important;
            padding: 0 !important;
            color: #fff !important;
            font-size: 29px !important;
            line-height: 1.30 !important;
            font-weight: 850 !important;
            background: transparent !important;
            border: 0 !important;
            word-break: break-word;
        }
        .nova-poster-title::after { display: none !important; }
        .nova-poster-desc {
            max-width: 538px;
            margin: 0 0 19px 0;
            color: rgba(255,255,255,.86);
            font-size: 16px;
            line-height: 1.58;
            max-height: 90px;
            overflow: hidden;
            word-break: break-word;
        }
        .nova-poster-more {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 169px;
            height: 56px;
            padding: 0 29px;
            border-radius: 16px;
            background: rgba(255,255,255,.20);
            border: 1px solid rgba(255,255,255,.24);
            color: #fff !important;
            font-size: 20px;
            font-weight: 800;
            text-decoration: none !important;
            backdrop-filter: blur(12px);
            box-shadow: 0 10px 22px rgba(0,0,0,.18);
        }
        .nova-poster-more:hover {
            background: rgba(255,255,255,.30);
            color: #fff !important;
            transform: translateY(-1px);
        }
        .nova-poster-arrow {
            position: absolute;
            top: 50%;
            z-index: 8;
            width: 50px;
            height: 90px;
            margin-top: -45px;
            border: 0;
            border-radius: 14px;
            background: rgba(0,0,0,.50) !important;
            color: #fff !important;
            font-size: 0 !important;
            line-height: 1;
            cursor: pointer;
            box-shadow: none !important;
            padding: 0 !important;
        }
        .nova-poster-arrow::before {
            display: block;
            font-size: 48px;
            font-weight: 900;
            line-height: 90px;
        }
        .nova-poster-prev::before { content: "\2039"; }
        .nova-poster-next::before { content: "\203A"; }
        .nova-poster-arrow:hover { background: rgba(0,0,0,.68) !important; transform: none !important; }
        .nova-poster-prev { left: 16px; }
        .nova-poster-next { right: 16px; }
        .nova-poster-thumbs {
            position: absolute;
            z-index: 7;
            right: 29px;
            bottom: 23px;
            display: flex;
            align-items: flex-end;
            gap: 10px;
            max-width: calc(100% - 394px);
        }
        .nova-poster-thumb {
            width: 70px;
            padding: 0;
            border: 0;
            background: transparent !important;
            color: rgba(255,255,255,.82) !important;
            box-shadow: none !important;
            cursor: pointer;
            transform: none !important;
        }
        .nova-poster-thumb img {
            width: 70px;
            height: 104px;
            object-fit: cover;
            border-radius: 9px;
            box-shadow: 0 10px 20px rgba(0,0,0,.30);
            opacity: .76;
            transition: opacity .18s ease, transform .18s ease, box-shadow .18s ease;
        }
        .nova-poster-thumb span {
            display: block;
            margin-top: 4px;
            font-size: 13px;
            line-height: 1.16;
            height: 34px;
            overflow: hidden;
            text-shadow: 0 2px 8px rgba(0,0,0,.65);
            word-break: break-word;
        }
        .nova-poster-thumb.is-active img,
        .nova-poster-thumb:hover img {
            opacity: 1;
            transform: translateY(-3px);
            box-shadow: 0 15px 26px rgba(0,0,0,.38), 0 0 0 2px rgba(255,255,255,.72);
        }
        .nova-poster-strip-wrap {
            position: relative;
            margin-top: 19px;
        }
        .nova-poster-strip-viewport {
            overflow: hidden;
            padding: 0 56px 13px 56px;
        }
        .nova-poster-strip {
            display: flex;
            gap: 16px;
            transition: none;
            will-change: transform;
        }
        .nova-poster-strip.is-marquee-paused {
            transition: transform .18s ease;
        }
        .nova-poster-card {
            flex: 0 0 148px;
            color: #102a35 !important;
            text-decoration: none !important;
        }
        .nova-poster-card .nova-poster-card-img {
            width: 148px;
            aspect-ratio: 9 / 16;
            border-radius: 12px;
            background-position: center center;
            background-size: cover;
            background-repeat: no-repeat;
            background-color: #0d1720;
            box-shadow: 0 10px 22px rgba(15, 35, 45, .16);
            transition: transform .18s ease, box-shadow .18s ease;
            overflow: hidden;
        }
        .nova-poster-card:hover .nova-poster-card-img {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(15, 35, 45, .22);
        }
        .nova-poster-card span {
            display: block;
            margin-top: 8px;
            color: #17313a;
            font-size: 13px;
            font-weight: 700;
            line-height: 1.22;
            min-height: 38px;
            max-height: 75px;
            overflow: hidden;
            text-align: center;
            word-break: break-word;
        }
        .nova-poster-strip-btn {
            position: absolute;
            top: 81px;
            z-index: 3;
            width: 44px;
            height: 75px;
            border: 0;
            border-radius: 13px;
            background: rgba(15, 35, 45, .72) !important;
            color: #fff !important;
            box-shadow: none !important;
            cursor: pointer;
            font-size: 0 !important;
            padding: 0 !important;
            transform: none !important;
        }
        .nova-poster-strip-btn::before {
            display: block;
            font-size: 38px;
            font-weight: 900;
            line-height: 75px;
        }
        .nova-poster-strip-prev { left: 0; }
        .nova-poster-strip-next { right: 0; }
        .nova-poster-strip-prev::before { content: "\2039"; }
        .nova-poster-strip-next::before { content: "\203A"; }
        .nova-poster-strip-btn:disabled {
            opacity: .28;
            cursor: default;
        }
        @media (max-width: 900px) {
            .nova-poster-wall { width: 100%; max-width: 100%; }
            .nova-poster-slide { padding: 24px 42px 90px 42px; }
            .nova-poster-copy { width: auto; min-width: 0; max-width: 62%; }
            .nova-poster-frame { display: none !important; }
            .nova-poster-title { font-size: 15px !important; }
            .nova-poster-desc { font-size: 11px; max-height: 46px; }
            .nova-poster-more { min-width: 92px; height: 32px; font-size: 12px; }
            .nova-poster-arrow { width: 28px; height: 50px; margin-top: -25px; border-radius: 12px; }
            .nova-poster-arrow::before { font-size: 25px; line-height: 50px; }
            .nova-poster-prev { left: 7px; }
            .nova-poster-next { right: 7px; }
            .nova-poster-thumbs { left: 40px; right: 40px; bottom: 12px; max-width: none; overflow: hidden; gap: 7px; }
            .nova-poster-thumb { width: 40px; }
            .nova-poster-thumb img { width: 40px; height: 58px; border-radius: 8px; }
            .nova-poster-thumb span { display: none; }
            .nova-poster-strip-viewport { padding: 0 30px 8px 30px; }
            .nova-poster-card { flex-basis: 78px; }
            .nova-poster-card .nova-poster-card-img { width: 78px; aspect-ratio: 9 / 16; }
            .nova-poster-card span { font-size: 10px; }
            .nova-poster-strip-btn { top: 40px; width: 24px; height: 42px; }
            .nova-poster-strip-btn::before { font-size: 21px; line-height: 42px; }
        }
    </style>
    <div class="nova-poster-wall" id="novaPosterWall">
        <div class="nova-poster-hero">
            <?php foreach ($codexHeroRows as $codexIndex => $codexPoster) {
                $codexCover = htmlspecialchars($codexPoster['cover'], ENT_QUOTES);
                $codexTitleRaw = (string)($codexPoster['poster_group_title'] ?? $codexPoster['name']);
                $codexTitle = htmlspecialchars($codexTitleRaw, ENT_QUOTES);
                $codexDescRaw = trim($codexPoster['small_descr']) != '' ? trim($codexPoster['small_descr']) : $codexPoster['name'];
                $codexDesc = htmlspecialchars($codexDescRaw, ENT_QUOTES);
                $codexPosterHref = !empty($codexPoster['poster_group_is_episode'])
                    ? 'torrents.php?search=' . rawurlencode($codexTitleRaw) . '&amp;search_area=0&amp;search_mode=0&amp;notnewword=1'
                    : 'details.php?id=' . (int)$codexPoster['id'] . '&amp;hit=1';
                $codexActive = $codexIndex == 0 ? ' is-active' : '';
            ?>
                <div class="nova-poster-slide<?php echo $codexActive ?>" data-nova-slide="<?php echo $codexIndex ?>" style="background-image: url('<?php echo $codexCover ?>');">
                    <div class="nova-poster-bg" style="background-image: url('<?php echo $codexCover ?>');"></div>
                    <div class="nova-poster-copy">
                        <div class="nova-poster-eyebrow">&#x5B98;&#x79CD; &#8594; POSTER WALL</div>
                        <h2 class="nova-poster-title"><?php echo $codexTitle ?></h2>
                        <p class="nova-poster-desc"><?php echo $codexDesc ?></p>
                        <a class="nova-poster-more" href="<?php echo $codexPosterHref ?>"><?php echo !empty($codexPoster['poster_group_is_episode']) ? '&#x67E5;&#x770B;&#x5168;&#x90E8;&#x96C6;&#x6570;' : '&#x67E5;&#x770B;&#x8BE6;&#x60C5;' ?></a>
                    </div>
                    <a class="nova-poster-frame" href="<?php echo $codexPosterHref ?>">
                        <img src="<?php echo $codexCover ?>" alt="<?php echo $codexTitle ?>" loading="lazy" />
                    </a>
                </div>
            <?php } ?>
            <button type="button" class="nova-poster-arrow nova-poster-prev" aria-label="Previous">&#8249;</button>
            <button type="button" class="nova-poster-arrow nova-poster-next" aria-label="Next">&#8250;</button>
            <div class="nova-poster-thumbs">
                <?php foreach ($codexHeroRows as $codexIndex => $codexPoster) {
                    $codexCover = htmlspecialchars($codexPoster['cover'], ENT_QUOTES);
                    $codexTitle = htmlspecialchars($codexPoster['name'], ENT_QUOTES);
                    $codexActive = $codexIndex == 0 ? ' is-active' : '';
                ?>
                    <button type="button" class="nova-poster-thumb<?php echo $codexActive ?>" data-nova-go="<?php echo $codexIndex ?>">
                        <img src="<?php echo $codexCover ?>" alt="<?php echo $codexTitle ?>" loading="lazy" />
                    </button>
                <?php } ?>
            </div>
        </div>
        <div class="nova-poster-strip-wrap">
            <button type="button" class="nova-poster-strip-btn nova-poster-strip-prev" aria-label="Previous page">&#8249;</button>
            <div class="nova-poster-strip-viewport">
                <div class="nova-poster-strip">
                    <?php foreach ($codexStripRows as $codexPoster) {
                        $codexCover = htmlspecialchars($codexPoster['cover'], ENT_QUOTES);
                        $codexTitleRaw = (string)($codexPoster['poster_group_title'] ?? $codexPoster['name']);
                        $codexTitle = htmlspecialchars($codexTitleRaw, ENT_QUOTES);
                        $codexPosterHref = !empty($codexPoster['poster_group_is_episode'])
                            ? 'torrents.php?search=' . rawurlencode($codexTitleRaw) . '&amp;search_area=0&amp;search_mode=0&amp;notnewword=1'
                            : 'details.php?id=' . (int)$codexPoster['id'] . '&amp;hit=1';
                    ?>
                        <a class="nova-poster-card" href="<?php echo $codexPosterHref ?>" title="<?php echo $codexTitle ?>">
                            <div class="nova-poster-card-img" style="background-image: url('<?php echo $codexCover ?>');"></div>
                        </a>
                    <?php } ?>
                </div>
            </div>
            <button type="button" class="nova-poster-strip-btn nova-poster-strip-next" aria-label="Next page">&#8250;</button>
        </div>
    </div>
    <script type="text/javascript">
    (function () {
        var root = document.getElementById('novaPosterWall');
        if (!root) return;
        var slides = root.querySelectorAll('.nova-poster-slide');
        var thumbs = root.querySelectorAll('.nova-poster-thumb');
        var current = 0;
        var heroTimer = null;
        var heroDelay = 5000;
        function show(index) {
            if (!slides.length) return;
            current = (index + slides.length) % slides.length;
            for (var i = 0; i < slides.length; i++) {
                slides[i].className = slides[i].className.replace(/\s?is-active/g, '') + (i === current ? ' is-active' : '');
                if (thumbs[i]) thumbs[i].className = thumbs[i].className.replace(/\s?is-active/g, '') + (i === current ? ' is-active' : '');
            }
        }
        function stopHeroAuto() {
            if (heroTimer) {
                window.clearInterval(heroTimer);
                heroTimer = null;
            }
        }
        function startHeroAuto() {
            stopHeroAuto();
            if (slides.length > 1) {
                heroTimer = window.setInterval(function () { show(current + 1); }, heroDelay);
            }
        }
        function manualShow(index) {
            show(index);
            startHeroAuto();
        }
        var prev = root.querySelector('.nova-poster-prev');
        var next = root.querySelector('.nova-poster-next');
        if (prev) prev.onclick = function () { manualShow(current - 1); };
        if (next) next.onclick = function () { manualShow(current + 1); };
        for (var t = 0; t < thumbs.length; t++) {
            thumbs[t].onclick = function () { manualShow(parseInt(this.getAttribute('data-nova-go'), 10) || 0); };
        }
        var hero = root.querySelector('.nova-poster-hero');
        if (hero) {
            hero.onmouseenter = stopHeroAuto;
            hero.onmouseleave = startHeroAuto;
        }
        startHeroAuto();

        var strip = root.querySelector('.nova-poster-strip');
        var cards = root.querySelectorAll('.nova-poster-card');
        var stripPrev = root.querySelector('.nova-poster-strip-prev');
        var stripNext = root.querySelector('.nova-poster-strip-next');
        var marqueeX = 0;
        var marqueeSpeed = 0.44;
        var marqueePaused = false;
        var marqueeFrame = null;
        function getStripGap() {
            if (!strip) return 0;
            var style = window.getComputedStyle(strip);
            return parseFloat(style.columnGap || style.gap || 0) || 0;
        }
        function cardStep() {
            if (!cards.length) return 0;
            return cards[0].offsetWidth + getStripGap();
        }
        function normalizeMarquee() {
            var step = cardStep();
            if (!step) return;
            while (marqueeX <= -step) {
                marqueeX += step;
                strip.appendChild(strip.children[0]);
                cards = strip.querySelectorAll('.nova-poster-card');
            }
            while (marqueeX > 0) {
                marqueeX -= step;
                strip.insertBefore(strip.children[strip.children.length - 1], strip.children[0]);
                cards = strip.querySelectorAll('.nova-poster-card');
            }
        }
        function renderMarquee() {
            if (!strip || !cards.length) return;
            normalizeMarquee();
            strip.style.transform = 'translateX(' + marqueeX + 'px)';
        }
        function tickMarquee() {
            if (!marqueePaused && strip && cards.length > 1) {
                marqueeX -= marqueeSpeed;
                renderMarquee();
            }
            marqueeFrame = window.requestAnimationFrame(tickMarquee);
        }
        function nudgeMarquee(direction) {
            var step = cardStep();
            if (!step) return;
            marqueePaused = true;
            if (strip) strip.className += strip.className.indexOf('is-marquee-paused') === -1 ? ' is-marquee-paused' : '';
            marqueeX += direction * step;
            renderMarquee();
            window.setTimeout(function () {
                marqueePaused = false;
                if (strip) strip.className = strip.className.replace(/\s?is-marquee-paused/g, '');
            }, 900);
        }
        if (stripPrev) stripPrev.onclick = function () { nudgeMarquee(1); };
        if (stripNext) stripNext.onclick = function () { nudgeMarquee(-1); };
        var stripViewport = root.querySelector('.nova-poster-strip-viewport');
        if (stripViewport) {
            stripViewport.onmouseenter = function () { marqueePaused = true; };
            stripViewport.onmouseleave = function () { marqueePaused = false; };
        }
        window.addEventListener('resize', renderMarquee);
        renderMarquee();
        tickMarquee();
    })();
    </script>
    <?php
}
// ------------- end: Codex home poster wall ------------------//

?>
<div class="nova-home-modules" style="width:1455px;max-width:100%;margin:0 auto;box-sizing:border-box;">
<?php

// ------------- start: recent news ------------------//
$homeNewsResult = sql_query("SELECT * FROM news ORDER BY added DESC LIMIT ".(int)$maxnewsnum_main) or sqlerr(__FILE__, __LINE__);
$homeNews = [];
while ($homeNewsRow = mysql_fetch_assoc($homeNewsResult)) $homeNews[] = $homeNewsRow;
$homeAttendance = (new \App\Repositories\AttendanceRepository())->getAttendance($CURUSER['id']);
$homeMonthStart = new DateTimeImmutable(date('Y-m-01'));
$homeMonth = $homeMonthStart->format('Y-m');
$homeMonthDays = (int)$homeMonthStart->format('t');
$homeLogs = [];
if ($homeAttendance) foreach ($homeAttendance->logs()->where('date', '>=', $homeMonth.'-01')->where('date', '<=', $homeMonth.'-'.$homeMonthDays)->get() as $homeLog) $homeLogs[$homeLog->date] = $homeLog;
echo '<section class="nova-home-community"><div class="nova-home-news nova-home-panel"><div class="nova-home-panel-head"><h2>📣 社区新鲜事</h2>'.(user_can('newsmanage') ? '<a href="news.php">发布</a>' : '').'</div>';
if ($homeNews) {
    $homeFeatured = $homeNews[0];
    echo '<div class="nova-home-feature" data-news-index="0" role="button" tabindex="0"><div class="nova-home-feature-icon">✦</div><div class="nova-home-feature-copy"><strong>'.htmlspecialchars($homeFeatured['title']).'</strong><p>'.htmlspecialchars(trim(preg_replace('/\s+/u', ' ', strip_tags(format_comment($homeFeatured['body'], 0))))).'</p><button type="button" class="nova-home-news-toggle">查看公告</button></div></div><div class="nova-home-news-list">';
    foreach ($homeNews as $homeNewsIndex => $homeNewsItem) echo '<div class="nova-home-news-row" data-news-index="'.(int)$homeNewsIndex.'" role="button" tabindex="0"><span>公告</span><strong>'.htmlspecialchars($homeNewsItem['title']).'</strong><time>'.date('m-d', strtotime($homeNewsItem['added'])).'</time></div>';
    echo '<div class="nova-home-news-details">';
    foreach ($homeNews as $homeNewsItem) echo '<div class="nova-home-news-body">'.format_comment($homeNewsItem['body'], 0).'</div>';
    echo '</div></div><div class="nova-news-modal" hidden><div class="nova-news-modal-backdrop"></div><div class="nova-news-modal-card" role="dialog" aria-modal="true" aria-labelledby="nova-news-modal-title"><button type="button" class="nova-news-modal-close" aria-label="关闭">×</button><h3 id="nova-news-modal-title"></h3><time></time><div class="nova-news-modal-content"></div></div></div><script>(function(){var rows=document.querySelectorAll(".nova-home-news-list .nova-home-news-row"),featured=document.querySelector(".nova-home-feature"),bodies=document.querySelectorAll(".nova-home-news-details .nova-home-news-body"),modal=document.querySelector(".nova-news-modal"),title=modal.querySelector("h3"),date=modal.querySelector("time"),content=modal.querySelector(".nova-news-modal-content");function open(i){var row=rows[i],body=bodies[i];if(!body)return;title.textContent=row.querySelector("strong")?row.querySelector("strong").textContent:"公告详情";date.textContent=row.querySelector("time")?row.querySelector("time").textContent:"";content.innerHTML=body.innerHTML;modal.hidden=false;document.body.classList.add("nova-news-modal-open");}function close(){modal.hidden=true;document.body.classList.remove("nova-news-modal-open");}function bind(el){if(!el)return;el.addEventListener("click",function(e){if(e.target.closest("a,button")&& !e.target.closest(".nova-home-news-toggle"))return;e.preventDefault();open(Number(el.getAttribute("data-news-index")||0));});el.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){e.preventDefault();open(Number(el.getAttribute("data-news-index")||0));}});}rows.forEach(bind);bind(featured);var toggle=featured&&featured.querySelector(".nova-home-news-toggle");if(toggle)toggle.addEventListener("click",function(e){e.preventDefault();e.stopPropagation();open(0);});modal.querySelector(".nova-news-modal-close").addEventListener("click",close);modal.querySelector(".nova-news-modal-backdrop").addEventListener("click",close);document.addEventListener("keydown",function(e){if(e.key==="Escape"&&!modal.hidden)close();});})();</script>';
} else echo '<div class="nova-home-empty">暂无消息</div>';
echo '</div><div class="nova-home-attendance nova-home-panel"><div class="nova-home-panel-head"><h2>📅 签到魔力</h2><a href="attendance.php">'.($homeAttendance && date('Y-m-d') === $homeAttendance->added->format('Y-m-d') ? '今日已签到' : '去签到').'</a></div><div class="nova-home-attendance-summary"><strong>'.date('Y年m月').'</strong><span>连续签到 <b>'.(int)($homeAttendance->days ?? 0).' 天</b></span><em>'.count($homeLogs).'<small>累计签到</small></em></div><div class="nova-home-week">一　二　三　四　五　六　日</div><div class="nova-home-calendar">';
$homeOffset = (int)$homeMonthStart->format('N') - 1;
for ($i = 0; $i < $homeOffset; $i++) echo '<span class="is-muted"></span>';
for ($day = 1; $day <= $homeMonthDays; $day++) { $dateKey = $homeMonth.'-'.str_pad((string)$day, 2, '0', STR_PAD_LEFT); $log = $homeLogs[$dateKey] ?? null; $class = $log ? 'is-done' : ($dateKey === date('Y-m-d') ? 'is-today' : ''); echo '<a class="'.$class.'" href="attendance.php"><b>'.$day.'</b><small>'.($log ? '+'.$log->points : ($dateKey === date('Y-m-d') ? '今天' : '未签到')).'</small></a>'; }
echo '</div><div class="nova-home-attendance-legend"><span><i class="done"></i>已签到</span><span><i class="today"></i>今天</span><span><i class="empty"></i>未签到</span></div></div></section>';
/* Legacy cached news renderer intentionally replaced by the visual home module above. */
/*

$Cache->new_page('recent_news', 86400, true);
if (!$Cache->get_page()){
$res = sql_query("SELECT * FROM news ORDER BY added DESC LIMIT ".(int)$maxnewsnum_main) or sqlerr(__FILE__, __LINE__);
if (mysql_num_rows($res) > 0)
{
	$Cache->add_whole_row();
	print("<table width=\"100%\"><tr><td class=\"text\"><div style=\"margin-left: 16pt;\">\n");
	$Cache->end_whole_row();
	$news_flag = 0;
	while($array = mysql_fetch_array($res))
	{
		$Cache->add_row();
		$Cache->add_part();
		if ($news_flag < 1) {
			print("<a href=\"javascript: klappe_news('a".$array['id']."')\"><img class=\"minus\" src=\"pic/trans.gif\" id=\"pica".$array['id']."\" alt=\"Show/Hide\" title=\"".$lang_index['title_show_or_hide']."\" />&nbsp;" . date("Y.m.d",strtotime($array['added'])) . " - " ."<b>". $array['title'] . "</b></a>");
			print("<div id=\"ka".$array['id']."\" style=\"display: block;\"> ".format_comment($array["body"],0)." </div> ");
			$news_flag = $news_flag + 1;
		}
		else
		{
			print("<a href=\"javascript: klappe_news('a".$array['id']."')\"><br /><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica".$array['id']."\" alt=\"Show/Hide\" title=\"".$lang_index['title_show_or_hide']."\" />&nbsp;" . date("Y.m.d",strtotime($array['added'])) . " - " ."<b>". $array['title'] . "</b></a>");
			print("<div id=\"ka".$array['id']."\" style=\"display: none;\"> ".format_comment($array["body"],0)." </div> ");
		}
		$Cache->end_part();
		$Cache->add_part();
		print("  &nbsp; [<a class=\"faqlink\" href=\"news.php?action=edit&amp;newsid=" . $array['id'] . "\"><b>".$lang_index['text_e']."</b></a>]");
		print(" [<a class=\"faqlink\" href=\"news.php?action=delete&amp;newsid=" . $array['id'] . "\"><b>".$lang_index['text_d']."</b></a>]");
		$Cache->end_part();
		$Cache->end_row();
	}
	$Cache->break_loop();
	$Cache->add_whole_row();
	print("</div></td></tr></table>\n");
	$Cache->end_whole_row();
}
	$Cache->cache_page();
}
echo $Cache->next_row();
while($Cache->next_row()){
	echo $Cache->next_part();
	if (user_can('newsmanage'))
	echo $Cache->next_part();
}
echo $Cache->next_row();
*/
// ------------- end: recent news ------------------//
// ------------- start: hot and classic movies ------------------//
//displayHotAndClassic();
// ------------- end: hot and classic movies ------------------//
// ------------- start: funbox ------------------//
$novaFunChatOpen = ($showfunbox_main == "yes" && (!isset($CURUSER) || $CURUSER['showfb'] == "yes")) || $showshoutbox_main == "yes";
if ($novaFunChatOpen) print('<div class="nova-fun-chat-columns">');
if ($showfunbox_main == "yes" && (!isset($CURUSER) || $CURUSER['showfb'] == "yes")){
	print('<section class="nova-fun-chat-panel nova-fun-panel">');
	// Get the newest fun stuff
	if (!$row = $Cache->get_value('current_fun_content')){
		$result = sql_query("SELECT fun.*, IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew FROM fun WHERE status != 'banned' AND status != 'dull' ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($result);
		$Cache->cache_value('current_fun_content', $row, 1043);
	}
	if (!$row) //There is no funbox item
	{
		print("<h2>".$lang_index['text_funbox'].(user_can('newfunitem') ? "<font class=\"small\"> - [<a class=\"altlink\" href=\"fun.php?action=new\"><b>".$lang_index['text_new_fun']."</b></a>]</font>" : "")."</h2>");
	}
	else
	{
	$totalvote = $Cache->get_value('current_fun_vote_count');
	if ($totalvote == ""){
		$totalvote = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id']));
		$Cache->cache_value('current_fun_vote_count', $totalvote, 756);
	}
	$funvote = $Cache->get_value('current_fun_vote_funny_count');
	if ($funvote == ""){
		$funvote = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id'])." AND vote='fun'");
		$Cache->cache_value('current_fun_vote_funny_count', $funvote, 756);
	}
//check whether current user has voted
	$funvoted = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id'])." AND userid=".sqlesc($CURUSER['id']));

	print ("<h2>".$lang_index['text_funbox']);
	if ($CURUSER)
	{
		print("<font class=\"small\">".(user_can('log') ? " - [<a class=\"altlink\" href=\"log.php?action=funbox\"><b>".$lang_index['text_more_fun']."</b></a>]": "").($row['neednew'] && user_can('newfunitem') ? " - [<a class=altlink href=\"fun.php?action=new\"><b>".$lang_index['text_new_fun']."</b></a>]" : "" ).( ($CURUSER['id'] == $row['userid'] || user_can('funmanage')) ? " - [<a class=\"altlink\" href=\"fun.php?action=edit&amp;id=".$row['id']."&amp;returnto=index.php\"><b>".$lang_index['text_edit']."</b></a>]" : "").(get_user_class() >= $funmanage_class ? " - [<a class=\"altlink\" href=\"fun.php?action=delete&amp;id=".$row['id']."&amp;returnto=index.php\"><b>".$lang_index['text_delete']."</b></a>] - [<a class=\"altlink\" href=\"fun.php?action=ban&amp;id=".$row['id']."&amp;returnto=index.php\"><b>".$lang_index['text_ban']."</b></a>]" : "")."</font>");
	}
	print("</h2>");

	print("<table width=\"100%\"><tr><td class=\"text\">");
	print("<iframe src=\"fun.php?action=view\" width='100%' height='300' frameborder='0' name='funbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");

	if ($CURUSER)
	{
		$funonclick = " onclick=\"funvote(".$row['id'].",'fun'".")\"";
		$dullonclick = " onclick=\"funvote(".$row['id'].",'dull'".")\"";
		print("<span id=\"funvote\"><b>".$funvote."</b>".$lang_index['text_out_of'].$totalvote.$lang_index['text_people_found_it'].($funvoted ? "" : "<font class=\"striking\">".$lang_index['text_your_opinion']."</font>&nbsp;&nbsp;<input type=\"button\" class='btn' name='fun' id='fun' ".$funonclick." value=\"".$lang_index['submit_fun']."\" />&nbsp;<input type=\"button\" class='btn' name='dull' id='dull' ".$dullonclick." value=\"".$lang_index['submit_dull']."\" />")."</span><span id=\"voteaccept\" style=\"display: none;\">".$lang_index['text_vote_accepted']."</span>");
	}
	print("</td></tr></table>");
	print('</section>');
	}
}
// ------------- end: funbox ------------------//
// ------------- start: shoutbox ------------------//
if ($showshoutbox_main == "yes") {
    print('<section class="nova-fun-chat-panel nova-chat-panel">');
?>
    <h2>
        <?php echo $lang_index['text_shoutbox'] ?> - <font class="small"><?php echo $lang_index['text_auto_refresh_after']?></font>
        <font class='striking' id="countdown"></font><font class="small"><?php echo $lang_index['text_seconds']?></font>
        <?php
        if (user_can('sbmanage')) {
            echo ' - <font class="small" id="clear-shout-box">[<a class="altlink" href="javascript:;"><b>'.$lang_index['clear_shout_box'].'</b></a>]</font>';
            $clearShoutBoxJs = <<<JS
jQuery('#clear-shout-box').on("click", function () {
    layer.confirm("{$lang_index['sure_to_clear_shout_box']}", {title: "Info", btn: ['Yes', "Cancel"], btnAlign: 'c'}, function (layerIndex) {
        jQuery.post("ajax.php", {"action": "clearShoutBox"}, function (response) {
            layer.close(layerIndex)
            if (response.ret != 0) {
                layer.alert(response.msg, {title: "Info", btn: ['OK', 'Cancel'], btnAlign: 'c'})
            } else {
                document.getElementById('iframe-shout-box').src='shoutbox.php?type=shoutbox';
            }
        }, "json")
    })
})
JS;
            \Nexus\Nexus::js($clearShoutBoxJs, 'footer', false);
        }
        ?>
    </h2>
<?php
	print("<table width=\"100%\"><tr><td class=\"text\">\n");
	print("<iframe id='iframe-shout-box' src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");
	print("<form action='shoutbox.php' method='get' target='sbox' name='shbox'>\n");
    print('<div style="display: flex">');
	print("<label for='shbox_text'>".$lang_index['text_message']."</label><input type='text' name='shbox_text' id='shbox_text' size='100' style='flex-grow: 1; border: 1px solid gray;' />  <input type='submit' id='hbsubmit' class='btn' name='shout' value=\"".$lang_index['sumbit_shout']."\" />");
	if ($CURUSER['hidehb'] != 'yes' && $showhelpbox_main =='yes')
		print("<input type='submit' class='btn' name='toguest' value=\"".$lang_index['sumbit_to_guest']."\" />");
	print("<input type='reset' class='btn' value=\"".$lang_index['submit_clear']."\" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />");
	print('</div>');
	print(smile_row("shbox","shbox_text"));
	print("</form></td></tr></table>");
	print('</section>');
}
// ------------- end: shoutbox ------------------//
if ($novaFunChatOpen) print('</div>');
print('<div class="nova-draw-torrents-columns">');
print('<section class="nova-draw-torrents-panel nova-draw-panel">');
require_once dirname(__FILE__) . '/../include/nova_lucky_draw.php';
echo nova_draw_home_widget();
print('</section>');

// ------------- start: latest forum posts ------------------//

if ($showlastxforumposts_main == "yes" && $CURUSER)
{
	$res = sql_query("SELECT posts.id AS pid, posts.userid AS userpost, posts.added, topics.id AS tid, topics.subject, topics.forumid, topics.views, forums.name FROM posts, topics, forums WHERE posts.topicid = topics.id AND topics.forumid = forums.id AND minclassread <=" . sqlesc(get_user_class()) . " ORDER BY posts.id DESC LIMIT 5") or sqlerr(__FILE__,__LINE__);
	if(mysql_num_rows($res) != 0)
	{
		print("<h2>".$lang_index['text_last_five_posts']."</h2>");
		print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"100%\" align=\"left\">".$lang_index['col_topic_title']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_view']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"left\">".$lang_index['col_posted_at']."</td></tr>");

		while ($postsx = mysql_fetch_assoc($res))
		{
			print("<tr><td><a href=\"forums.php?action=viewtopic&amp;topicid=".$postsx["tid"]."&amp;page=p".$postsx["pid"]."#pid".$postsx["pid"]."\"><b>".htmlspecialchars($postsx["subject"])."</b></a><br />".$lang_index['text_in']."<a href=\"forums.php?action=viewforum&amp;forumid=".$postsx["forumid"]."\">".htmlspecialchars($postsx["name"])."</a></td><td align=\"center\">".$postsx["views"]."</td><td align=\"center\">" . get_username($postsx["userpost"]) ."</td><td>".gettime($postsx["added"])."</td></tr>");
		}
		print("</table>");
	}
}

// ------------- end: latest forum posts ------------------//
// ------------- start: latest torrents ------------------//

if ($showlastxtorrents_main == "yes") {
		print('<section class="nova-draw-torrents-panel nova-latest-torrents-panel">');
		$result = sql_query("SELECT DISTINCT torrents.id,torrents.name,torrents.small_descr,torrents.leechers,torrents.seeders FROM torrents INNER JOIN torrent_tags ON torrents.id = torrent_tags.torrent_id WHERE torrents.visible='yes' AND torrents.banned='no' AND torrent_tags.tag_id = 3 ORDER BY torrents.id DESC LIMIT 5") or sqlerr(__FILE__, __LINE__);
		if(mysql_num_rows($result) != 0 )
		{
			print ("<h2>".$lang_index['text_last_five_torrent']."</h2>");
			print ("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"100%\">".$lang_index['col_name']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_seeder']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_leecher']."</td></tr>");

			while( $row = mysql_fetch_assoc($result) )
			{
				print ("<tr><a href=\"details.php?id=". $row['id'] ."&amp;hit=1\"><td><a href=\"details.php?id=". $row['id'] ."&amp;hit=1\"><b>" . htmlspecialchars($row['name']) . "</b><br/>" . htmlspecialchars($row['small_descr']) ."</td></a><td align=\"center\">" . $row['seeders'] . "</td><td align=\"center\">" . $row['leechers'] . "</td></tr>");
			}
			print ("</table>");
		}
		print('</section>');
}
// ------------- end: latest torrents ------------------//
print('</div>');

$extraModules = [];
$extraModules = apply_filter('nexus_home_module', $extraModules);
print implode('', $extraModules);

// ------------- start: top uploader ------------------//

if (get_setting('main.show_top_uploader') == "yes") {
    $topUploaderBaseQuery = \App\Models\Torrent::query()
        ->selectRaw("owner, count(*) as counts")
        ->groupBy('owner')
        ->orderBy("counts", "desc")
        ->take(10);
    $userStatResult = \Nexus\Database\NexusDB::remember("index_top_uploader_all", 60, function () use ($topUploaderBaseQuery) {
        return (clone $topUploaderBaseQuery)->get();
    });
    if($userStatResult->isNotEmpty())
    {
        \Nexus\Nexus::css('.tr-top-uploader-tab>td {cursor: pointer}', 'footer', false);
        $toggleTimeRangeJs = <<<JS
jQuery(".tr-top-uploader-tab").on("click", "td", function () {
    let _this = jQuery(this)
    if (_this.hasClass("colhead")) {
        return
    }
    _this.parent().children().removeClass("colhead")
    _this.addClass("colhead")
    jQuery(".top-uploader").hide()
    jQuery("." + _this.attr("data-table")).fadeIn()

})
JS;
        \Nexus\Nexus::js($toggleTimeRangeJs, "footer", false);
        print ("<h2>".$lang_index['top_uploader_title']."</h2>");
        print("<table width='100%'><tr class='tr-top-uploader-tab' title='{$lang_index['top_uploader_toggle_time_range_tab']}'><td class='colhead' align='center' data-table='top-uploader-recently'>{$lang_index['top_uploader_toggle_time_range_recently']}</td><td align='center' data-table='top-uploader-all'>{$lang_index['top_uploader_toggle_time_range_all']}</td></tr></table>");

        $userTorrentCounts = $userStatResult->pluck('counts', 'owner');
        $uidArr = $userStatResult->pluck('owner')->toArray();
        $result = \App\Models\User::query()->whereIn('id', $uidArr)->orderByRaw(sprintf("field(id,%s)", implode(',', $uidArr)))->get(['id', 'username']);
        print ("<table class='top-uploader top-uploader-all' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" style='display: none'><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_counts']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_ranking']."</td></tr>");
        foreach ($result as $ranking => $row)
        {
            print ("<tr><td>" . get_username($row->id) . "</td><td align=\"center\">" . $userTorrentCounts->get($row->id, 0) . "</td><td align=\"center\">" . ($ranking + 1) . "</td></tr>");
        }
        print ("</table>");

        $userStatResult = \Nexus\Database\NexusDB::remember("index_top_uploader_recently", 60, function () use ($topUploaderBaseQuery) {
            return (clone $topUploaderBaseQuery)->where('added', '>=', \Carbon\Carbon::today()->subDays(30))->get();
        });
        $userTorrentCounts = $userStatResult->pluck('counts', 'owner');
        $uidArr = $userStatResult->pluck('owner')->toArray() ?: [0];
        $result = \App\Models\User::query()->whereIn('id', $uidArr)->orderByRaw(sprintf("field(id,%s)", implode(',', $uidArr)))->get(['id', 'username']);
        print ("<table class='top-uploader top-uploader-recently' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_counts']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_ranking']."</td></tr>");
        foreach ($result as $ranking => $row)
        {
            print ("<tr><td>" . get_username($row->id) . "</td><td align=\"center\">" . $userTorrentCounts->get($row->id, 0) . "</td><td align=\"center\">" . ($ranking + 1) . "</td></tr>");
        }
        print ("</table>");
    }
}
// ------------- end: top uploader ------------------//

// ------------- start: polls ------------------//
if ($CURUSER && $showpolls_main == "yes")
{
		// Get current poll
		if (!$arr = $Cache->get_value('current_poll_content')){
			$res = sql_query("SELECT * FROM polls ORDER BY id DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_array($res);
			$Cache->cache_value('current_poll_content', $arr, 7226);
		}
		if (!$arr)
			$pollexists = false;
		else $pollexists = true;

		print("<h2>".$lang_index['text_polls']);

			if (user_can('pollmanage'))
			{
				print("<font class=\"small\"> - [<a class=\"altlink\" href=\"makepoll.php?returnto=main\"><b>".$lang_index['text_new']."</b></a>]\n");
				if ($pollexists)
				{
					print(" - [<a class=\"altlink\" href=\"makepoll.php?action=edit&amp;pollid=".$arr['id']."&amp;returnto=main\"><b>".$lang_index['text_edit']."</b></a>]\n");
					print(" - [<a class=\"altlink\" href=\"log.php?action=poll&amp;do=delete&amp;pollid=".$arr['id']."&amp;returnto=main\"><b>".$lang_index['text_delete']."</b></a>]");
					print(" - [<a class=\"altlink\" href=\"polloverview.php?id=".$arr['id']."\"><b>".$lang_index['text_detail']."</b></a>]");
				}
				print("</font>");
			}
			print("</h2>");
		if ($pollexists)
		{
			$pollid = intval($arr["id"] ?? 0);

			$question = $arr["question"];
			$o = array($arr["option0"], $arr["option1"], $arr["option2"], $arr["option3"], $arr["option4"],
			$arr["option5"], $arr["option6"], $arr["option7"], $arr["option8"], $arr["option9"],
			$arr["option10"], $arr["option11"], $arr["option12"], $arr["option13"], $arr["option14"],
			$arr["option15"], $arr["option16"], $arr["option17"], $arr["option18"], $arr["option19"]);

			print("<table width=\"100%\"><tr><td class=\"text\" align=\"center\">\n");
			print("<table width=\"59%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"text\" align=\"left\">");
			print("<p align=\"center\"><b>".$question."</b></p>\n");

			// Check if user has already voted
			$res = sql_query("SELECT selection FROM pollanswers WHERE pollid=".sqlesc($pollid)." AND userid=".sqlesc($CURUSER["id"])) or sqlerr();
			$voted = mysql_fetch_assoc($res);
			if ($voted) //user has already voted
			{
				$uservote = $voted["selection"];
				$Cache->new_page('current_poll_result', 3652, true);
				if (!$Cache->get_page())
				{
				// we reserve 255 for blank vote.
				$res = sql_query("SELECT selection FROM pollanswers WHERE pollid=".sqlesc($pollid)." AND selection < 20") or sqlerr();

				$tvotes = mysql_num_rows($res);

				$vs = array();
				$os = array();

				// Count votes
                while ($arr2 = mysql_fetch_row($res)) {
                    if (!isset($vs[$arr2[0]])) {
                        $vs[$arr2[0]] = 0;
                    }
                    $vs[$arr2[0]] ++;
                }


				reset($o);
				for ($i = 0; $i < count($o); ++$i){
					if ($o[$i])
						$os[$i] = array($vs[$i] ?? 0, $o[$i], $i);//field 1: options vote count, field 2: option name, field 3: option index
				}

				function srt($a,$b)
				{
					if ($a[0] > $b[0]) return -1;
					if ($a[0] < $b[0]) return 1;
					return 0;
				}

				// now os is an array like this: array(array(123, "Option 1", 1), array(45, "Option 2", 2))
				$Cache->add_whole_row();
				print("<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n");
				$Cache->end_whole_row();
				$i = 0;
				while (isset($os[$i]))
				{
				    $a = $os[$i];
					if ($tvotes == 0)
						$p = 0;
					else
						$p = round($a[0] / $tvotes * 100);
					$Cache->add_row();
					$Cache->add_part();
					print("<tr><td width=\"1%\" class=\"embedded nowrap\">" . $a[1] . "&nbsp;&nbsp;</td><td width=\"99%\" class=\"embedded nowrap\"><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /><img ");
					$Cache->end_part();
					$Cache->add_part();
					print(" src=\"pic/trans.gif\" style=\"width: " . ($p * 3) ."px;\" alt=\"\" /><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /> $p%</td></tr>\n");
					$Cache->end_part();
					$Cache->end_row();
					++$i;
				}
				$Cache->break_loop();
				$Cache->add_whole_row();
				print("</table>\n");
				$tvotes = number_format($tvotes);
				print("<p align=\"center\">".$lang_index['text_votes']." ".$tvotes."</p>\n");
				$Cache->end_whole_row();
				$Cache->cache_page();
				}
				echo $Cache->next_row();
				$i = 0;
				while($Cache->next_row()){
					echo $Cache->next_part();
					if ($i == $uservote)
						echo "class=\"sltbar\"";
					else
						echo "class=\"unsltbar\"";
					echo $Cache->next_part();
					$i++;
				}
				echo $Cache->next_row();
			}
			else //user has not voted yet
			{
				print("<form method=\"post\" action=\"index.php\">\n");
				$i = 0;
				while ($a = $o[$i])
				{
					print("<input type=\"radio\" name=\"choice\" value=\"".$i."\">".$a."<br />\n");
					++$i;
				}
				print("<br />");
				print("<input type=\"radio\" name=\"choice\" value=\"255\">".$lang_index['radio_blank_vote']."<br />\n");
				print("<p align=\"center\"><input type=\"submit\" class=\"btn\" value=\"".$lang_index['submit_vote']."\" /></p>");
			}
			print("</td></tr></table>");

			if ($voted && user_can('log'))
				print("<p align=\"center\"><a href=\"log.php?action=poll\">".$lang_index['text_previous_polls']."</a></p>\n");
			print("</td></tr></table>");
		}
}
// ------------- end: polls ------------------//
// ------------- start: stats ------------------//
if ($showstats_main == "yes")
{
?>
<h2><?php echo $lang_index['text_tracker_statistics'] ?></h2>
<table width="100%"><tr><td class="text" align="center">
<table width="60%" class="main" border="1" cellspacing="0" cellpadding="10">
<?php
	$Cache->new_page('stats_users', 3000, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$registered = number_format(get_row_count("users"));
	$unverified = number_format(get_row_count("users", "WHERE status='pending' and enabled='yes'"));
	$totalonlinetoday = number_format(get_row_count("users","WHERE last_access >= ". sqlesc(date("Y-m-d H:i:s",(TIMENOW - 86400)))));
	$totalonlineweek = number_format(get_row_count("users","WHERE last_access >= ". sqlesc(date("Y-m-d H:i:s",(TIMENOW - 604800)))));
	$VIP = number_format(get_row_count("users", "WHERE class=".UC_VIP));
	$donated = number_format(get_row_count("users", "WHERE donor = 'yes'"));
	$warned = number_format(get_row_count("users", "WHERE warned='yes'"));
	$disabled = number_format(get_row_count("users", "WHERE enabled='no'"));
	$registered_male = number_format(get_row_count("users", "WHERE gender='Male'"));
	$registered_female = number_format(get_row_count("users", "WHERE gender='Female'"));
?>
<tr>
<?php
	twotd($lang_index['row_users_active_today'],$totalonlinetoday);
	twotd($lang_index['row_users_active_this_week'],$totalonlineweek);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_registered_users'],$registered." / ".number_format($maxusers));
	twotd($lang_index['row_unconfirmed_users'],$unverified);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_VIP,false,false,true),$VIP);
	twotd($lang_index['row_donors']." <img class=\"star\" src=\"pic/trans.gif\" alt=\"Donor\" />",$donated);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_warned_users']." <img class=\"warned\" src=\"pic/trans.gif\" alt=\"warned\" />",$warned);
	twotd($lang_index['row_banned_users']." <img class=\"disabled\" src=\"pic/trans.gif\" alt=\"disabled\" />",$disabled);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_male_users'],$registered_male);
	twotd($lang_index['row_female_users'],$registered_female);
?>
</tr>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
?>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<?php
	$Cache->new_page('stats_torrents', 1800, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$torrents = number_format(get_row_count("torrents"));
	$dead = number_format(get_row_count("torrents", "WHERE visible='no'"));
	$seeders = get_row_count("peers", "WHERE seeder='yes'");
	$leechers = get_row_count("peers", "WHERE seeder='no'");
	if ($leechers == 0)
		$ratio = 0;
	else
		$ratio = round($seeders / $leechers * 100);
	$activewebusernow = get_row_count("users","WHERE last_access >= ".sqlesc(date("Y-m-d H:i:s",(TIMENOW - 900))));
	$activewebusernow=number_format($activewebusernow);
	$activetrackerusernow = number_format(get_single_value("peers","COUNT(DISTINCT(userid))"));
	$peers = number_format($seeders + $leechers);
	$seeders = number_format($seeders);
	$leechers = number_format($leechers);
	$totaltorrentssize = mksize(get_row_sum("torrents", "size"));
	$totaluploaded = get_row_sum("users","uploaded");
	$totaldownloaded = get_row_sum("users","downloaded");
	$totaldata = $totaldownloaded+$totaluploaded;
?>
<tr>
<?php
	twotd($lang_index['row_torrents'],$torrents);
	twotd($lang_index['row_dead_torrents'],$dead);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_seeders'],$seeders);
	twotd($lang_index['row_leechers'],$leechers);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_peers'],$peers);
	twotd($lang_index['row_seeder_leecher_ratio'],$ratio."%");
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_active_browsing_users'], $activewebusernow);
	twotd($lang_index['row_tracker_active_users'], $activetrackerusernow);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_total_size_of_torrents'],$totaltorrentssize);
	twotd($lang_index['row_total_uploaded'],mksize($totaluploaded));
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_total_downloaded'],mksize($totaldownloaded));
	twotd($lang_index['row_total_data'],mksize($totaldata));
?>
</tr>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
?>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<?php
	$Cache->new_page('stats_classes', 4535, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$peasants =  number_format(get_row_count("users", "WHERE class=".UC_PEASANT));
	$users = number_format(get_row_count("users", "WHERE class=".UC_USER));
	$powerusers = number_format(get_row_count("users", "WHERE class=".UC_POWER_USER));
	$eliteusers = number_format(get_row_count("users", "WHERE class=".UC_ELITE_USER));
	$crazyusers = number_format(get_row_count("users", "WHERE class=".UC_CRAZY_USER));
	$insaneusers = number_format(get_row_count("users", "WHERE class=".UC_INSANE_USER));
	$veteranusers = number_format(get_row_count("users", "WHERE class=".UC_VETERAN_USER));
	$extremeusers = number_format(get_row_count("users", "WHERE class=".UC_EXTREME_USER));
	$ultimateusers = number_format(get_row_count("users", "WHERE class=".UC_ULTIMATE_USER));
	$nexusmasters = number_format(get_row_count("users", "WHERE class=".UC_NEXUS_MASTER));
?>
<tr>
<?php
	twotd(get_user_class_name(UC_PEASANT,false,false,true)." <img class=\"leechwarned\" src=\"pic/trans.gif\" alt=\"leechwarned\" />",$peasants);
	twotd(get_user_class_name(UC_USER,false,false,true),$users);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_POWER_USER,false,false,true),$powerusers);
	twotd(get_user_class_name(UC_ELITE_USER,false,false,true),$eliteusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_CRAZY_USER,false,false,true),$crazyusers);
	twotd(get_user_class_name(UC_INSANE_USER,false,false,true),$insaneusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_VETERAN_USER,false,false,true),$veteranusers);
	twotd(get_user_class_name(UC_EXTREME_USER,false,false,true),$extremeusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_ULTIMATE_USER,false,false,true),$ultimateusers);
	twotd(get_user_class_name(UC_NEXUS_MASTER,false,false,true),$nexusmasters);
?>
</tr>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
?>
</table>
</td></tr></table>
<?php
}
// ------------- end: stats ------------------//
// ------------- start: tracker load ------------------//
if ($showtrackerload == "yes") {
	$uptimeresult = function_exists('exec') ? exec('uptime') : '';
	if ($uptimeresult){
?>
<h2><?php echo $lang_index['text_tracker_load'] ?></h2>
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text" align="center">
<?php
	//uptime, work in *nix system
	print ("<div align=\"center\">" . trim($uptimeresult) . "</div>");
	print("</td></tr></table>");
	}
}
// ------------- end: tracker load ------------------//

// ------------- start: disclaimer ------------------//
?>
<h2><?php echo $lang_index['text_disclaimer'] ?></h2>
<table width="100%"><tr><td class="text">
  <?php echo sprintf($lang_index['text_disclaimer_content'], \App\Models\Setting::getSiteName(), \App\Models\Setting::getSiteName()) ?></td></tr></table>
<?php
// ------------- end: disclaimer ------------------//
// ------------- start: links ------------------//
	print("<h2>".$lang_index['text_links']);
	if (user_can('applylink'))
		print("<font class=\"small\"> - [<a class=\"altlink\" href=\"linksmanage.php?action=apply\"><b>".$lang_index['text_apply_for_link']."</b></a>]</font>");
	if (user_can('linkmanage'))
	{
		print("<font class=\"small\">");
		print(" - [<a class=\"altlink\" href=\"linksmanage.php\"><b>".$lang_index['text_manage_links']."</b></a>]\n");
		print("</font>");
	}
	print("</h2>");
	$Cache->new_page('links', 86400, false);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$res = sql_query("SELECT * FROM links ORDER BY id ASC") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) > 0)
	{
		$links = "";
		while($array = mysql_fetch_array($res))
		{
			$links .= "<a href=\"" . $array['url'] . "\" title=\"" . $array['title'] . "\" target=\"_blank\">" . $array['name'] . "</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
		}
		print("<table width=\"100%\"><tr><td class=\"text\">".trim($links)."</td></tr></table>");
	}
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
// ------------- end: links ------------------//
// ------------- start: browser, client and code note ------------------//
?>
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
<div align="center"><br /><font class="medium"><?php echo $lang_index['text_browser_note'] ?></font></div>
<div class="nova-home-footer-logo"><a href="index.php" title="<?php echo htmlspecialchars(\App\Models\Setting::getSiteName()) ?>"><img src="pic/logo-novahd.svg" alt="<?php echo htmlspecialchars(\App\Models\Setting::getSiteName()) ?>" /></a></div>
</td></tr></table>
<?php
// ------------- end: browser, client and code note ------------------//
?>
</div>
<?php
if ($CURUSER)
	$USERUPDATESET[] = "last_home = ".sqlesc(date("Y-m-d H:i:s"));
$Cache->delete_value('user_'.$CURUSER["id"].'_unread_news_count');
end_main_frame();
stdfoot();
?>
