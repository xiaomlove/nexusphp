<?php

namespace Nexus\Imdb;

use Imdb\Config;
use Imdb\Title;

class Imdb
{
    private $config;

    public function __construct()
    {
        $config = new Config();
        $config->cachedir = ROOT_PATH . 'imdb/cache';
        $config->photodir = ROOT_PATH . 'imdb/pic_imdb';
        $config->photoroot = 'pic_imdb';
        $this->config = $config;
    }

    public function renderDetailsPageDescription($torrentId, $imdbId)
    {
        $movie = new Title($imdbId, $this->config);
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
        $similiar_movies = $movie->similiar_movies();

        if (($photo_url = $movie->photo_localurl() ) != FALSE)
            $smallth = "<img src=\"".$photo_url. "\" width=\"105\" onclick=\"Preview(this);\" alt=\"poster\" />";
        else
            $smallth = "<img src=\"pic/imdb_pic/nophoto.gif\" alt=\"no poster\" />";

        $autodata = '<a href="https://www.imdb.com/title/tt'.$thenumbers.'">https://www.imdb.com/title/tt'.$thenumbers."</a><br /><strong><font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font><br />\n";
        $autodata .= "<font color=\"darkred\" size=\"3\">".$lang_details['text_information']."</font><br />\n";
        $autodata .= "<font color=\"navy\">------------------------------------------------------------------------------------------------------------------------------------</font></strong><br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">". $lang_details['text_title']."</font></strong>" . "".$movie->title ()."<br />\n";
        $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_also_known_as']."</font></strong>";

        $temp = "";
        foreach ($movie->alsoknow() as $ak)
        {
//							$temp .= $ak["title"].$ak["year"]. ($ak["country"] != "" ? " (".$ak["country"].")" : "") . ($ak["comment"] != "" ? " (" . $ak["comment"] . ")" : "") . ", ";
            $temp .= $ak["title"] . ", ";
        }
        $autodata .= rtrim(trim($temp), ",");
        $runtimes = str_replace(" min",$lang_details['text_mins'], $movie->runtime_all());
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
            $autodata .= "<strong><font color=\"DarkRed\">".$lang_details['text_creator']."</font></strong>".$creator;

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
//							if ($i > 9)
//							{
//								break;
//							}
            $autodata .= "<font color=\"DarkRed\">.</font> " . "<a target=\"_blank\" href=\"https://www.imdb.com/" . "".$cast[$i]["imdb"]."" ."\">" . $cast[$i]["name"] . "</a> " .$lang_details['text_as']."<strong><font color=\"DarkRed\">" . "".$cast[$i]["role"]."" . " </font></strong><br />\n";
        }

    }
}