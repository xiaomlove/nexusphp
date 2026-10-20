<?php
ob_start(); //Do not delete this line
require_once("../include/bittorrent.php");
require_once("../include/torrent_review_assistant.php");
require_once("../include/iyuu_torrent_match.php");
require_once("../include/nova_sticky_promotion.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
echo "<script>document.addEventListener(DOMContentLoaded,function(){document.querySelectorAll(details.nova-detail-fold).forEach(function(e){e.remove();});});</script>";
echo "<style>details.nova-detail-fold{display:none!important}</style>";
$id = intval($_GET["id"] ?? 0);
$customField = new \Nexus\Field\Field();
int_check($id, true);
if (!isset($id) || !$id)
die();

$taxonomyFields = "sources.name AS source_name, media.name AS medium_name, codecs.name AS codec_name, standards.name AS standard_name, processings.name AS processing_name, teams.name AS team_name, audiocodecs.name AS audiocodec_name";
$extraFields = "torrent_extras.descr, torrent_extras.nfo, LENGTH(torrent_extras.nfo) AS nfosz, torrent_extras.media_info as technical_info";
$res = sql_query("SELECT torrents.cache_stamp, torrents.sp_state, torrents.url, torrents.small_descr, torrents.seeders, torrents.banned, torrents.leechers, torrents.info_hash, torrents.filename, torrents.last_action, torrents.name, torrents.owner, torrents.save_as, torrents.visible, torrents.size, torrents.added, torrents.views, torrents.hits, torrents.times_completed, torrents.id, torrents.type, torrents.numfiles, torrents.anonymous, torrents.hr, torrents.promotion_until, torrents.promotion_time_type, torrents.approval_status, torrents.price,
       categories.name AS cat_name, categories.mode as search_box_id, $taxonomyFields, $extraFields
FROM torrents LEFT JOIN categories ON torrents.category = categories.id
    LEFT JOIN sources ON torrents.source = sources.id
    LEFT JOIN media ON torrents.medium = media.id
    LEFT JOIN codecs ON torrents.codec = codecs.id
    LEFT JOIN standards ON torrents.standard = standards.id
    LEFT JOIN processings ON torrents.processing = processings.id
    LEFT JOIN teams ON torrents.team = teams.id
    LEFT JOIN audiocodecs ON torrents.audiocodec = audiocodecs.id
    LEFT JOIN torrent_extras ON torrents.id = torrent_extras.torrent_id
WHERE torrents.id = $id LIMIT 1")
or sqlerr();
$row = mysql_fetch_array($res);
if (user_can('torrentmanage') || $CURUSER["id"] == $row["owner"])
$owned = 1;
else $owned = 0;

$settingMain = get_setting('main');
if (!$row) {
    stderr($lang_details['std_error'], $lang_details['std_no_torrent_id']);
} elseif (
    ($row['banned'] == 'yes' && !user_can('seebanned') && $row['owner'] != $CURUSER['id'])
    || (!can_access_torrent($row, $CURUSER['id']) && $row['owner'] != $CURUSER['id'])
) {
    permissiondenied();
} else {
    $row = apply_filter('torrent_detail', $row);
    $owner = \App\Models\User::query()->find($row['owner']);
    if (!$owner) {
        $owner = \App\Models\User::defaultUser();
    }
    $torrentRep = new \App\Repositories\TorrentRepository();
    $searchBoxRep = new \App\Repositories\SearchBoxRepository();
    $torrentUpdate = [];
	if (!empty($_GET["hit"])) {
        $torrentUpdate[] = 'views = views + 1';
	}

    // The detail page intentionally uses only the published description for
    // the top media banner. Avoid IMDb parsing, cache reads and network work.
    $imdb_id = 0;
    $movie = null;

	if (!isset($_GET["cmtpage"])) {
		stdhead($lang_details['head_details_for_torrent']. "\"" . $row["name"] . "\"");
		if (!empty($_GET["uploaded"]))
		{
			print("<h1 align=\"center\">".$lang_details['text_successfully_uploaded']."</h1>");
			print("<p>".$lang_details['text_redownload_torrent_note']."</p>");
			header("refresh: 1; url=download.php?id=$id");
			//header("refresh: 1; url=getimdb.php?id=$id&type=1");
		}
		elseif (!empty($_GET["edited"])) {
			print("<h1 align=\"center\">".$lang_details['text_successfully_edited']."</h1>");
			if (isset($_GET["returnto"]))
				print("<p><b>".$lang_details['text_go_back'] . "<a href=\"".htmlspecialchars($_GET["returnto"])."\">" . $lang_details['text_whence_you_came']."</a></b></p>");
		} elseif (!empty($_GET['existed'])) {
            print("<h1 align=\"center\" style='color: red'>".$lang_details['torrent_existed']."</h1>");
            if (isset($_GET["returnto"]))
                print("<p><b>".$lang_details['text_go_back'] . "<a href=\"".htmlspecialchars($_GET["returnto"])."\">" . $lang_details['text_whence_you_came']."</a></b></p>");
        }

        // Build the top banner from Douban/TMDB metadata, then fall back to
        // the structured PT-Gen text stored in the published description.
        try {
            $heroTitle = $heroCover = $heroYear = $heroRuntime = $heroRating = $heroPlot = $heroLanguage = '';
            $heroGenres = $heroCountry = $heroCast = $heroDirector = [];
            $heroRaw = html_entity_decode((string) ($row['descr'] ?? ''), ENT_QUOTES, 'UTF-8');
            $heroText = preg_replace('/<br\s*\/?\s*>/i', "\n", $heroRaw);
            $heroText = html_entity_decode(strip_tags((string) $heroText), ENT_QUOTES, 'UTF-8');
            $heroMatch = static function ($pattern, $text) {
                return preg_match($pattern, $text, $matches) ? trim(strip_tags($matches[1])) : '';
            };

            // Read only links already published with the torrent. Requests are
            // short and cached so a blocked metadata provider cannot slow down
            // the detail page or make the page depend on IMDb.
            $heroMetadata = ['douban' => [], 'tmdb' => [], 'imdb' => []];
            $heroUrls = trim($heroRaw . "\n" . (string)($row['url'] ?? ''));
            if (preg_match('/^\d{6,9}$/', trim((string)($row['url'] ?? '')), $imdbNumber)) $heroUrls .= "\nhttps://www.imdb.com/title/tt" . $imdbNumber[0] . "/";
            $heroExtractMeta = static function ($url, $source) {
                $result = [];
                $cacheDir = __DIR__ . '/../runtime/cache/nova_movie_meta';
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
                $cacheFile = $cacheDir . '/' . sha1($source . '|' . $url) . '.json';
                $html = '';
                if (is_file($cacheFile) && (time() - @filemtime($cacheFile)) < 86400) {
                    $html = (string) @file_get_contents($cacheFile);
                } elseif (function_exists('curl_init')) {
                    $ch = curl_init($url);
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_FOLLOWLOCATION => true,
                        CURLOPT_CONNECTTIMEOUT => 1,
                        CURLOPT_TIMEOUT => 3,
                        CURLOPT_PROXY => 'http://127.0.0.1:18080',
                        CURLOPT_ENCODING => '',
                        CURLOPT_USERAGENT => 'Mozilla/5.0 NovaHD metadata reader',
                        CURLOPT_HTTPHEADER => ['Accept-Language: zh-CN,zh;q=0.8,en;q=0.5'],
                    ]);
                    $html = (string) curl_exec($ch);
                    curl_close($ch);
                    if ($html !== '' && strlen($html) < 2000000) @file_put_contents($cacheFile, $html, LOCK_EX);
                }
                if ($html === '') return $result;
                $readMeta = static function ($name) use ($html) {
                    $quoted = preg_quote($name, '/');
                    if (preg_match('/<meta[^>]+(?:property|name)=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']*)/i', $html, $m)) return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                    if (preg_match('/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property|name)=["\']'.$quoted.'["\']/i', $html, $m)) return trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                    return '';
                };
                $json = null;
                if (preg_match_all('/<script[^>]+type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $blocks)) {
                    foreach ($blocks[1] as $block) {
                        $decoded = json_decode(html_entity_decode(trim($block), ENT_QUOTES, 'UTF-8'), true);
                        if (is_array($decoded) && (isset($decoded['name']) || isset($decoded['@graph']))) {
                            $json = $decoded;
                            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) foreach ($decoded['@graph'] as $node) if (is_array($node) && isset($node['name'])) { $json = $node; break; }
                            break;
                        }
                    }
                }
                $result['title'] = trim((string)($json['name'] ?? $readMeta('og:title')));
                $result['cover'] = trim((string)($json['image'] ?? $readMeta('og:image')));
                if (is_array($result['cover'])) $result['cover'] = (string)($result['cover']['url'] ?? reset($result['cover']));
                $result['plot'] = trim(strip_tags((string)($json['description'] ?? $readMeta('og:description'))));
                $result['year'] = trim((string)($json['datePublished'] ?? ''));
                if (preg_match('/\b(19|20)\d{2}\b/', $result['year'] . ' ' . $result['title'], $m)) $result['year'] = $m[0]; else $result['year'] = '';
                $genres = $json['genre'] ?? [];
                $result['genres'] = is_array($genres) ? $genres : ($genres !== '' ? [$genres] : []);
                $result['rating'] = trim((string)($json['aggregateRating']['ratingValue'] ?? ''));
                if ($result['rating'] === '' && $source === 'tmdb' && preg_match('/class=["\']user_score_chart["\'][^>]*data-percent=["\'](\d{1,3})["\']/i', $html, $score)) $result['rating'] = number_format(((int)$score[1]) / 10, 1);
                $result['country'] = [];
                foreach ((array)($json['countryOfOrigin'] ?? []) as $country) $result['country'][] = is_array($country) ? (string)($country['name'] ?? '') : (string)$country;
                foreach (['国家/地区','制片国家/地区'] as $key) { $value = $readMeta($key); if ($value !== '') $result['country'][] = $value; }
                $readPeople = static function ($people) {
                    if (!$people) return [];
                    if (is_array($people) && array_key_exists('name', $people)) $people = [$people];
                    if (!is_array($people)) $people = [$people];
                    $result = [];
                    foreach ($people as $person) {
                        $name = is_array($person) ? ($person['name'] ?? '') : $person;
                        if ($name !== '') $result[] = ['name' => trim((string) $name)];
                    }
                    return $result;
                };
                $result['cast'] = $readPeople($json['actor'] ?? []);
                $result['director'] = $readPeople($json['director'] ?? []);
                if ($source === 'tmdb' && preg_match_all('/<li[^>]+class=["\'][^"\']*card[^"\']*["\'][^>]*>(.*?)<\/li>/is', $html, $cards)) {
                    foreach (array_slice($cards[1], 0, 12) as $card) {
                        $name = preg_match('/<p[^>]*>\s*<a[^>]*>(.*?)<\/a>/is', $card, $cm) ? trim(strip_tags($cm[1])) : '';
                        $photo = preg_match('/<img[^>]+src=["\']([^"\']+)/i', $card, $pm) ? trim(html_entity_decode($pm[1], ENT_QUOTES, 'UTF-8')) : '';
                        if ($name !== '') $result['cast'][] = ['name' => html_entity_decode($name, ENT_QUOTES, 'UTF-8'), 'photo' => $photo];
                    }
                }
                $result['source'] = $source;
                return $result;
            };
            if (preg_match('/https?:\/\/(?:www\.)?douban\.com\/(?:subject|movie)\/\d+[^\s\]"<>]*/i', $heroUrls, $m)) $heroMetadata['douban'] = $heroExtractMeta($m[0], 'douban');
            if (preg_match('/https?:\/\/(?:www\.)?themoviedb\.org\/(?:movie|tv)\/\d+[^\s\]"<>]*/i', $heroUrls, $m)) $heroMetadata['tmdb'] = $heroExtractMeta($m[0], 'tmdb');
            if (preg_match('/https?:\/\/(?:www\.)?imdb\.com\/title\/tt\d+[^\s\]"<>]*/i', $heroUrls, $m)) $heroMetadata['imdb'] = $heroExtractMeta($m[0], 'imdb');
            if (!$heroMetadata['tmdb'] && preg_match('/(?:tt)?(\d{6,9})/', trim((string)($row['url'] ?? '')), $imdbLookup)) {
                $findCh = curl_init('https://www.themoviedb.org/find/tt' . $imdbLookup[1] . '?external_source=imdb_id');
                curl_setopt_array($findCh, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 4, CURLOPT_PROXY => 'http://127.0.0.1:18080', CURLOPT_USERAGENT => 'Mozilla/5.0 NovaHD metadata reader']);
                $findHtml = (string) curl_exec($findCh);
                curl_close($findCh);
                if (preg_match('/href=["\'](\/(?:movie|tv)\/\d+[^"\']*)/i', $findHtml, $tmdbPath)) $heroMetadata['tmdb'] = $heroExtractMeta('https://www.themoviedb.org' . $tmdbPath[1], 'tmdb');
            }
            $heroSources = array_values(array_filter($heroMetadata, static function ($item) { return !empty($item['title']) || !empty($item['cover']) || !empty($item['plot']); }));
            if ($heroSources) {
                $primary = $heroMetadata['douban'] ?: ($heroMetadata['tmdb'] ?: ($heroMetadata['imdb'] ?: $heroSources[0]));
                $secondary = $heroMetadata['douban'] ? ($heroMetadata['tmdb'] ?: $heroMetadata['imdb']) : ($heroMetadata['tmdb'] ? $heroMetadata['imdb'] : []);
                $sameYear = !$primary['year'] || !$secondary['year'] || $primary['year'] === $secondary['year'];
                $heroTitle = $sameYear ? ($primary['title'] ?: ($secondary['title'] ?? '')) : '';
                $heroCover = $primary['cover'] ?: ($secondary['cover'] ?? '');
                $heroYear = $primary['year'] ?: ($secondary['year'] ?? '');
                $heroPlot = $primary['plot'] ?: ($secondary['plot'] ?? '');
                $heroGenres = $primary['genres'] ?: ($secondary['genres'] ?? []);
                $heroCountry = $primary['country'] ?: ($secondary['country'] ?? []);
                $heroRating = $primary['rating'] ?: ($secondary['rating'] ?? '');
                $heroCast = $primary['cast'] ?: ($secondary['cast'] ?? []);
                $heroDirector = $primary['director'] ?: ($secondary['director'] ?? []);
            }
            if ($heroCover === '') $heroCover = $heroMatch('/\[img\](https?:\/\/[^\[]+)\[\/img\]/i', $heroRaw);
            if ($heroTitle === '') $heroTitle = $heroMatch('/(?:◎\s*)?(?:译|片)\s*名\s*[:：]?\s*([^\r\n]+)/u', $heroText);
            if ($heroYear === '') $heroYear = $heroMatch('/(?:◎\s*)?年\s*代\s*[:：]?\s*([^\r\n]+)/u', $heroText);
            if ($heroRuntime === '') $heroRuntime = $heroMatch('/(?:◎\s*)?片\s*长\s*[:：]?\s*([^\r\n]+)/u', $heroText);
            if (!$heroCountry) {
                $value = $heroMatch('/(?:◎\s*)?产\s*地\s*[:：]?\s*([^\r\n]+)/u', $heroText);
                if ($value !== '') $heroCountry = [$value];
            }
            if ($heroLanguage === '') $heroLanguage = $heroMatch('/(?:◎\s*)?语\s*言\s*[:：]?\s*([^\r\n]+)/u', $heroText);
            if (!$heroGenres) {
                $value = $heroMatch('/(?:◎\s*)?类\s*别\s*[:：]?\s*([^\r\n]+)/u', $heroText);
                if ($value !== '') $heroGenres = [$value];
            }
            if ($heroRating === '') $heroRating = $heroMatch('/(?:◎\s*)?(?:豆瓣|IMDb)评分\s*[:：]?\s*([^\r\n]+)/ui', $heroText);
            if ($heroPlot === '') $heroPlot = $heroMatch('/(?:◎\s*)?(?:剧\s*情\s*)?简\s*介\s*[:：]?\s*([\s\S]*?)(?=\s*(?:◎\s*)?(?:片\s*长|导\s*演|主\s*演|类\s*别|演员|技术参数)|\[img\]|$)/u', $heroText);
            if ($heroPlot !== '') {
                $heroPlot = preg_replace('/(?:豆瓣|IMDb)\s*链接\s*[:：]?\s*\[url[^\]]*\].*?\[\/url\]\s*/isu', '', $heroPlot);
                $heroPlot = preg_replace('/\[url[^\]]*\].*?\[\/url\]/isu', '', $heroPlot);
                $heroPlot = preg_replace('/https?:\/\/[^\s\]]+/iu', '', $heroPlot);
                $heroPlot = trim(preg_replace('/[ \t\r\n]+/u', ' ', $heroPlot));
            }
            // The upload form stores short metadata such as "演员：..." in
            // small_descr; use it as the primary source for the hero cast.
            $heroSubtitle = html_entity_decode(strip_tags((string) ($row['small_descr'] ?? '')), ENT_QUOTES, 'UTF-8');
            $subtitleCast = $heroMatch('/(?:领\s*衔\s*)?(?:主\s*演|演\s*员|主要\s*演\s*员)\s*[:：]\s*([^\r\n|｜]+)/u', $heroSubtitle);
            if ($subtitleCast !== '') foreach (preg_split('/[、，,\/＆&;；]+/u', $subtitleCast) as $castName) {
                $castName = trim(strip_tags($castName));
                if (preg_match('/标签|链接|说明|技术参数|百度|豆瓣|IMDb/iu', $castName)) continue;
                if ($castName !== '' && mb_strlen($castName, 'UTF-8') > 1 && !in_array($castName, array_column($heroCast, 'name'), true)) $heroCast[] = ['name' => $castName];
            }
            $castText = $heroMatch('/(?:◎\s*)?(?:领\s*衔\s*)?(?:主\s*演|演\s*员|主要\s*演\s*员)\s*[:：]?\s*([\s\S]*?)(?=\s*(?:◎\s*)?(?:简\s*介|导\s*演|类\s*别|片\s*长|演员|技术参数|标\s*签|标\s*签说明|百度|豆瓣|IMDb)|\[img\]|$)/u', $heroText);
            if ($castText !== '') foreach (preg_split('/[、，,\/＆&;；\r\n]+/u', $castText) as $castName) {
                $castName = trim(strip_tags($castName));
                if (preg_match('/标\s*签|百度|豆瓣|IMDb|链接|说明|技术参数/iu', $castName)) continue;
                if ($castName !== '' && mb_strlen($castName, 'UTF-8') > 1 && !in_array($castName, array_column($heroCast, 'name'), true)) $heroCast[] = ['name' => $castName];
            }
            if (!$heroCast && preg_match('/(?:\)|）)\s*([^\-—\r\n]+?)\s*[\-—]\s*[^\-—\r\n]+$/u', (string) ($row['name'] ?? ''), $nameCast)) {
                foreach (preg_split('/[、&＆\/，,;；]+/u', trim($nameCast[1])) as $castName) {
                    $castName = trim(preg_replace('/等+$/u', '', $castName));
                    if ($castName !== '' && mb_strlen($castName, 'UTF-8') > 1) $heroCast[] = ['name' => $castName];
                }
            }
            $directorText = $heroMatch('/(?:◎\s*)?导\s*演\s*[:：]?\s*([\s\S]*?)(?=\s*(?:◎\s*)?(?:简\s*介|主\s*演|类\s*别|片\s*长|演员|技术参数)|\[img\]|$)/u', $heroText);
            if ($directorText !== '') foreach (preg_split('/[,，\/\r\n]+/u', $directorText) as $directorName) {
                $directorName = trim(strip_tags($directorName));
                if ($directorName !== '' && mb_strlen($directorName, 'UTF-8') > 1 && !in_array($directorName, array_column($heroDirector, 'name'), true)) $heroDirector[] = ['name' => $directorName];
            }
            if ($heroTitle === '') $heroTitle = trim((string) $row['name']);

            $heroEsc = static function ($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); };
            $heroMeta = [];
            if ($heroRuntime !== '') $heroMeta[] = '<span class="nova-movie-hero-chip"><b>◷</b> '.$heroEsc($heroRuntime).'</span>';
            if ($heroCountry) $heroMeta[] = '<span class="nova-movie-hero-chip"><b>▣</b> '. $heroEsc(implode(', ', array_unique($heroCountry))).'</span>';
            if ($heroLanguage !== '') $heroMeta[] = '<span class="nova-movie-hero-chip"><b>◉</b> '.$heroEsc($heroLanguage).'</span>';
            if ($heroGenres) $heroMeta[] = '<span class="nova-movie-hero-chip"><b>◈</b> '.$heroEsc(implode(', ', array_unique($heroGenres))).'</span>';
            if ($heroDirector) {
                $directorNames = [];
                foreach (array_slice($heroDirector, 0, 5) as $director) if (!empty($director['name'])) $directorNames[] = $director['name'];
                if ($directorNames) $heroMeta[] = '<span class="nova-movie-hero-chip"><b>导</b> 导演：'.$heroEsc(implode('、', array_unique($directorNames))).'</span>';
            }
            // Merge bilingual/short-name duplicates and keep the record that has a real photo.
            $heroDisplayCast = [];
            foreach ($heroCast as $cast) {
                $castName = trim((string) ($cast['name'] ?? ''));
                if ($castName === '' || preg_match('/标\s*签|链\s*接|说\s*明|技术\s*参数|百度|豆瓣|IMDb/iu', $castName)) continue;
                $displayName = $castName;
                if (preg_match('/[\x{3400}-\x{9fff}·]{2,}/u', $castName, $castChinese)) $displayName = $castChinese[0];
                $castKey = mb_strtolower((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $displayName), 'UTF-8');
                if ($castKey === '') continue;
                $matchedKey = null;
                foreach ($heroDisplayCast as $existingKey => $existingCast) {
                    if ($existingKey === $castKey || (mb_strlen($castKey, 'UTF-8') >= 2 && (mb_strpos($existingKey, $castKey) !== false || mb_strpos($castKey, $existingKey) !== false))) {
                        $matchedKey = $existingKey;
                        break;
                    }
                }
                $normalizedCast = ['name' => $displayName, 'photo' => trim((string) ($cast['photo'] ?? ''))];
                if ($matchedKey === null) {
                    $heroDisplayCast[$castKey] = $normalizedCast;
                } elseif ($heroDisplayCast[$matchedKey]['photo'] === '' && $normalizedCast['photo'] !== '') {
                    $heroDisplayCast[$matchedKey] = $normalizedCast;
                }
            }
            $heroCastHtml = '';
            foreach (array_slice(array_values($heroDisplayCast), 0, 12) as $cast) {
                if (!empty($cast['name'])) {
                    $photo = !empty($cast['photo'])
                        ? '<img src="'.$heroEsc($cast['photo']).'" alt="">'
                        : '<img class="nova-movie-hero-avatar-placeholder" src="pic/default_avatar.png" alt="">';
                    $heroCastHtml .= '<span class="nova-movie-hero-cast"><span class="nova-movie-hero-avatar">'.$photo.'</span><em title="'.$heroEsc($cast['name']).'">'.$heroEsc($cast['name']).'</em></span>';
                }
            }
            $heroStyle = $heroCover !== '' ? ' style="--nova-movie-hero-cover: url(\''.$heroEsc($heroCover).'\')"' : '';
            echo '<section class="nova-movie-hero"'.$heroStyle.'>';
            if ($heroCover !== '') echo '<img class="nova-movie-hero-poster" src="'.$heroEsc($heroCover).'" alt="'.$heroEsc($heroTitle).'">';
            echo '<div class="nova-movie-hero-body"><div class="nova-movie-hero-heading"><div class="nova-movie-hero-title">'.$heroEsc($heroTitle).($heroYear !== '' ? ' <small>'.$heroEsc($heroYear).'</small>' : '').'</div>'.($heroRating !== '' ? '<div class="nova-movie-hero-rating">★ '.$heroEsc($heroRating).'</div>' : '').'</div>';
            if ($heroMeta) echo '<div class="nova-movie-hero-meta">'.implode('', $heroMeta).'</div>';
            if ($heroPlot !== '') echo '<div class="nova-movie-hero-plot">'.$heroEsc($heroPlot).'</div>';
            if ($heroCastHtml !== '') echo '<div class="nova-movie-hero-cast-list">'.$heroCastHtml.'</div>';
            echo '</div>';
            echo '</section>';
        } catch (Throwable $heroException) {
            do_log('Movie hero render failed: '.$heroException->getMessage(), 'warning');
        }
        $banned_torrent = ($row["banned"] == 'yes' ? " <b>(<font class=\"striking\">".$lang_functions['text_banned']."</font>)</b>" : "");
		$sp_torrent = get_torrent_promotion_append($row['sp_state'],'word', false, '', 0, '', $row['__ignore_global_sp_state'] ?? false);
		$sp_torrent_sub = get_torrent_promotion_append_sub($row['sp_state'],"",true,$row['added'], $row['promotion_time_type'], $row['promotion_until'], $row['__ignore_global_sp_state'] ?? false);
        $hrImg = get_hr_img($row, $row['search_box_id']);
        $approvalStatusIcon = $torrentRep->renderApprovalStatus($row["approval_status"]);
        $paidIcon = $torrentRep->getPaidIcon($row, 20);
		$activityName = htmlspecialchars($row["name"]);
        if (function_exists('nova_official_seed_is_active') && nova_official_seed_is_active((int)$row['id'])) {
            $activityName = '<span style="color:#df2638;-webkit-text-fill-color:#df2638">' . $activityName . '</span>';
        }
		$s=$activityName.$banned_torrent.$paidIcon.($sp_torrent ? "&nbsp;&nbsp;&nbsp;".$sp_torrent : "").($sp_torrent_sub) . $hrImg . $approvalStatusIcon;
		print("<h1 align=\"center\" id=\"top\">".$s."</h1>\n");
		if (function_exists('nova_official_seed_render_details_notice')) {
            echo nova_official_seed_render_details_notice((int)$row['id']);
        }
        echo '<details class="nova-detail-fold nova-detail-fold-standalone"><summary><span><b>审种助手</b><small>查看审种状态与审核信息</small></span><i>点击展开 / 收起</i></summary><div class="nova-detail-fold-body">';
        nova_render_torrent_review_assistant($row);
        echo '</div></details>';

        echo '<details class="nova-detail-fold nova-detail-fold-standalone"><summary><span><b>资源助推</b><small>查看助推活动与奖励信息</small></span><i>点击展开 / 收起</i></summary><div class="nova-detail-fold-body"><table class="nova-detail-fold-inner"><tbody>';
        nova_sticky_render_detail($row, $CURUSER);
        echo '</tbody></table></div></details>';
        echo '<details class="nova-detail-fold nova-detail-fold-standalone"><summary><span><b>角色与权限</b><small>查看当前角色和扩展权限</small></span><i>点击展开 / 收起</i></summary><div class="nova-detail-fold-body"><table class="nova-detail-fold-inner"><tbody>';
        nova_permission_render_detail($row, $CURUSER);
        echo '</tbody></table></div></details>';

        //Banned reason
        if ($row['approval_status'] == \App\Models\Torrent::APPROVAL_STATUS_DENY) {
            $torrentOperationLog = \App\Models\TorrentOperationLog::query()
                ->where('torrent_id', $row['id'])
                ->where('action_type', \App\Models\TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY)
                ->orderBy('id', 'desc')
                ->first();
            if ($torrentOperationLog) {
                $dangerIcon = '<svg t="1655242121471" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="46590" width="16" height="16"><path d="M963.555556 856.888889a55.978667 55.978667 0 0 1-55.978667 56.007111c-0.284444 0-0.540444-0.085333-0.824889-0.085333l-0.056889 0.085333H110.734222l-0.654222-1.137778A55.409778 55.409778 0 0 1 56.888889 856.462222c0-9.756444 2.730667-18.773333 7.139555-26.737778l-3.726222-6.599111L453.461333 156.302222A59.335111 59.335111 0 0 1 510.236444 113.777778c26.936889 0 49.436444 18.005333 56.803556 42.552889l389.973333 661.447111-3.669333 6.997333c6.4 9.102222 10.211556 20.138667 10.211556 32.113778z m-497.777778-541.326222l16.014222 312.888889h56.888889l16.014222-312.888889h-88.917333z m44.458666 398.222222a56.888889 56.888889 0 1 0-0.028444 113.749333 56.888889 56.888889 0 0 0 0.028444-113.749333z" p-id="46591" fill="#d81e06" data-spm-anchor-id="a313x.7781069.0.i61" class="selected"></path></svg>';
                printf(
                    '<div style="display: flex; justify-content: center;margin-bottom: 10px"><div style="display: flex;background-color: black; color: white;font-weight: bold; padding: 10px 100px">%s&nbsp;%s</div></div>',
                    $dangerIcon, nexus_trans('torrent.approval.deny_comment_show', ['reason' => $torrentOperationLog->comment])
                );
            }
        }

		print("<table width=\"97%\" cellspacing=\"0\" cellpadding=\"5\">\n");

		$url = "edit.php?id=" . $row["id"];
		if (isset($_GET["returnto"])) {
			$url .= "&returnto=" . rawurlencode($_GET["returnto"]);
		}
		$editlink = "a title=\"".$lang_details['title_edit_torrent']."\" href=\"$url\"";

		// ------------- start upped by block ------------------//
		if($row['anonymous'] == 'yes') {
			if (!user_can('viewanonymous') && $row['owner'] != $CURUSER['id'])
			$uprow = "<i>".$lang_details['text_anonymous']."</i>";
			else
			$uprow = "<i>".$lang_details['text_anonymous']."</i> (" . get_username($row['owner'], false, true, true, false, false, true, "", false, false) . ")";
		}
		else {
			$uprow = (isset($row['owner']) ? get_username($row['owner'], false, true, true, false, false, true, "", false, false) : "<i>".$lang_details['text_unknown']."</i>");
		}
		if ($CURUSER["id"] == $row["owner"])
			$CURUSER["downloadpos"] = "yes";
		if ($CURUSER["downloadpos"] != "no")
		{
			print("<tr><td class=\"rowhead\" width=\"13%\">".$lang_details['row_download']."</td><td class=\"rowfollow\" width=\"87%\" align=\"left\">");
			if ($CURUSER['timetype'] != 'timealive')
				$uploadtime = $lang_details['text_at'].$row['added'];
			else $uploadtime = $lang_details['text_blank'].gettime($row['added'],true,false);
			print("<a class=\"index\" href=\"download.php?id=$id\">" . htmlspecialchars($torrentnameprefix ."." .$row["save_as"]) . ".torrent</a>&nbsp;&nbsp;<a id=\"bookmark0\" href=\"javascript: bookmark(".$row['id'].",0);\">".get_torrent_bookmark_state($CURUSER['id'], $row['id'], false)."</a>&nbsp;&nbsp;&nbsp;".$lang_details['row_upped_by']."&nbsp;".$uprow.$uploadtime);
			print("</td></tr>");
		}
		else
			tr($lang_details['row_download'], $lang_details['text_downloading_not_allowed']);
		if ($smalldescription_main == 'yes')
			tr($lang_details['row_small_description'],htmlspecialchars(trim($row["small_descr"])),true);

		//tag
        $torrentTags = \App\Models\TorrentTag::query()->where('torrent_id', $row['id'])->get();
        if ($torrentTags->isNotEmpty()) {
            $tagRep = new \App\Repositories\TagRepository();
            tr($lang_details['row_tags'], $tagRep->renderSpan($row['search_box_id'], $torrentTags->pluck('tag_id')->toArray()),true);
        }

		$size_info =  "<b>".$lang_details['text_size']."</b>" . mksize($row["size"]);
		$type_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['row_type'].":</b>&nbsp;".$row["cat_name"];
//        $source_info = $medium_info = $codec_info = $audiocodec_info = $standard_info = $processing_info = $team_info = '';
//		if (isset($row["source_name"]))
//			$source_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_source']."&nbsp;</b>".$row['source_name'];
//		if (isset($row["medium_name"]))
//			$medium_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_medium']."&nbsp;</b>".$row['medium_name'];
//		if (isset($row["codec_name"]))
//			$codec_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_codec']."&nbsp;</b>".$row['codec_name'];
//		if (isset($row["standard_name"]))
//			$standard_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_stardard']."&nbsp;</b>".$row['standard_name'];
//		if (isset($row["processing_name"]))
//			$processing_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_processing']."&nbsp;</b>".$row['processing_name'];
//		if (isset($row["team_name"]))
//			$team_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_team']."&nbsp;</b>".$row['team_name'];
//		if (isset($row["audiocodec_name"]))
//			$audiocodec_info = "&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_audio_codec']."&nbsp;</b>".$row['audiocodec_name'];

//		tr($lang_details['row_basic_info'], $size_info.$type_info.$source_info . $medium_info. $codec_info . $audiocodec_info. $standard_info . $processing_info . $team_info, 1);

        $taxonomyInfo = $searchBoxRep->listTaxonomyInfo($row['search_box_id'], $row);
        $taxonomyRendered = '';
        foreach ($taxonomyInfo as $item) {
            $taxonomyRendered .= sprintf('&nbsp;&nbsp;&nbsp;<b>%s: </b>%s', $item['label'], $item['value']);
        }
        tr($lang_details['row_basic_info'], $size_info.$type_info.$taxonomyRendered, 1);
		nova_render_iyuu_torrent_match($row);
		$actions = [];
        if ($CURUSER["downloadpos"] != "no") {
            $hasBuy = \App\Models\TorrentBuyLog::query()->where('uid', $CURUSER['id'])->where('torrent_id', $id)->exists();
            if ($row['price'] > 0) {
                if ($hasBuy) {
                    $downloadBtn = $lang_details['text_download_bought_torrent'];
                } else {
                    $downloadBtn = sprintf($lang_details['text_download_paid_torrent'], number_format($row['price']));
                }
            } else {
                $downloadBtn = $lang_details['text_download_torrent'];
            }
            $actions[] = "<a title=\"".$lang_details['title_download_torrent']."\" href=\"download.php?id=".$id."\"><img class=\"dt_download\" src=\"pic/trans.gif\" alt=\"download\" />&nbsp;<b><font class=\"small\">".$downloadBtn."</font></b></a>";
        }
        if ($owned == 1) {
            $actions[] = "<$editlink><img class=\"dt_edit\" src=\"pic/trans.gif\" alt=\"edit\" />&nbsp;<b><font class=\"small\">".(user_can('torrentmanage') ? $lang_details['text_edit_and_delete_torrent'] : $lang_details['text_edit_torrent']). "</font></b></a>";
        }
        if (user_can('askreseed') && $row['seeders'] == 0) {
            $actions[] = "<a title=\"".$lang_details['title_ask_for_reseed']."\" href=\"takereseed.php?reseedid=$id\"><img class=\"dt_reseed\" src=\"pic/trans.gif\" alt=\"reseed\">&nbsp;<b><font class=\"small\">".$lang_details['text_ask_for_reseed'] ."</font></b></a>";
        }
        if (user_can('torrent-approval') && (get_setting('torrent.approval_status_icon_enabled') == 'yes' || get_setting('torrent.approval_status_none_visible') == 'no')) {
            $approvalIcon = '<svg t="1655224943277" class="icon" viewBox="0 0 1397 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="45530" width="16" height="16"><path d="M1396.363636 121.018182c0 0-223.418182 74.472727-484.072727 372.363636-242.036364 269.963636-297.890909 381.672727-390.981818 530.618182C512 1014.690909 372.363636 744.727273 0 549.236364l195.490909-186.181818c0 0 176.872727 121.018182 297.890909 344.436364 0 0 307.2-474.763636 902.981818-707.490909L1396.363636 121.018182 1396.363636 121.018182zM1396.363636 121.018182" p-id="45531" fill="#e78d0f"></path></svg>';
            $actions[] = sprintf(
                '<a href="javascript:;"><b><font id="approval" class="small approval" data-torrent_id="%s">%s&nbsp;%s</font></b></a>',
                $row['id'], $approvalIcon, $lang_details['action_approval']
            );
            $title = nexus_trans('torrent.approval.modal_title');
            $js = <<<JS
jQuery('#approval').on("click", function () {
    let torrentId = jQuery(this).attr('data-torrent_id')
    layer.open({
        type: 2,
        title: '$title',
        area: ['60%', '600px'],
        content: '/web/torrent-approval-page?torrent_id=' + torrentId,
    })
})
JS;
            \Nexus\Nexus::js($js, 'footer', false);
        }
        $actions = apply_filter('torrent_detail_actions', $actions, $row);
        $actions[] = "<a title=\"".$lang_details['title_report_torrent']."\" href=\"report.php?torrent=$id\"><img class=\"dt_report\" src=\"pic/trans.gif\" alt=\"report\" />&nbsp;<b><font class=\"small\">".$lang_details['text_report_torrent']."</font></b></a>";
		tr($lang_details['row_action'], implode('&nbsp;|&nbsp;', $actions), 1);

        // ------------- start claim block ------------------//
        $claimTorrentTTL = \App\Models\Claim::getConfigTorrentTTL();
        if (\App\Models\Claim::getConfigIsEnabled() && \Carbon\Carbon::parse($row['added'])->addDays($claimTorrentTTL)->lte(\Carbon\Carbon::now())) {
            $baseClaimQuery = \App\Models\Claim::query()->where('torrent_id', $id);
            $claimCounts = (clone $baseClaimQuery)->count();
            $isClaimed = (clone $baseClaimQuery)->where('uid', $CURUSER['id'])->exists();
            if ($isClaimed) {
                $inputValue = $lang_details['claim_already'];
                $disabled = ' disabled';
            } else {
                $inputValue = $lang_details['claim_now'];
                $disabled = '';
                $claimJs = <<<JS
jQuery('#add-claim').on('click', function () {
    if (!window.confirm('{$lang_details['claim_confirm']}')) {
        return
    }
    let params = {action: "addClaim", params: {"torrent_id": jQuery(this).attr('data-torrent_id')}}
    jQuery.post("ajax.php", params, function (response) {
        console.log(response)
        if (response.ret != 0) {
            alert(response.msg)
        } else {
            window.location.reload()
        }
    }, 'json')
})
JS;
                \Nexus\Nexus::js($claimJs, 'footer', false);
            }
            $maxUserCounts = get_setting('torrent.claim_torrent_user_counts_up_limit', \App\Models\Claim::USER_UP_LIMIT);
            $y = sprintf('<input type="button" value="%s" id="add-claim" data-torrent_id="%s"%s>', $inputValue, $id, $disabled);
            $y .= sprintf('&nbsp;' . $lang_details['claim_info'], $claimCounts, bcsub($maxUserCounts, $claimCounts));
            $y .= sprintf('&nbsp;<b><a href="claim.php?torrent_id=%s">'.$lang_details['claim_detail'].'</a></b>', $id);
            tr($lang_details['claim_label'], $y, 1);
        }
        // ------------- end claim block ------------------//

        tr($lang_details['torrent_dl_url'],sprintf('<a title="%s" href="%s">%s</a>',$lang_details['torrent_dl_url_notice'], $torrentRep->getDownloadUrl($id, $CURUSER), $lang_details['torrent_dl_url_text']),1);

		// ---------------- start subtitle block -------------------//
        $subTorrentIdArr = [$row['id']];
        $otherCopiesIdArr = [];
        if ($imdb_id) {
            $otherCopiesIdArr = \App\Models\Torrent::query()->where('url', $imdb_id)->where('id', '!=', $row['id'])->pluck('id')->toArray();
//            $subTorrentIdArr = array_merge($subTorrentIdArr, $otherCopiesIdArr);
        }
		$r = sql_query("SELECT subs.*, language.flagpic, language.lang_name FROM subs LEFT JOIN language ON subs.lang_id=language.id WHERE torrent_id in(" . implode(',', $subTorrentIdArr). ") ORDER BY subs.lang_id ASC") or sqlerr(__FILE__, __LINE__);
		print("<tr><td class=\"rowhead\" valign=\"top\">".$lang_details['row_subtitles']."</td>");
		print("<td class=\"rowfollow\" align=\"left\" valign=\"top\">");
		print("<table border=\"0\" cellspacing=\"0\">");
		if (mysql_num_rows($r) > 0)
		{
			while($a = mysql_fetch_assoc($r))
			{
				$lang = "<tr><td class=\"embedded\"><img border=\"0\" src=\"pic/flag/". $a["flagpic"] . "\" alt=\"" . $a["lang_name"] . "\" title=\"" . $a["lang_name"] . "\" style=\"padding-bottom: 4px\" /></td>";
				$lang .= "<td class=\"embedded\">&nbsp;&nbsp;<a href=\"downloadsubs.php?torrentid=".$a['torrent_id']."&subid=".$a['id']."\"><u>". htmlspecialchars($a["title"]) . "</u></a>".(user_can('submanage') || (user_can('delownsub') && $a["uppedby"] == $CURUSER["id"]) ? " <font class=\"small\"><a href=\"subtitles.php?delete=".$a['id']."\">[".$lang_details['text_delete']."</a>]</font>" : "")."</td><td class=\"embedded\">&nbsp;&nbsp;".($a["anonymous"] == 'yes' ? $lang_details['text_anonymous'] . (user_can('viewanonymous') ? get_username($a['uppedby'],false,true,true,false,true) : "") : get_username($a['uppedby']))."</td></tr>";
				print($lang);
			}
		}
		else
			print("<tr><td class=\"embedded\">".$lang_details['text_no_subtitles']."</td></tr>");
		print("</table>");
		print("<table border=\"0\" cellspacing=\"0\"><tr>");
		if($CURUSER['id']==$row['owner']  ||  user_can('uploadsub'))
		{
			print("<td class=\"embedded\"><form method=\"post\" action=\"subtitles.php\"><input type=\"hidden\" name=\"torrent_name\" value=\"" . $row["name"]. "\" /><input type=\"hidden\" name=\"detail_torrent_id\" value=\"" . $row["id"]. "\" /><input type=\"hidden\" name=\"in_detail\" value=\"in_detail\" /><input type=\"submit\" value=\"".$lang_details['submit_upload_subtitles']."\" /></form></td>");
		}
		$moviename = "";
		if ($imdb_id && $showextinfo['imdb'] == 'yes')
		{
			$thenumbers = $imdb_id;
			if (!$moviename = $Cache->get_value('imdb_id_'.$thenumbers.'_movie_name')){
				switch ($imdb->getCacheStatus($imdb_id)){
					case "1":{
						$moviename = $movie->title (); break;
						$Cache->cache_value('imdb_id_'.$thenumbers.'_movie_name', $moviename, 1296000);
					}
					default: break;
				}
			}
		}
		print("<td class=\"embedded\"><form method=\"get\" action=\"https://assrt.net/sub/\" target=\"_blank\"><input type=\"text\" name=\"searchword\" id=\"keyword\" style=\"width: 250px\" value=\"".$moviename."\" /><input type=\"submit\" value=\"".$lang_details['submit_search_at_shooter']."\" /></form></td><td class=\"embedded\"><form method=\"get\" action=\"https://www.opensubtitles.org/en/search2/\" target=\"_blank\"><input type=\"hidden\" id=\"moviename\" name=\"MovieName\" /><input type=\"hidden\" name=\"action\" value=\"search\" /><input type=\"hidden\" name=\"SubLanguageID\" value=\"all\" /><input onclick=\"document.getElementById('moviename').value=document.getElementById('keyword').value;\" type=\"submit\" value=\"".$lang_details['submit_search_at_opensubtitles']."\" /></form></td>\n");
		print("</tr></table>");
		print("</td></tr>\n");
        // ---------------- end subtitle block -------------------//

        //hook before desc
        do_action('torrent_detail_before_desc', $row['id'], $CURUSER['id']);

        /**************start custom fields****************/
        echo $customField->renderOnTorrentDetailsPage($id, $row['search_box_id']);

        /**************end custom fields****************/

        //technical info
        if ($settingMain['enable_technical_info'] == 'yes') {
            $technicalData = $row['technical_info'] ?? '';
            
            // 判断是否为BDINFO格式
            $isBdInfo = false;
            if (!empty($technicalData)) {
                $firstLine = strtok($technicalData, "\n");
                if (strpos($firstLine, 'DISC INFO') !== false 
				|| strpos($firstLine, 'Disc Title') !== false
				|| strpos($firstLine, 'Disc Label') !== false
				) {
                    $isBdInfo = true;
                }
            }
            
            if ($isBdInfo) {
                // 使用BdInfoExtra处理BDINFO格式
                $technicalInfo = new \Nexus\Torrent\BdInfoExtra($technicalData);
            } else {
                // 使用TechnicalInformation处理MediaInfo格式
                $technicalInfo = new \Nexus\Torrent\TechnicalInformation($technicalData);
            }
            
            $technicalInfoResult = $technicalInfo->renderOnDetailsPage();
            if (!empty($technicalInfoResult)) {
                tr($lang_functions['text_technical_info'], $technicalInfoResult, 1);
            }
        }

		if ($CURUSER['showdescription'] != 'no' && !empty($row["descr"])){
            $torrentdetailad=$Advertisement->get_ad('torrentdetail');
            $rawDesc = (string) $row['descr'];
            $desc = format_comment($rawDesc);
            if (preg_match('/[◎◦○●]\s*片\s*名/u', $rawDesc) && preg_match('/[◎◦○●]\s*简\s*介/u', $rawDesc)) {
                $profileLines = preg_split('/\r\n|\r|\n/', preg_replace('/<br\s*\/?\s*>/i', "\n", html_entity_decode($rawDesc, ENT_QUOTES, 'UTF-8')));
                $profileMeta = [];
                $profileCast = [];
                $profilePrelude = [];
                $profileSections = [];
                $profileSection = '';
                $profilePattern = '/^\s*[@＠◎◦○●]?\s*(译\s*名|片\s*名|年\s*代|产\s*地|类\s*别|语\s*言|上映\s*日期|IMDb\s*链接|IMDb\s*评分|豆瓣\s*评分|豆瓣\s*链接|TMDB\s*评分|TMDB\s*链接|季\s*数|集\s*数|片\s*长|导\s*演|编\s*剧|主\s*演|简\s*介|获奖\s*情况|标\s*签|标\s*签说明)\s*(.*)$/ui';
                foreach ($profileLines as $profileLine) {
                    if (preg_match('/^\s*[@＠]?\s*标\s*签(?:说明)?\b/iu', $profileLine)) {
                        $profileSection = '__ignore__';
                        continue;
                    }
                    if (preg_match($profilePattern, $profileLine, $profileMatch)) {
                        $profileLabel = preg_replace('/\s+/u', '', trim($profileMatch[1]));
                        $profileValue = trim($profileMatch[2], " \t　:：");
                        if ($profileLabel === '标签' || $profileLabel === '标签说明') {
                            $profileSection = '__ignore__';
                        } elseif ($profileLabel === '简介' || $profileLabel === '获奖情况') {
                            $profileSection = $profileLabel;
                            if (!isset($profileSections[$profileSection])) $profileSections[$profileSection] = [];
                            if ($profileValue !== '') $profileSections[$profileSection][] = $profileValue;
                        } elseif ($profileLabel === '主演') {
                            $profileSection = '主演';
                            if ($profileValue !== '') $profileCast[] = $profileValue;
                        } else {
                            $profileSection = '';
                            $profileMeta[] = [$profileLabel, $profileValue];
                        }
                        continue;
                    }
                    $profileValue = trim($profileLine, " \t　");
                    if ($profileValue === '') {
                        if (isset($profileSections[$profileSection]) && $profileSections[$profileSection]) $profileSections[$profileSection][] = '';
                        continue;
                    }
                    if ($profileSection === '__ignore__') {
                        continue;
                    } elseif ($profileSection === '主演') {
                        $profileCast[] = $profileValue;
                    } elseif ($profileSection !== '' && isset($profileSections[$profileSection])) {
                        $profileSections[$profileSection][] = $profileValue;
                    } else {
                        $profilePrelude[] = $profileLine;
                    }
                }
                $profileHtml = '<div class="nova-resource-profile">';
                if ($profilePrelude) $profileHtml .= '<div class="nova-resource-profile-media">'.format_comment(implode("\n", $profilePrelude)).'</div>';
                if ($profileMeta) {
                    $profileHtml .= '<div class="nova-resource-profile-meta">';
                    foreach ($profileMeta as $profileItem) {
                        $profileMetaClass = 'nova-resource-profile-meta-row';
                        if (in_array($profileItem[0], ['TMDB评分', '豆瓣评分', 'IMDb评分'], true)) $profileMetaClass .= ' is-rating';
                        if (in_array($profileItem[0], ['TMDB链接', '豆瓣链接', 'IMDb链接'], true)) $profileMetaClass .= ' is-link';
                        if (in_array($profileItem[0], ['季数', '集数'], true)) $profileMetaClass .= ' is-count';
                        $profileHtml .= '<div class="'.$profileMetaClass.'"><b>'.htmlspecialchars($profileItem[0], ENT_QUOTES, 'UTF-8').'</b><span>'.format_comment($profileItem[1]).'</span></div>';
                    }
                    $profileHtml .= '</div>';
                }
                if ($profileCast) {
                    $profileHtml .= '<section class="nova-resource-profile-section"><h3>主演</h3><div class="nova-resource-profile-cast">';
                    foreach (array_unique($profileCast) as $profileActor) $profileHtml .= '<span>'.htmlspecialchars($profileActor, ENT_QUOTES, 'UTF-8').'</span>';
                    $profileHtml .= '</div></section>';
                }
                foreach (['简介', '获奖情况'] as $profileSectionName) {
                    if (empty($profileSections[$profileSectionName])) continue;
                    $profileSectionText = trim(implode("\n", $profileSections[$profileSectionName]));
                    if ($profileSectionText === '') continue;
                    $profileHtml .= '<section class="nova-resource-profile-section nova-resource-profile-'.($profileSectionName === '简介' ? 'plot' : 'awards').'"><h3>'.htmlspecialchars($profileSectionName, ENT_QUOTES, 'UTF-8').'</h3><div>'.format_comment($profileSectionText).'</div></section>';
                }
                $desc = $profileHtml.'</div>';
            }
            $desc = apply_filter('torrent_detail_description', $desc, $row['id'], $CURUSER['id']);
            tr("<a href=\"javascript: klappe_news('descr')\"><span class=\"nowrap\"><img class=\"minus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picdescr\" title=\"".($lang_details['title_show_or_hide'] ?? '')."\" /> ".$lang_details['row_description']."</span></a>", "<div id='kdescr'>".($Advertisement->enable_ad() && $torrentdetailad ? "<div align=\"left\" style=\"margin-bottom: 10px\" id=\"\">".$torrentdetailad[0]."</div>" : "").$desc."</div>", 1);
		}

		if (user_can('viewnfo') && $CURUSER['shownfo'] != 'no' && $row["nfosz"] > 0){
			if (!$nfo = $Cache->get_value('nfo_block_torrent_id_'.$id)){
				$nfo = code_new($row["nfo"], get_setting('torrent.nfo_view_style_default'));
				$Cache->cache_value('nfo_block_torrent_id_'.$id, $nfo, 604800);
			}
			tr("<a href=\"javascript: klappe_news('nfo')\"><img class=\"plus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picnfo\" title=\"".$lang_details['title_show_or_hide']."\" /> ".$lang_details['text_nfo']."</a><br /><a href=\"viewnfo.php?id=".$row['id']."\" class=\"sublink\">". $lang_details['text_view_nfo']. "</a>", "<div id='knfo' style=\"display: none;\"><pre style=\"font-size:10pt; font-family: 'Courier New', monospace;white-space: break-spaces\">".$nfo."</pre></div>\n", 1);
		}

	if ($imdb_id && $showextinfo['imdb'] == 'yes' && $CURUSER['showimdb'] != 'no')
	{
		$thenumbers = $imdb_id;
		$Cache->new_page('imdb_id_'.$thenumbers.'_large', 3600*24, true);
		if (!$Cache->get_page()){
			switch ($imdb->getCacheStatus($imdb_id))
			{
				case "0" : //cache is not ready, try to
				{
					if($row['cache_stamp']==0 || ($row['cache_stamp'] != 0 && (time()-$row['cache_stamp']) > 120))	//not exist or timed out
						tr($lang_details['text_imdb'] . $lang_details['row_info'] , $lang_details['text_imdb'] . $lang_details['text_not_ready']."<a href=\"retriver.php?id=". $id ."&amp;type=1&amp;siteid=1\">".$lang_details['text_here_to_retrieve'] . $lang_details['text_imdb'],1);
					else
						tr($lang_details['text_imdb'] . $lang_details['row_info'] , "<img src=\"pic/progressbar.gif\" alt=\"\" />&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;" . $lang_details['text_someone_has_requested'].min(max(time()-$row['cache_stamp'],0),120) . $lang_details['text_please_be_patient'],1);
					break;
				}
				case "1" :
					{
						reset_cachetimestamp($row['id']);
                        if (($photo_url = $movie->photo() ) != FALSE)
                            $smallth = "<img src=\"".$photo_url. "\" width=\"105\" onclick=\"Preview(this);\" alt=\"poster\" />";
                        else
                            $smallth = "<img src=\"pic/imdb_pic/nophoto.gif\" alt=\"no poster\" />";

                        $autodata = $imdb->renderDetailsPageDescription($row['id'], $imdb_id);
                        $cache_time = $imdb->getCachedAt($imdb_id);
                        $Cache->add_whole_row();
                        print("<tr>");
                        print("<td class=\"rowhead\"><a href=\"javascript: klappe_ext('imdb')\"><span class=\"nowrap\"><img class=\"minus\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picimdb\" title=\"".$lang_details['title_show_or_hide']."\" /> ".$lang_details['text_imdb'] . $lang_details['row_info'] ."</span></a><div id=\"posterimdb\">".  $smallth."</div></td>");
                        $Cache->end_whole_row();
                        $Cache->add_row();
                        $Cache->add_part();
                        print("<td class=\"rowfollow\" align=\"left\"><div id='kimdb'>".$autodata);
                        $Cache->end_part();
                        $Cache->add_part();
                        print($lang_details['text_information_updated_at'] . date("Y-m-d H:i:s", $cache_time) . $lang_details['text_might_be_outdated']."<a href=\"".htmlspecialchars("retriver.php?id=". $id ."&type=2&siteid=1")."\">".$lang_details['text_here_to_update']);
                        $Cache->end_part();
                        $Cache->end_row();
                        $Cache->add_whole_row();
                        print("</div></td></tr>");
                        $Cache->end_whole_row();
                        $Cache->cache_page();
                        echo $Cache->next_row();
                        $Cache->next_row();
                        echo $Cache->next_part();
                        if (user_can('updateextinfo'))
                            echo $Cache->next_part();
                        echo $Cache->next_row();
                        break;
					}
				case "2" :
					{
						tr($lang_details['text_imdb'] . $lang_details['row_info'] ,$lang_details['text_network_error'],1);
						break;
					}
				case "3" :// not a valid imdb url
				{
					break;
				}
			}
		}
		else{
				echo $Cache->next_row();
				$Cache->next_row();
				echo $Cache->next_part();
				if (user_can('updateextinfo')){
					echo $Cache->next_part();
				}
				echo $Cache->next_row();
		}
	}
		if (!empty($otherCopiesIdArr))
		{
//			$where_area = " url = " . sqlesc((int)$imdb_id) ." AND torrents.id != ".sqlesc($id);
			$where_area = sprintf('torrents.id in (%s)', implode(',', $otherCopiesIdArr));
			$copies_res = sql_query("SELECT torrents.id, torrents.name, torrents.sp_state, torrents.size, torrents.added, torrents.seeders, torrents.leechers, torrents.hr,categories.id AS catid, categories.name AS catname, categories.image AS catimage, $taxonomyFields, categories.mode as search_box_id FROM torrents
    LEFT JOIN categories ON torrents.category=categories.id
    LEFT JOIN sources ON torrents.source = sources.id
    LEFT JOIN media ON torrents.medium = media.id
    LEFT JOIN codecs ON torrents.codec = codecs.id
    LEFT JOIN standards ON torrents.standard = standards.id
    LEFT JOIN teams ON torrents.team = teams.id
    LEFT JOIN audiocodecs ON torrents.audiocodec = audiocodecs.id
    LEFT JOIN processings ON torrents.processing = processings.id
WHERE " . $where_area . " ORDER BY torrents.id DESC") or sqlerr(__FILE__, __LINE__);

			$copies_count = mysql_num_rows($copies_res);
			if($copies_count > 0)
			{
				$s = "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
				$s.="<tr><td class=\"colhead\" style=\"padding: 0px; text-align:center;\">".$lang_details['col_type']."</td><td class=\"colhead\" align=\"left\">".$lang_details['col_name']."</td><td class=\"colhead\" align=\"center\">".$lang_details['col_quality']."</td><td class=\"colhead\" align=\"center\"><img class=\"size\" src=\"pic/trans.gif\" alt=\"size\" title=\"".$lang_details['title_size']."\" /></td><td class=\"colhead\" align=\"center\"><img class=\"time\" src=\"pic/trans.gif\" alt=\"time added\" title=\"".$lang_details['title_time_added']."\" /></td><td class=\"colhead\" align=\"center\"><img class=\"seeders\" src=\"pic/trans.gif\" alt=\"seeders\" title=\"".$lang_details['title_seeders']."\" /></td><td class=\"colhead\" align=\"center\"><img class=\"leechers\" src=\"pic/trans.gif\" alt=\"leechers\" title=\"".$lang_details['title_leechers']."\" /></td></tr>\n";
				while ($copy_row = mysql_fetch_assoc($copies_res))
				{
					$dispname = htmlspecialchars(trim($copy_row["name"]));
					$count_dispname=strlen($dispname);
					$max_lenght_of_torrent_name="80"; // maximum lenght
					if($count_dispname > $max_lenght_of_torrent_name)
					{
						$dispname=substr($dispname, 0, $max_lenght_of_torrent_name) . "..";
					}
//                    $other_source_info = $other_medium_info = $other_codec_info = $other_standard_info = $other_processing_info = '';
//					if (isset($copy_row["source_name"]))
//						$other_source_info = $copy_row['source_name'].", ";
//					if (isset($copy_row["medium_name"]))
//						$other_medium_info = $copy_row['medium_name'].", ";
//					if (isset($copy_row["codec_name"]))
//						$other_codec_info = $copy_row['codec_name'].", ";
//					if (isset($copy_row["standard_name"]))
//						$other_standard_info = $copy_row['standard_name'].", ";
//					if (isset($copy_row["processing_name"]))
//						$other_processing_info = $copy_row['processing_name'].", ";

                    $taxonomyInfo = $searchBoxRep->listTaxonomyInfo($copy_row['search_box_id'], $copy_row);
                    $taxonomyValues = array_column($taxonomyInfo, 'value');
					$sphighlight = get_torrent_bg_color($copy_row['sp_state']);
					$sp_info = get_torrent_promotion_append($copy_row['sp_state'], '', false, '', 0, '', $copy_row['__ignore_global_sp_state'] ?? false);
					$hrImg = get_hr_img($copy_row, $copy_row['search_box_id']);

					$s .= "<tr". $sphighlight."><td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0px'>".return_category_image($copy_row["catid"], "torrents.php?allsec=1&amp;")."</td><td class=\"rowfollow\" align=\"left\"><a href=\"" . htmlspecialchars(get_protocol_prefix() . $BASEURL . "/details.php?id=" . $copy_row["id"]. "&hit=1")."\">" . $dispname ."</a>". $sp_info. $hrImg ."</td>" .
					"<td class=\"rowfollow\" align=\"left\">" .implode(', ', $taxonomyValues). "</td>" .
					"<td class=\"rowfollow\" align=\"center\">" . mksize($copy_row["size"]) . "</td>" .
					"<td class=\"rowfollow nowrap\" align=\"center\">" . str_replace("&nbsp;", "<br />", gettime($copy_row["added"],false)). "</td>" .
					"<td class=\"rowfollow\" align=\"center\">" . $copy_row["seeders"] . "</td>" .
					"<td class=\"rowfollow\" align=\"center\">" . $copy_row["leechers"] . "</td>" .
					"</tr>\n";
				}
				$s .= "</table>\n";
				tr("<a href=\"javascript: klappe_news('othercopy')\"><span class=\"nowrap\"><img class=\"".($copies_count > 5 ? "plus" : "minus")."\" src=\"pic/trans.gif\" alt=\"Show/Hide\" id=\"picothercopy\" title=\"".$lang_details['title_show_or_hide']."\" /> ".$lang_details['row_other_copies']."</span></a>", "<b>".$copies_count.$lang_details['text_other_copies']." </b><br /><div id='kothercopy' style=\"".($copies_count > 5 ? "display: none;" : "display: block;")."\">".$s."</div>",1);
			}
		}

		if ($row["type"] == "multi")
		{
			$files_info = "<b>".$lang_details['text_num_files']."</b>". $row["numfiles"] . $lang_details['text_files'] . "<br />";
			$files_info .= "<span id=\"showfl\"><a href=\"javascript: viewfilelist(".$id.")\" >".$lang_details['text_see_full_list']."</a></span><span id=\"hidefl\" style=\"display: none;\"><a href=\"javascript: hidefilelist()\">".$lang_details['text_hide_list']."</a></span>";
		}
		function hex_esc($matches) {
			return sprintf("%02x", ord($matches[0]));
		}
		$infoTds = [];
		if (!empty($files_info)) {
		    $infoTds[] = "<td class=\"no_border_wide\">" . $files_info . "</td>";
        }
		$infoTds[] = "<td class=\"no_border_wide\"><b>".$lang_details['row_info_hash'].":</b>&nbsp;".preg_replace_callback('/./s', "hex_esc", hash_pad($row["info_hash"]))."</td>";
		if (user_can('torrentstructure')) {
		    $infoTds[] = "<td class=\"no_border_wide\"><b>" . $lang_details['text_torrent_structure'] . "</b><a href=\"torrent_info.php?id=".$id."\">".$lang_details['text_torrent_info_note']."</a></td>";
        }
        tr($lang_details['row_torrent_info'], "<table><tr>" . implode("", $infoTds) . "</tr></table><span id='filelist'></span>",1);
		tr($lang_details['row_hot_meter'], "<table><tr><td class=\"no_border_wide\"><b>" . $lang_details['text_views']."</b>". $row["views"] . "</td><td class=\"no_border_wide\"><b>" . $lang_details['text_hits']. "</b>" . $row["hits"] . "</td><td class=\"no_border_wide\"><b>" .$lang_details['text_snatched'] . "</b><a href=\"viewsnatches.php?id=".$id."\"><b>" . $row["times_completed"]. $lang_details['text_view_snatches'] . "</td><td class=\"no_border_wide\"><b>" . $lang_details['row_last_seeder']. "</b>" . gettime($row["last_action"]) . "</td></tr></table>",1);
		$bwres = sql_query("SELECT uploadspeed.name AS upname, downloadspeed.name AS downname, isp.name AS ispname FROM users LEFT JOIN uploadspeed ON users.upload = uploadspeed.id LEFT JOIN downloadspeed ON users.download = downloadspeed.id LEFT JOIN isp ON users.isp = isp.id WHERE users.id=".$row['owner']);
		$bwrow = mysql_fetch_array($bwres);
		if (isset($bwrow['upname']) && isset($bwrow['downname']) && $bwrow['upname'] && $bwrow['downname'])
			tr($lang_details['row_uploader_bandwidth'], "<img class=\"speed_down\" src=\"pic/trans.gif\" alt=\"Downstream Rate\" /> ".$bwrow['downname']."&nbsp;&nbsp;&nbsp;&nbsp;<img class=\"speed_up\" src=\"pic/trans.gif\" alt=\"Upstream Rate\" /> ".$bwrow['upname']."&nbsp;&nbsp;&nbsp;&nbsp;".$bwrow['ispname'],1);

		/*
		// Health
		$seedersTmp = $row['seeders'];
		$leechersTmp = $row['leechers'];
		if ($leechersTmp >= 1)	// it is possible that there's traffic while have no seeders
		{
			$progressPerTorrent = 0;
			$i = 0;
			$subres = sql_query("SELECT seeder, finishedat, downloadoffset, uploadoffset, ip, port, uploaded, downloaded, to_go, UNIX_TIMESTAMP(started) AS st, connectable, agent, peer_id, UNIX_TIMESTAMP(last_action) AS la, userid FROM peers WHERE torrent = $row[id]") or sqlerr();

			while ($subrow = mysql_fetch_array($subres)) {
				$progressPerTorrent += sprintf("%.2f", 100 * (1 - ($subrow["to_go"] / $row["size"])));
				$i++;
				if ($subrow["seeder"] == "yes")
				$seeders[] = $subrow;
				else
				$downloaders[] = $subrow;
			}
			if ($i == 0)
				$i = 1;
			$progressTotal = sprintf("%.2f", $progressPerTorrent / $i);

			$totalspeed = 0;

			if($seedersTmp >=1)
			{
				if ($seeders) {
					foreach($seeders as $e) {
						$totalspeed = $totalspeed + ($e["uploaded"] - $e["uploadoffset"]) / max(1, ($e["la"] - $e["st"]));
						$totalspeed = $totalspeed + ($e["downloaded"] - $e["downloadoffset"]) / max(1, $e["finishedat"] - $e[st]);
					}
				}
			}

			if ($downloaders) {
				foreach($downloaders as $e) {
					$totalspeed = $totalspeed + ($e["uploaded"] - $e["uploadoffset"]) / max(1, ($e["la"] - $e["st"]));
					$totalspeed = $totalspeed + ($e["downloaded"] - $e["downloadoffset"]) / max(1, ($e["la"] - $e["st"]));
				}
			}

			$avgspeed = $lang_details['text_average_speed']."<b>" . mksize($totalspeed/($seedersTmp+$leechersTmp)) . "/s</b>";
			$totalspeed = $lang_details['text_total_speed']."<b>" . mksize($totalspeed) . "/s</b> ".$lang_details['text_health_note'];
			$health = $lang_details['text_avprogress'] . get_percent_completed_image(floor($progressTotal))." (".round($progressTotal)."%)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<b>".$lang_details['text_traffic']."</b>" . $avgspeed ."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". $totalspeed;
		}
		else
			$health = "<b>".$lang_details['text_traffic']. "</b>" . $lang_details['text_no_traffic'];

		if ($row["visible"] == "no")
			$health = "<b>".$lang_details['text_status']."</b>" . $lang_details['text_dead'] ."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;". $health;

		tr($lang_details['row_health'], $health, 1);*/
		tr("<span id=\"seeders\"></span><span id=\"leechers\"></span>".$lang_details['row_peers']."<br /><span id=\"showpeer\"><a href=\"javascript: viewpeerlist(".$row['id'].");\" class=\"sublink\">".$lang_details['text_see_full_list']."</a></span><span id=\"hidepeer\" style=\"display: none;\"><a href=\"javascript: hidepeerlist();\" class=\"sublink\">".$lang_details['text_hide_list']."</a></span>", "<div id=\"peercount\"><b>".$row['seeders'].$lang_details['text_seeders'].add_s($row['seeders'])."</b> | <b>".$row['leechers'].$lang_details['text_leechers'].add_s($row['leechers'])."</b></div><div id=\"peerlist\"></div>" , 1);
		if (isset($_GET['dllist']) && $_GET['dllist'] == 1)
		{
			$scronload = "viewpeerlist(".$row['id'].")";

echo "<script type=\"text/javascript\">\n";
echo $scronload;
echo "</script>";
		}

        //Add 魔力值奖励功能
        $bonus_array = \App\Models\Setting::getBonusRewardOptions();
        echo '<style type="text/css">
					ul.magic
					{
						cursor:pointer;
						list-style-type:none;
						padding-left:0px;
					}
					ul.magic li
					{
						margin:0px;text-align:center;float:left;width:40px;margin-right:15px; height:21px;background:url("styles/huise.png") no-repeat;
						padding-left:5px;padding-right:5px;
						line-height:20px;
					}
					ul.magic li:hover
					{
						background:url("styles/boli.png") no-repeat
					}
				</style>
		';
        $magic_value_button = '';

        if ($CURUSER['id'] <> $row['owner']) {
            $arr_temp = $bonus_array;
            $bonus_has = $CURUSER['seedbonus'];
            if(intval($bonus_has) < intval($arr_temp[0])){
                $error_bonus_message = $lang_details['magic_have_no_enough_bonus_value'];
                $button_name = "<input class=\"btn\" type=\"button\" value=\"".$error_bonus_message."\" disabled=\"disabled\" />";
                $magic_value_button .= $button_name;
            }else{
                foreach($arr_temp as $key => $each_temp){
                    $each_temp = intval($each_temp);
                    if ($each_temp > 0 && $each_temp <= $bonus_has) {
                        $button_name = $magic_value_button.$key;
                        $magic_button_id = 'magic_value_'.$key;
                        $each_temp_font = '<font style="font-size:8pt;padding-right:5px;">'.('+'.$each_temp).'</font>';
                        $error_bonus_message = $lang_details['magic_have_no_enough_bonus_value'];
                        $button_name = "<li onclick=\"saveMagicValue(".$id.",$each_temp);\">".$each_temp_font."</li>";

                        $magic_value_button .= $button_name;
                    }
                }
            }
        }

        $span_description = $lang_details['span_description_have_given'];
        $span = '<input class="btn" type="button" id="magic_add" style="display:none" value="'.$span_description.'" disabled="disabled" />&nbsp;';
        $whether_have_give_value = 0;
        $give_value = array();
        $no_give = "";
        $add_value ="";

        $tempresult = sql_query ("SELECT count( DISTINCT `userid` ) as count FROM magic WHERE torrentid=".sqlesc($id));
        $count_user = mysql_fetch_array($tempresult);
        $count_user_number = $count_user['count'];

        $give_value_sql = sql_query("SELECT userid,value FROM magic WHERE torrentid=".sqlesc($id)." ORDER BY id DESC");

        $give_value_count = get_row_count("magic", "WHERE torrentid=".sqlesc($id));
        $give_value_all = mysql_num_rows($give_value_sql);
        $sum_value = 0;
        if ($give_value_all) {
            while($rows_t = mysql_fetch_array($give_value_sql)) {
                $give_value_userid = $rows_t["userid"];
                $sum_value += $rows_t["value"]*1;
                if ($give_value_userid == $CURUSER['id']) {
                    $whether_have_give_value = 1;
                    $add_value = $rows_t["value"];
                }
                $give_value[] = get_username($give_value_userid)." ";
            }
        }else $no_give = $lang_details['text_no_magic_added'];

        if(isset($bonus_has) && isset($arr_temp) && intval($bonus_has) < intval($arr_temp[0])){

        }else if ($whether_have_give_value == 0 ) {
            $magic_value_button = '<ul id="listNumber" class="magic">'.$magic_value_button.'</ul>';
        } else {
            $add_value = str_replace("Number",$add_value,$lang_details['magic_value_number']);
            $magic_value_button ="<input class=\"btn\" type=\"button\" value=\"".$add_value."\" disabled=\"disabled\" />";
            //$give_value = get_username($CURUSER['id'])." ".$give_value;
        }

        $show_list = null;
        $show_all = null;
        $show_list_new_number = 6;
        $other_user_str = null;
        $other_user_span = null;
        if(count($give_value) > 0){
            $count_user_span = '<span id="count_user_spa">'.$count_user_number.'</span>';
            $magic_newest_record = '<span id="magic_newest_record">'. $lang_details['magic_newest_record'].'</span>';
            $show_list_description ='('. $magic_newest_record.$lang_details['magic_sum_user_give_number'].')';
            $show_list_description = str_replace('Number',$count_user_span,$show_list_description);
            $output = array_slice($give_value, 0, $show_list_new_number);
            foreach($output as $eachOutput){
                $show_list .= $eachOutput.'  ';
            }
            //other user list
            if(count($give_value) > $show_list_new_number){
                $show_list .= '<span id="ellipsis">&nbsp;......&nbsp;</span>';
                $show_all_description = '['.$lang_details['magic_show_all_description'].']';
                $show_all = '<a herf="#" style="cursor:pointer" onclick="displayOtherUserList()">'.$show_all_description.'</a>'.'<br/>';
                $other_user_list = array_slice($give_value, $show_list_new_number, count($give_value));
                foreach($other_user_list as $each){
                    $other_user_str .= $each.'  ';
                }
                $other_user_span = '<span id="other_user_list" style="display:none">'.$other_user_str.'</span>';
            }
        }else{
            $show_list_description = null;
            $haveGotBonus = $no_give;
        }
        $current_user_magic = "<span id='current_user_magic' style='display:none'>".get_username($CURUSER['id'])."</span>&nbsp;";
        $haveGotBonus = $lang_details['magic_haveGotBonus'].'&nbsp';
        $spanSumAll = '<span id="spanSumAll">'.$sum_value.'</span>';
        $haveGotBonus = str_replace('Number',$spanSumAll,$haveGotBonus);
        $firstLine = '<div style="height:25px">'.$magic_value_button.$span.$haveGotBonus.$show_all.'</div>';
        $otherLine = '<div>'.$current_user_magic.$show_list.$other_user_span.$show_list_description.'</div>';
        tr($lang_details['magic_value_award'],$firstLine.$otherLine,1);
        //End 魔力值奖励功能

		// ------------- start thanked-by block--------------//

		$torrentid = $id;
		$thanksby = "";
		$nothanks = "";
		$thanks_said = 0;
		$thanks_sql = sql_query("SELECT userid FROM thanks WHERE torrentid=".sqlesc($torrentid)." ORDER BY id DESC LIMIT 20");
		$thanksCount = get_row_count("thanks", "WHERE torrentid=".sqlesc($torrentid));
		$thanks_all = mysql_num_rows($thanks_sql);
		if ($thanks_all) {
			while($rows_t = mysql_fetch_array($thanks_sql)) {
				$thanks_userid = $rows_t["userid"];
				if ($rows_t["userid"] == $CURUSER['id']) {
					$thanks_said = 1;
				} else {
					$thanksby .= get_username($thanks_userid)." ";
				}
			}
		}
		else $nothanks = $lang_details['text_no_thanks_added'];

		if (!$thanks_said) {
			$thanks_said = get_row_count("thanks", "WHERE torrentid=$torrentid AND userid=".sqlesc($CURUSER['id']));
		}
		if ($thanks_said == 0) {
			$buttonvalue = " value=\"".$lang_details['submit_say_thanks']."\"";
		} else {
			$buttonvalue = " value=\"".$lang_details['submit_you_said_thanks']."\" disabled=\"disabled\"";
			$thanksby = get_username($CURUSER['id'])." ".$thanksby;
		}
		$thanksbutton = "<input class=\"btn\" type=\"button\" id=\"saythanks\"  onclick=\"saythanks(".$torrentid.");\" ".$buttonvalue." />";
		tr($lang_details['row_thanks_by'],"<span id=\"thanksadded\" style=\"display: none;\"><input class=\"btn\" type=\"button\" value=\"".$lang_details['text_thanks_added']."\" disabled=\"disabled\" /></span><span id=\"curuser\" style=\"display: none;\">".get_username($CURUSER['id'])." </span><span id=\"thanksbutton\">".$thanksbutton."</span>&nbsp;&nbsp;<span id=\"nothanks\">".$nothanks."</span><span id=\"addcuruser\"></span>".$thanksby.($thanks_all < $thanksCount ? $lang_details['text_and_more'].$thanksCount.$lang_details['text_users_in_total'] : ""),1);
		// ------------- end thanked-by block--------------//

		print("</table>\n");
	}
	else {
		stdhead($lang_details['head_comments_for_torrent']."\"" . $row["name"] . "\"");
		print("<h1 id=\"top\">".$lang_details['text_comments_for']."<a href=\"details.php?id=".$id."\">" . htmlspecialchars($row["name"]) . "</a></h1>\n");
	}
	if (!empty($torrentUpdate)) {
        sql_query("UPDATE torrents SET " . join(",", $torrentUpdate) . " WHERE id = $id") or sqlerr(__FILE__, __LINE__);
    }

	// -----------------COMMENT SECTION ---------------------//
if ($CURUSER['showcomment'] != 'no'){
	$count = get_row_count("comments","WHERE torrent=".sqlesc($id));
	if ($count)
	{
		print("<br /><br />");
		print("<h1 align=\"center\" id=\"startcomments\">" .$lang_details['h1_user_comments'] . "</h1>\n");
		list($pagertop, $pagerbottom, $limit) = pager(10, $count, "details.php?id=$id&cmtpage=1&", array('lastpagedefault' => 1), "page");

		$subres = sql_query("SELECT id, text, user, added, editedby, editdate FROM comments WHERE torrent = $id ORDER BY id $limit") or sqlerr(__FILE__, __LINE__);
		$allrows = array();
		while ($subrow = mysql_fetch_array($subres)) {
			$allrows[] = $subrow;
		}
		print($pagertop);
		commenttable($allrows,"torrent",$id);
		print($pagerbottom);
	}
}
print("<br /><br />");
print ("<table style='border:1px solid #000000;'><tr><td class=\"text\" align=\"center\"><b>".$lang_details['text_quick_comment']."</b><br /><br /><form id=\"compose\" name=\"comment\" method=\"post\" action=\"".htmlspecialchars("comment.php?action=add&type=torrent")."\" onsubmit=\"return postvalid(this);\"><input type=\"hidden\" name=\"pid\" value=\"".$id."\" /><br />");
quickreply('comment', 'body', $lang_details['submit_add_comment']);
print("</form></td></tr></table>");
print("<p align=\"center\"><a class=\"index\" href=\"".htmlspecialchars("comment.php?action=add&pid=".$id."&type=torrent")."\">".$lang_details['text_add_a_comment']."</a></p>\n");
}
stdfoot();
