<?php

namespace Nexus\Imdb;

use App\Models\Setting;
use Imdb\Config;
use Imdb\Title;
use Nexus\Database\NexusDB;
use Nexus\PTGen\PTGen;

class Imdb
{
    private $config;

    private $movies = [];

    private $pages = array('Title', 'Credits', 'ReleaseInfo', );

    private $ptGen;

    private TitleCache $titleCache;

    public function __construct()
    {
        $config = new Config();
        $cacheDir = ROOT_PATH . 'imdb/cache/';
        $photoRoot = 'images/';
        $photoDir = ROOT_PATH . "imdb/$photoRoot";
        $this->checkDir($cacheDir, 'imdb_cache_dir');
        $this->checkDir($photoDir, 'imdb_photo_dir');

        $language = get_setting('main.imdb_language', 'en-US');
        $config->cacheDir = $cacheDir;
        $config->cacheUse = true;
        $config->cacheStore = true;
        $config->cacheExpire = 0;
        // photoLocalurl() uses photoroot as filesystem path and photodir as URL.
        $config->photoroot = $photoDir;
        $config->photodir = $photoRoot;
        $config->language = $language;
        if ($language && $language !== 'en-US') {
            $config->useLocalization = true;
            $parts = explode('-', $language);
            $config->country = $parts[1] ?? $config->country;
        }
        $this->config = $config;
        $this->titleCache = new TitleCache($config);
        $this->titleCache->setReadOnly(true);
    }

    public static function listSupportLanguages(): array
    {
        $file = sprintf('%s/resources/lang/%s/imdb.php', ROOT_PATH, get_langfolder_cookie(true));
        $data = include $file;
        return $data['languages'];
    }

    public function setDebug($debug)
    {
        $this->config->debug = $debug;
    }

    private function checkDir($dir, $langKeyPrefix)
    {
        if (!is_dir($dir)) {
            $mkdirResult = mkdir($dir, 0777, true);
            if ($mkdirResult !== true) {
                $msg = nexus_trans("torrent.{$langKeyPrefix}_can_not_create");
                do_log("$msg, dir: $dir");
                throw new ImdbException($msg);
            }
        }
        if (!is_writable($dir)) {
            $msg = nexus_trans("torrent.{$langKeyPrefix}_is_not_writeable");
            do_log("$msg, dir: $dir");
            throw new ImdbException($msg);
        }
        return true;
    }

    public function getCachedAt(int $id)
    {
        $id = parse_imdb_id($id);
        $log = "id: $id";
        $cacheFile = $this->getCacheFilePath($id);
        if (!file_exists($cacheFile)) {
            $log .= ", file: $cacheFile not exits";
        }
        $result = filemtime($cacheFile);
        $log .= ", file: $cacheFile cache at: $result";
        do_log($log);
        return $result;
    }

    /**
     * @date 2021/1/18
     * @param int $id
     * @return int state (0-not complete, 1-cache complete)
     */
    public function getCacheStatus(int $id, bool $queueIfMissing = true)
    {
        $id = parse_imdb_id($id);
        $log = "id: $id";
        $cacheFile = $this->getCacheFilePath($id);
        if (!file_exists($cacheFile)) {
            $log .= ", file: $cacheFile not exits";
            do_log($log);
            if ($queueIfMissing) {
                $this->queueFetchIfMissing($id);
            }
            return 0;
        }
        if (!fopen($cacheFile, 'r')) {
            $log .= ", file: $cacheFile can not open";
            do_log($log);
            if ($queueIfMissing) {
                $this->queueFetchIfMissing($id);
            }
            return 0;
        }
        return 1;
    }

    public function purgeSingle($id)
    {
        $mainCacheFile =  $this->getCacheFilePath($id);
        if (!is_file($mainCacheFile)) {
            do_log("mainCacheFile: $mainCacheFile not exists, return");
        }
        foreach (glob("$mainCacheFile*") ?: [] as $file) {
            if (file_exists($file)) {
                do_log("unlink: $file");
                unlink($file);
            }
        }
        $paddedId = str_pad((string)parse_imdb_id($id), 7, '0', STR_PAD_LEFT);
        foreach ([
            $this->config->cacheDir . 'gql.*tt' . $paddedId . '*',
            $this->config->photoroot . 'tt' . $paddedId . '*.jpg',
            rtrim($this->config->cacheDir, '/') . '/tt' . $paddedId . '.json',
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (file_exists($file)) {
                    do_log("unlink: $file");
                    unlink($file);
                }
            }
        }
        return true;
    }

    public function getMovie($id)
    {
        if (!isset($this->movies[$id])) {
            $this->movies[$id] = new Movie(new Title((string)$id, $this->config, null, $this->titleCache));
        }
        return $this->movies[$id];
    }

    private function getCacheFilePath($id, $suffix = '')
    {
        $id = parse_imdb_id($id);
        $result = sprintf('%stitle.tt%s', $this->config->cacheDir, $id);
        if ($suffix) {
            $result .= ".$suffix";
        }
        return $result;
    }

    public function updateCache($id)
    {
        $id = parse_imdb_id($id);
        $this->titleCache->setReadOnly(false);
        try {
            $movie = $this->getMovie($id);
            $movie->title();
            $movie->photo_localurl();
            $movie->releaseInfo();
            $movie->rating();
            $movie->photo(false);
            $movie->country();
            $movie->director();
            $movie->creator();
            $movie->writing();
            $movie->producer();
            $movie->cast();
            $movie->plotoutline();
            $movie->composer();
            $movie->genres();
            $movie->alsoknow();
            $movie->runtime();
            $movie->language();
            $movie->tagline();
            touch($this->getCacheFilePath($id));
            return true;
        } finally {
            $this->titleCache->setReadOnly(true);
        }
    }

    public function renderDetailsPageDescription($torrentId, $imdbId)
    {
        global $lang_details;
        $movie = $this->getMovie($imdbId);
        $thenumbers = $imdbId;
        $country = $movie->country ();
        $director = $movie->director();
        $creator = $movie->creator(); // For TV series
        $write = $movie->writing();
        $produce = $movie->producer();
        $cast = $movie->cast();
//						$plot = $movie->plot ();
        $plot_outline = $movie->plotoutline();
        $compose = $movie->composer();
        $gen = $movie->genres();
        //$comment = $movie->comment();
//        $similiar_movies = $movie->similiar_movies();

        $autodata = '<a href="https://www.imdb.com/title/tt'.$thenumbers.'">https://www.imdb.com/title/tt'.$thenumbers."</a><br /><strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
        $autodata .= "<font color=\"darkred\" size=\"3\">".$lang_details['text_information']."</font><br />\n";
        $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong><br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">". $lang_details['text_title']."</font></strong>" . "".$movie->title ()."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_also_known_as']."</font></strong>";

        $temp = "";
        foreach ($movie->alsoknow() as $ak)
        {
			$temp .= $ak["title"].($ak["country"] != "" ? " (".$ak["country"].")" : "") . ($ak["comment"] != "" ? " (" . $ak["comment"] . ")" : "") . ", ";
        }
        $autodata .= rtrim(trim($temp), ",");
        $runtime = $movie->runtime();
        if ($runtime === null || $runtime === '') {
            $runtimes = '';
        } elseif (is_numeric($runtime)) {
            $runtimes = $runtime . $lang_details['text_mins'];
        } else {
            $runtimes = str_replace(" min", $lang_details['text_mins'], (string)$runtime);
        }
        $autodata .= "<br />\n<strong><font color=\"DarkRed\">".$lang_details['text_year']."</font></strong>" . "".$movie->year ()."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_runtime']."</font></strong>".$runtimes."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_votes']."</font></strong>" . "".$movie->votes ()."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_rating']."</font></strong>" . "".$movie->rating ()."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_language']."</font></strong>" . "".$movie->language ()."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_country']."</font></strong>";

        $temp = "";
        for ($i = 0; $i < count ($country); $i++)
        {
            $temp .="$country[$i], ";
        }
        $autodata .= rtrim(trim($temp), ",");

        $autodata .= "<br />\n<strong><font color=\"DarkRed\">".$lang_details['text_all_genres']."</font></strong>";
        $temp = "";
        for ($i = 0; $i < count($gen); $i++)
        {
            $temp .= "$gen[$i], ";
        }
        $autodata .= rtrim(trim($temp), ",");

        $autodata .= "<br />\n<strong><font color=\"DarkRed\">".$lang_details['text_tagline']."</font></strong>" . "".$movie->tagline ()."<br />\n";
        if ($director){
            $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_director']."</font></strong>";
            $temp = "";
            for ($i = 0; $i < count ($director); $i++)
            {
                $temp .= "<a target=\"_blank\" href=\"https://www.imdb.com/" . "".$director[$i]["imdb"]."" ."\">" . $director[$i]["name"] . "</a>, ";
            }
            $autodata .= rtrim(trim($temp), ",");
        }
        elseif ($creator)
            $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_creator']."</font></strong>".implode(", ", array_column($creator, 'name'));

        $autodata .= "<br />\n<strong><font color=\"DarkRed\">".$lang_details['text_written_by']."</font></strong>";
        $temp = "";
        for ($i = 0; $i < count ($write); $i++)
        {
            $temp .= "<a target=\"_blank\" href=\"https://www.imdb.com/" . "".$write[$i]["imdb"]."" ."\">" . "".$write[$i]["name"]."" . "</a>, ";
        }
        $autodata .= rtrim(trim($temp), ",");

        $autodata .= "<br />\n<strong><font color=\"DarkRed\">".$lang_details['text_produced_by']."</font></strong>";
        $temp = "";
        for ($i = 0; $i < count ($produce); $i++)
        {
            $temp .= "<a target=\"_blank\" href=\"https://www.imdb.com/" . "".$produce[$i]["imdb"]."" ." \">" . "".$produce[$i]["name"]."" . "</a>, ";
        }
        $autodata .= rtrim(trim($temp), ",");

        $autodata .= "<br />\n<strong><font color=\"DarkRed\">".$lang_details['text_music']."</font></strong>";
        $temp = "";
        for ($i = 0; $i < count($compose); $i++)
        {
            $temp .= "<a target=\"_blank\" href=\"https://www.imdb.com/" . "".$compose[$i]["imdb"]."" ." \">" . "".$compose[$i]["name"]."" . "</a>, ";
        }
        $autodata .= rtrim(trim($temp), ",");

        $autodata .= "<br /><br />\n\n<strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
        $autodata .= "<font color=\"darkred\" size=\"3\">".$lang_details['text_plot_outline']."</font><br />\n";
        $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong>";

//						if(count($plot) == 0)
//						{
//							$autodata .= "<br />\n".$plot_outline;
//						}
//						else
//						{
//							for ($i = 0; $i < count ($plot); $i++)
//							{
//								$autodata .= "<br />\n<font color=\"DarkRed\">.</font> ";
//								$autodata .= $plot[$i];
//							}
//						}
        if (!empty($plot_outline)) {
            $autodata .= "<br />\n".$plot_outline;
        }


        $autodata .= "<br /><br />\n\n<strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
        $autodata .= "<font color=\"darkred\" size=\"3\">".$lang_details['text_cast']."</font><br />\n";
        $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong><br />\n";

        for ($i = 0; $i < count ($cast); $i++)
        {
            if ($i > 9)
            {
                break;
            }
            $autodata .= "<font color=\"DarkRed\">.</font> " . "<a target=\"_blank\" href=\"https://www.imdb.com/" . "".$cast[$i]["imdb"]."" ."\">" . $cast[$i]["name"] . "</a> " .$lang_details['text_as']."<strong><font color=\"DarkRed\">" . "".$cast[$i]["role"]."" . " </font></strong><br />\n";
        }

       return $autodata;

    }

    public function renderTorrentsPageAverageRating($imdbId): string
    {
        return $this->getPtGen()->buildRatingSpan([PTGen::SITE_IMDB => $this->getRating($imdbId)]);
    }

    public function getRating($imdbId): float|string
    {
        $imdbId = parse_imdb_id($imdbId);
        $defaultRating = $rating = 'N/A';
        if ($imdbId && $this->getCacheStatus($imdbId) == 1) {
            $movie = $this->getMovie($imdbId);
            $rating = $movie->rating();
        }
        if (!is_numeric($rating)) {
            $rating = $defaultRating;
        }
        return $rating;
    }

    public function getPtGen()
    {
        if (empty($this->ptGen)) {
            $this->ptGen = new PTGen();
        }
        return $this->ptGen;
    }

    public function getMovieCover($imdbId): string
    {
        static $enabled;
        if (is_null($enabled)) {
            $enabled = Setting::getIsImdbEnabled();
        }
        if (!$enabled) {
            return '';
        }
        return NexusDB::remember(self::getMovieCoverCacheKey($imdbId), 864000, function() use ($imdbId) {
            if ($this->getCacheStatus($imdbId) != 1) {
                return '';
            }
            try {
                return $this->getMovie($imdbId)->photo(false);
            } catch (\Exception $exception) {
                do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                return '';
            }
        });
    }

    public static function getMovieCoverCacheKey($imdbId): string
    {
        return "imdb:cover:$imdbId";
    }

    public static function getFetchQueueLockKey($imdbId): string
    {
        return 'imdb:fetch_queue:' . parse_imdb_id($imdbId);
    }

    public function queueFetchIfMissing($imdbId): void
    {
        $imdbId = parse_imdb_id($imdbId);
        if (!$imdbId) {
            return;
        }
        $lockKey = self::getFetchQueueLockKey($imdbId);
        if (NexusDB::cache_get($lockKey)) {
            return;
        }
        NexusDB::cache_put($lockKey, 1, 600);
        \App\Jobs\FetchImdbCacheJob::dispatch(null, (string) $imdbId);
        do_log("FetchImdbCacheJob dispatched for missing cache, imdb: $imdbId");
    }
}
