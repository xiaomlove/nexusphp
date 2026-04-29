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
            $value = $this->trim($value);
            if (empty($value)) {
                continue;
            }
            $rowKeyValue = explode(':', $value);
            $rowKeyValue = array_filter(array_map([$this, 'trim'], $rowKeyValue));
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

    private function trim(string $value): string
    {
        return trim($value, " \n\r\t\v\0\u{A0}");
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
        $audioIndex = 1;
        foreach ($this->mediaInfoArr as $parentKey => $values) {
            if (strpos($parentKey, 'Audio') === false) {
                continue;
            }
            $audioInfoArr = [];
            if (!empty($values['Language'])) {
                $audioInfoArr[] = $values['Language'];
            }
            if (!empty($values['Title'])) {
                $audioInfoArr[] = $values['Title'];
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
                // 使用多语言支持的键名
                $result[nexus_trans('torrent.technicalinfo_audio') . $audioIndex] = implode(" ", $audioInfoArr);
                $audioIndex++;
            }
        }
        return $result;
    }

    public function getSubtitles()
    {
        $result = [];
        $subtitleIndex = 1;
        foreach ($this->mediaInfoArr as $parentKey => $values) {
            if (strpos($parentKey, 'Text') === false) {
                continue;
            }
            $subtitlesInfoArr = [];
            if (!empty($values['Language'])) {
                $subtitlesInfoArr[] = $values['Language'];
            }
            if (!empty($values['Title'])) {
                $subtitlesInfoArr[] = $values['Title'];
            }
            if (!empty($values['Format'])) {
                $subtitlesInfoArr[] = $values['Format'];
            }
            if (!empty($subtitlesInfoArr)) {
                // 使用多语言支持的键名
                $result[nexus_trans('torrent.technicalinfo_subtitles') . $subtitleIndex] = implode(" ", $subtitlesInfoArr);
                $subtitleIndex++;
            }
        }
        return $result;
    }

    public function getHDRFormat()
    {
        return $this->mediaInfoArr['Video']['HDR format'] ?? '';
    }

    public function getVideoFormat()
    {
        return $this->mediaInfoArr['Video']['Format'] ?? '';
    }

    public function getBitDepth()
    {
        return $this->mediaInfoArr['Video']['Bit depth'] ?? '';
    }

    public function renderOnDetailsPage()
    {
        global $lang_functions;
        if (empty($this->mediaInfo)) {
            return '';
        }
        $general = $this->getGeneralInfo();
        $videos  = $this->getVideoInfoDetailed();
        $audios  = $this->getAudioTracks();
        if (empty($general) && empty($videos) && empty($audios)) {
            // Parser couldn't pull anything structured — fall back to raw spoiler only.
            $rawmediaInfo = sprintf('[spoiler=%s][raw]<pre>%s</pre>[/raw][/spoiler]', nexus_trans('torrent.show_hide_media_info'), $this->mediaInfo);
            return sprintf('<div class="nexus-media-info-raw"><pre>%s</pre></div>', format_comment($rawmediaInfo, false));
        }

        $css = '<style>
.nti-wrap { margin: 4px 0; }
.nti-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.nti-col { border: 1px solid rgba(127,127,127,.30); border-radius: 6px; padding: 8px 10px; background: rgba(127,127,127,.05); min-width: 0; }
.nti-col h4 { margin: 0 0 6px; font-size: 13px; letter-spacing: .3px; text-transform: uppercase; opacity: .85; border-bottom: 1px solid rgba(127,127,127,.25); padding-bottom: 4px; }
.nti-kv { margin: 0; padding: 0; font-size: 12px; line-height: 1.45; }
.nti-kv .nti-row { display: flex; gap: 6px; padding: 1px 0; word-break: break-word; }
.nti-kv .nti-k { flex: 0 0 38%; opacity: .75; }
.nti-kv .nti-v { flex: 1 1 auto; }
.nti-track { padding: 4px 0; border-top: 1px dashed rgba(127,127,127,.25); }
.nti-track:first-child { border-top: 0; padding-top: 0; }
.nti-track .nti-track-head { font-weight: bold; font-size: 12px; margin-bottom: 2px; }
.nti-track .nti-badge { display: inline-block; font-size: 10px; padding: 0 4px; margin-left: 4px; border-radius: 3px; background: rgba(0,150,80,.20); color: inherit; vertical-align: 1px; }
.nti-more { margin-top: 6px; font-size: 11px; opacity: .9; }
.nti-raw { margin-top: 8px; }
@media (max-width: 760px) { .nti-grid { grid-template-columns: 1fr; } }
</style>';

        $html  = $css . '<div class="nti-wrap">';
        $html .= '<div class="nti-grid">';

        // General column
        if (!empty($general)) {
            $html .= '<div class="nti-col">';
            $html .= '<h4>' . htmlspecialchars(nexus_trans('torrent.technicalinfo_section_general')) . '</h4>';
            $html .= $this->renderKvList($general['main'] ?? []);
            if (!empty($general['extra'])) {
                $html .= $this->renderColumnSpoiler(nexus_trans('torrent.technicalinfo_more_general'), $general['extra']);
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="nti-col"></div>';
        }

        // Video column
        if (!empty($videos)) {
            $html .= '<div class="nti-col">';
            $html .= '<h4>' . htmlspecialchars(nexus_trans('torrent.technicalinfo_section_video')) . '</h4>';
            $html .= $this->renderKvList($videos['main'] ?? []);
            if (!empty($videos['encoding_settings'])) {
                $html .= $this->renderColumnSpoiler(nexus_trans('torrent.technicalinfo_encoding_settings'), [
                    nexus_trans('torrent.technicalinfo_encoding_settings') => $videos['encoding_settings'],
                ]);
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="nti-col"></div>';
        }

        // Audio column
        if (!empty($audios)) {
            $html .= '<div class="nti-col">';
            $html .= '<h4>' . htmlspecialchars(nexus_trans('torrent.technicalinfo_section_audio')) . '</h4>';
            $maxVisible = 3;
            $visible = array_slice($audios, 0, $maxVisible);
            $hidden = array_slice($audios, $maxVisible);
            foreach ($visible as $track) {
                $html .= $this->renderAudioTrack($track);
            }
            if (!empty($hidden)) {
                $hiddenHtml = '';
                foreach ($hidden as $track) {
                    $hiddenHtml .= $this->renderAudioTrack($track);
                }
                $title = nexus_trans('torrent.collapse_show_more_audio');
                $html .= sprintf(
                    '<div class="nti-more">%s</div>',
                    format_comment(sprintf('[spoiler=%s][raw]%s[/raw][/spoiler]', $title, $hiddenHtml), false)
                );
            }
            $html .= '</div>';
        } else {
            $html .= '<div class="nti-col"></div>';
        }

        $html .= '</div>'; // .nti-grid

        // Raw spoiler at the bottom
        $rawMediaInfo = sprintf('[spoiler=%s][raw]<pre>%s</pre>[/raw][/spoiler]', nexus_trans('torrent.show_hide_media_info'), $this->mediaInfo);
        $html .= sprintf('<div class="nti-raw nexus-media-info-raw">%s</div>', format_comment($rawMediaInfo, false));

        $html .= '</div>'; // .nti-wrap
        return $html;
    }

    /**
     * Returns ['main' => [label => value], 'extra' => [label => value]]
     * The "main" set is the small column display; "extra" goes into a per-column spoiler.
     */
    public function getGeneralInfo(): array
    {
        $g = $this->mediaInfoArr['General'] ?? [];
        if (empty($g)) {
            return [];
        }
        $main = [
            nexus_trans('torrent.technicalinfo_container')        => $g['Format'] ?? '',
            nexus_trans('torrent.technicalinfo_file_size')        => $g['File size'] ?? '',
            nexus_trans('torrent.technicalinfo_overall_bit_rate') => $g['Overall bit rate'] ?? ($g['Overall bit rate mode'] ?? ''),
            nexus_trans('torrent.technicalinfo_duration')         => $g['Duration'] ?? '',
            nexus_trans('torrent.technicalinfo_encoded_date')     => $g['Encoded date'] ?? '',
            nexus_trans('torrent.technicalinfo_writing_app')      => $g['Writing application'] ?? ($g['Encoded application'] ?? ''),
            nexus_trans('torrent.technicalinfo_writing_lib')      => $g['Writing library'] ?? '',
        ];
        $main = array_filter($main, fn($v) => $v !== '' && $v !== null);

        // Anything else from [General] goes to the "extra" spoiler so we don't drop info.
        $shownKeys = ['Format', 'File size', 'Overall bit rate', 'Overall bit rate mode',
            'Duration', 'Encoded date', 'Writing application', 'Encoded application',
            'Writing library', 'Complete name', 'Unique ID', 'File name'];
        $extra = [];
        foreach ($g as $k => $v) {
            if (in_array($k, $shownKeys, true)) {
                continue;
            }
            if ($v === '' || $v === null) {
                continue;
            }
            $extra[$k] = $v;
        }
        return ['main' => $main, 'extra' => $extra];
    }

    /**
     * Returns ['main' => [...], 'encoding_settings' => string|null]
     */
    public function getVideoInfoDetailed(): array
    {
        $v = $this->mediaInfoArr['Video'] ?? [];
        if (empty($v)) {
            return [];
        }
        $format = $v['Format'] ?? '';
        if (!empty($v['Format profile']) && $format !== '') {
            $format .= ' / ' . $v['Format profile'];
        } elseif (empty($format) && !empty($v['Format profile'])) {
            $format = $v['Format profile'];
        }
        $color = $this->joinNonEmpty([
            $v['Color primaries'] ?? '',
            $v['Transfer characteristics'] ?? '',
        ], ' / ');
        $main = [
            nexus_trans('torrent.technicalinfo_format')      => $format,
            nexus_trans('torrent.technicalinfo_resolution')  => $this->getResolution(),
            nexus_trans('torrent.technicalinfo_bit_rate')    => $v['Bit rate'] ?? ($v['Nominal bit rate'] ?? ''),
            nexus_trans('torrent.technicalinfo_frame_rate')  => $v['Frame rate'] ?? '',
            nexus_trans('torrent.technicalinfo_bit_depth')   => $v['Bit depth'] ?? '',
            nexus_trans('torrent.technicalinfo_color_space') => $color,
            'HDR'                                            => $v['HDR format'] ?? '',
            nexus_trans('torrent.technicalinfo_scan_type')   => $v['Scan type'] ?? '',
            nexus_trans('torrent.technicalinfo_ref_frames')  => $this->getRefFrame(),
            nexus_trans('torrent.technicalinfo_encoder')     => $v['Encoded library'] ?? ($v['Writing library'] ?? ''),
        ];
        $main = array_filter($main, fn($v) => $v !== '' && $v !== null);
        return [
            'main' => $main,
            'encoding_settings' => isset($v['Encoding settings']) && $v['Encoding settings'] !== ''
                ? $v['Encoding settings']
                : null,
        ];
    }

    /**
     * Returns a list of audio tracks: each item is [
     *   'index' => int,
     *   'language' => string,
     *   'title' => string,
     *   'rows' => [label => value],
     *   'badges' => ['Default' => bool, 'Forced' => bool],
     * ]
     */
    public function getAudioTracks(): array
    {
        $tracks = [];
        $idx = 1;
        foreach ($this->mediaInfoArr as $section => $values) {
            if (strpos($section, 'Audio') === false) {
                continue;
            }
            $format = $values['Format'] ?? '';
            $commercial = $values['Commercial name'] ?? '';
            if ($commercial !== '' && $commercial !== $format) {
                $format = $format !== '' ? $format . ' (' . $commercial . ')' : $commercial;
            }
            $channels = $values['Channel(s)'] ?? '';
            $layout = $values['Channel layout'] ?? '';
            if ($layout !== '' && $channels !== '') {
                $channels .= ' (' . $layout . ')';
            } elseif ($layout !== '' && $channels === '') {
                $channels = $layout;
            }
            $bitrate = $values['Bit rate'] ?? '';
            $bitrateMode = $values['Bit rate mode'] ?? '';
            if ($bitrate !== '' && $bitrateMode !== '') {
                $bitrate .= ' (' . $bitrateMode . ')';
            }
            $rows = [
                nexus_trans('torrent.technicalinfo_format')      => $format,
                nexus_trans('torrent.technicalinfo_channels')    => $channels,
                nexus_trans('torrent.technicalinfo_sample_rate') => $values['Sampling rate'] ?? '',
                nexus_trans('torrent.technicalinfo_bit_rate')    => $bitrate,
                nexus_trans('torrent.technicalinfo_bit_depth')   => $values['Bit depth'] ?? '',
                nexus_trans('torrent.technicalinfo_compression') => $values['Compression mode'] ?? '',
            ];
            $rows = array_filter($rows, fn($v) => $v !== '' && $v !== null);
            if (empty($rows)) {
                continue;
            }
            $tracks[] = [
                'index'    => $idx,
                'language' => $values['Language'] ?? '',
                'title'    => $values['Title'] ?? '',
                'rows'     => $rows,
                'badges'   => [
                    'Default' => isset($values['Default']) && strcasecmp($values['Default'], 'yes') === 0,
                    'Forced'  => isset($values['Forced']) && strcasecmp($values['Forced'], 'yes') === 0,
                ],
            ];
            $idx++;
        }
        return $tracks;
    }

    private function joinNonEmpty(array $parts, string $glue = ' / '): string
    {
        $parts = array_filter(array_map([$this, 'trim'], $parts), fn($v) => $v !== '');
        return implode($glue, $parts);
    }

    private function renderKvList(array $items): string
    {
        if (empty($items)) {
            return '';
        }
        $html = '<div class="nti-kv">';
        foreach ($items as $k => $v) {
            $html .= '<div class="nti-row"><span class="nti-k">' . htmlspecialchars((string)$k) . '</span><span class="nti-v">' . htmlspecialchars((string)$v) . '</span></div>';
        }
        $html .= '</div>';
        return $html;
    }

    private function renderAudioTrack(array $track): string
    {
        $head = '#' . (int)$track['index'];
        $headParts = [];
        if (!empty($track['language'])) {
            $headParts[] = $track['language'];
        }
        if (!empty($track['title']) && $track['title'] !== ($track['language'] ?? '')) {
            $headParts[] = $track['title'];
        }
        if (!empty($headParts)) {
            $head .= ' · ' . implode(' · ', $headParts);
        }
        $badges = '';
        if (!empty($track['badges']['Default'])) {
            $badges .= '<span class="nti-badge">' . htmlspecialchars(nexus_trans('torrent.technicalinfo_default')) . '</span>';
        }
        if (!empty($track['badges']['Forced'])) {
            $badges .= '<span class="nti-badge">' . htmlspecialchars(nexus_trans('torrent.technicalinfo_forced')) . '</span>';
        }
        $html  = '<div class="nti-track">';
        $html .= '<div class="nti-track-head">' . htmlspecialchars($head) . $badges . '</div>';
        $html .= $this->renderKvList($track['rows']);
        $html .= '</div>';
        return $html;
    }

    private function renderColumnSpoiler(string $title, array $items): string
    {
        if (empty($items)) {
            return '';
        }
        $body = '';
        foreach ($items as $k => $v) {
            $body .= '<b>' . htmlspecialchars((string)$k) . ': </b>' . htmlspecialchars((string)$v) . '<br>';
        }
        $bbcode = sprintf('[spoiler=%s][raw]%s[/raw][/spoiler]', $title, $body);
        return sprintf('<div class="nti-more">%s</div>', format_comment($bbcode, false));
    }

    public function getSummaryInfo(): array
    {
        $videos = [
            nexus_trans('torrent.technicalinfo_duration') => $this->getRuntime(),
            nexus_trans('torrent.technicalinfo_resolution') => $this->getResolution(),
            nexus_trans('torrent.technicalinfo_bit_rate') => $this->getBitrate(),
            'HDR' => $this->getHDRFormat(),
            nexus_trans('torrent.technicalinfo_bit_depth') => $this->getBitDepth(),
            nexus_trans('torrent.technicalinfo_frame_rate') => $this->getFramerate(),
            nexus_trans('torrent.technicalinfo_profile') => $this->getProfile(),
            nexus_trans('torrent.technicalinfo_format') => $this->getVideoFormat(),
            nexus_trans('torrent.technicalinfo_ref_frames') => $this->getRefFrame(),
        ];
        $videos = array_filter($videos) ?: null;
        $audios = $this->getAudios() ?: null;
        $subtitles = $this->getSubtitles() ?: null;
        return compact('videos', 'audios', 'subtitles');
    }

    private function buildTdTable(array $parts)
    {
        $table = '<table style="border: none;"><tbody>';
        
        // 检查是否为音频或字幕数据
        $isAudioOrSubtitle = false;
        $audioOrSubtitleCount = 0;
        $audioPrefix = nexus_trans('torrent.technicalinfo_audio');
        $subtitlePrefix = nexus_trans('torrent.technicalinfo_subtitles');
        foreach ($parts as $key => $value) {
            if (strpos($key, $audioPrefix) === 0 || strpos($key, $subtitlePrefix) === 0) {
                $isAudioOrSubtitle = true;
                $audioOrSubtitleCount++;
            }
        }
        
        $displayCount = 0;
        $hiddenParts = [];
        
        foreach ($parts as $key => $value) {
            $displayCount++;
            
            // 如果是音频或字幕，且超过3条，则隐藏多余的
            if ($isAudioOrSubtitle && $audioOrSubtitleCount > 3) {
                if ($displayCount <= 3) {
                    // 显示前3条
                    $table .= '<tr>';
                    $table .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px;"><b>%s: </b>%s</td>', $key, $value);
                    $table .= '</tr>';
                } else {
                    // 收集隐藏的部分
                    $hiddenParts[$key] = $value;
                }
            } else {
                // 非音频/字幕数据，或数量不超过3条，正常显示
                $table .= '<tr>';
                $table .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px;"><b>%s: </b>%s</td>', $key, $value);
                $table .= '</tr>';
            }
        }
        
        // 如果有隐藏的部分，添加spoiler
        if (!empty($hiddenParts)) {
            $hiddenContent = '';
            foreach ($hiddenParts as $key => $value) {
                $hiddenContent .= sprintf('<b>%s: </b>%s<br>', $key, $value);
            }
            $hiddenContent = rtrim($hiddenContent, '<br>');
            
            $spoilerTitle = $isAudioOrSubtitle && strpos(array_keys($parts)[0], $audioPrefix) === 0 
                ? nexus_trans('torrent.collapse_show_more_audio') 
                : nexus_trans('torrent.collapse_show_more_subtitles');
            
            $spoiler = sprintf('[spoiler=%s]%s[/spoiler]', $spoilerTitle, $hiddenContent);
            $table .= '<tr>';
            // 检查format_comment函数是否存在
            if (function_exists('format_comment')) {
                $table .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px;">%s</td>', format_comment($spoiler, false));
            } else {
                $table .= sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px;">%s</td>', $spoiler);
            }
            $table .= '</tr>';
        }
        
        $table .= '</tbody>';
        $table .= '</table>';
        return sprintf('<td style="border: none; padding-right: 5px;padding-bottom: 5px">%s</td>', $table);
    }

}
