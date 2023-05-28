<?php

namespace Nexus\Torrent;

class TechnicalInformation
{
    private $mediaInfo;

    private $mediaInfoArr;

    public function __construct(string $mediaInfo)
    {
        $this->mediaInfo = $mediaInfo;
        $this->mediaInfoArr = $this->getMediaInfoArr($mediaInfo);
    }

    public function getMediaInfoArr(string $mediaInfo)
    {
        $arr = preg_split('/[\r\n]+/', $mediaInfo);
        $result = [];
        $parentKey = "";
        foreach ($arr as $key => $value) {
            $value = trim($value);
            if (empty($value)) {
                continue;
            }
            $rowKeyValue = explode(':', $value);
            $rowKeyValue = array_filter(array_map('trim', $rowKeyValue));
            if (count($rowKeyValue) == 1) {
                $parentKey = $rowKeyValue[0];
            } elseif (count($rowKeyValue) == 2) {
                if (empty($parentKey)) {
                    continue;
                }
                $result[$parentKey][$rowKeyValue[0]] = $rowKeyValue[1];
            }
        }
        return $result;

    }

    public function getRuntime()
    {
        return $this->mediaInfoArr['General']['Duration'] ?? '';
    }

    public function getResolution()
    {
        $width = $this->mediaInfoArr['Video']['Width'] ?? '';
        $height = $this->mediaInfoArr['Video']['Height'] ?? '';
        $ratio = $this->mediaInfoArr['Video']['Display aspect ratio'] ?? '';
        $result = '';
        if ($width && $height) {
            $result .= $width . ' x ' . $height;
        }
        if ($ratio) {
            $result .= "($ratio)";
        }
        return $result;
    }

    public function getBitrate()
    {
        $result = $this->mediaInfoArr['Video']['Bit rate'] ?? '';
        return $result;
    }

    public function getFramerate()
    {
        $result = $this->mediaInfoArr['Video']['Frame rate'] ?? '';
        return $result;
    }

    public function getProfile()
    {
        $result = $this->mediaInfoArr['Video']['Format profile'] ?? '';
        return $result;
    }

    public function getRefFrame()
    {
        foreach ($this->mediaInfoArr['Video'] ?? [] as $key => $value) {
            if (str_contains($key, 'Reference frames')) {
                return $value;
            }
        }
        return '';
    }

    public function getAudios()
    {
        $result = [];
        foreach ($this->mediaInfoArr as $parentKey => $values) {
            if (strpos($parentKey, 'Audio') === false) {
                continue;
            }
            $audioInfoArr = [];
            if (!empty($values['Language'])) {
                $audioInfoArr[] = $values['Language'];
            }
            if (!empty($values['Format'])) {
                $audioInfoArr[] = $values['Format'];
            }
            if (!empty($values['Channel(s)'])) {
                $audioInfoArr[] = $values['Channel(s)'];
            }
            if (!empty($values['Bit rate'])) {
                $audioInfoArr[]= "@" . $values['Bit rate'];
            }
            if (!empty($audioInfoArr)) {
                $result[$parentKey] = implode(" ", $audioInfoArr);
            }
        }
        return $result;
    }

    public function getSubtitles()
    {
        $result = [];
        foreach ($this->mediaInfoArr as $parentKey => $values) {
            if (strpos($parentKey, 'Text') === false) {
                continue;
            }
            $audioInfoArr = [];
            if (!empty($values['Language'])) {
                $audioInfoArr[] = $values['Language'];
            }
            if (!empty($values['Format'])) {
                $audioInfoArr[] = $values['Format'];
            }
            if (!empty($audioInfoArr)) {
                $result[$parentKey] = implode(" ", $audioInfoArr);
            }
        }
        return $result;
    }

    public function getHDRFormat()
    {
        return $this->mediaInfoArr['Video']['HDR format'] ?? '';
    }

    public function getBitDepth()
    {
        return $this->mediaInfoArr['Video']['Bit depth'] ?? '';
    }

    public function renderOnDetailsPage()
    {
        global $lang_functions;
        $videos = [
            'Runtime' => $this->getRuntime(),
            'Resolution' => $this->getResolution(),
            'Bitrate' => $this->getBitrate(),
            'HDR' => $this->getHDRFormat(),
            'Bit depth' => $this->getBitDepth(),
            'Frame rate' => $this->getFramerate(),
            'Profile' => $this->getProfile(),
            'Ref.Frames' => $this->getRefFrame(),
        ];
        $videos = array_filter($videos);
        $audios = $this->getAudios();
        $subtitles = $this->getSubtitles();
//        dd($videos, $audios, $subtitles);
        if (empty($videos) && empty($audios) && empty($subtitles)) {
            return sprintf('<div class="nexus-media-info-raw"><pre>%s</pre></div>', $this->mediaInfo);
        }

        $result = '<table style="border: none;width: 100%"><tbody><tr>';
        $cols = 0;
        if (!empty($videos)) {
            $cols++;
            $result .= $this->buildTdTable($videos);
        }
        if (!empty($audios)) {
            $cols++;
            $result .= $this->buildTdTable($audios);
        }
        if (!empty($subtitles)) {
            $cols++;
            $result .= $this->buildTdTable($subtitles);
        }
        $result .= '</tr>';
        $rawMediaInfo = sprintf('[spoiler=%s][raw]<pre>%s</pre>[/raw][/spoiler]',  nexus_trans('torrent.show_hide_media_info'), $this->mediaInfo);
        $result .= sprintf('<tr><td colspan="%s" class="nexus-media-info-raw">%s</td></tr>', $cols, format_comment($rawMediaInfo, false));
        $result .= '</tbody></table>';
        return $result;
    }

    private function buildTdTable(array $parts)
    {
        $table = '<table style="border: none;"><tbody>';
        foreach ($parts as $key => $value) {
            $table .= '<tr>';
            $table .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px;"><b>%s: </b>%s</td>', $key, $value);
            $table .= '</tr>';
        }
        $table .= '</tbody>';
        $table .= '</table>';
        return sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px">%s</td>', $table);
    }

}
