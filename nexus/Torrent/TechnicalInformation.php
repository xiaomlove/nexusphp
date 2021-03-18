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
        foreach ($this->mediaInfoArr['Video'] as $key => $value) {
            if (strpos($key, 'Reference frames') !== false) {
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

    public function renderOnDetailsPage()
    {
        global $lang_functions;
        $videos = [
            'Runtime' => $this->getRuntime(),
            'Resolution' => $this->getResolution(),
            'Bitrate' => $this->getBitrate(),
            'Framerate' => $this->getFramerate(),
            'Profile' => $this->getProfile(),
            'Ref.Frames' => $this->getRefFrame(),
        ];
        $videos = array_filter($videos);
        $audios = $this->getAudios();
        $subtitles = $this->getSubtitles();
//        dd($this->mediaInfoArr, $videos, $audios, $subtitles);
        //video part
        $videoTable = '<table style="border: none"><tbody>';
        foreach (array_chunk($videos, 2, true) as $row) {
            $videoTable .= '<tr>';
            foreach ($row as $key => $value) {
                $videoTable .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px"><b>%s: </b>%s</td>', $key, $value);
            }
            $videoTable .= '</tr>';
        }
        $videoTable .= '</tbody></table>';

        $audioTable = '<table style="border: none"><tbody>';
        $audioTable .= '<tr>';
        foreach ($audios as $key => $value) {
            $audioTable .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px"><b>%s: </b>%s</td>', $key, $value);
        }
        $audioTable .= '</tr>';
        $audioTable .= '</tbody></table>';

        $subtitleTable = '<table style="border: none"><tbody>';
        $subtitleTable .= '<tr>';
        foreach ($subtitles as $key => $value) {
            $subtitleTable .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px"><b>%s: </b>%s</td>', $key, $value);
        }
        $subtitleTable .= '</tr>';
        $subtitleTable .= '</tbody></table>';

        if (empty($videos) && empty($audios) && empty($subtitles)) {
            return '';
        }
        $result = '<table style="border: none"><tbody><tr>';
        if (!empty($videos)) {
            $result .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px">%s</td>', $videoTable);
        }
        if (!empty($audios)) {
            $result .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px">%s</td>', $audioTable);
        }
        if (!empty($subtitles)) {
            $result .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px">%s</td>', $subtitleTable);
        }
        $result .= '</tr></tbody></table>';
        return $result;
    }
}