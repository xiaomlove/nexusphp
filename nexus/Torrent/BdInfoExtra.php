<?php

namespace Nexus\Torrent;

class BdInfoExtra
{
    private $bdInfo;

    private $bdInfoArr;

    public function __construct(string $bdInfo)
    {
        $this->bdInfo = $bdInfo;
        $this->bdInfoArr = $this->parseBdInfo($bdInfo);
    }

    /**
     * 解析BDINFO文本为结构化数组
     */
    private function parseBdInfo(string $bdInfo): array
    {
        $lines = preg_split('/[\r\n]+/', $bdInfo);

        // 检测是否为Summary格式（无章节标题的格式）
        $isSummaryFormat = $this->isSummaryFormat($lines);

        if ($isSummaryFormat) {
            $result = [
                'disc_info' => [],
                'playlist_report' => [],
                'video' => [],
                'audio' => [],
                'subtitles' => [],
            ];

            return $this->summaryFormat($lines, $result);
        } else {
            return $this->normalFormat($lines);
        }
    }

    /**
     * 检测是否为Summary格式
     */
    private function isSummaryFormat(array $lines): bool
    {
        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (strpos($line, 'DISC INFO') !== false ||
                strpos($line, 'PLAYLIST REPORT') !== false ||
                strpos($line, 'VIDEO') !== false ||
                strpos($line, 'AUDIO') !== false ||
                strpos($line, 'SUBTITLES') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * 解析格式1（有章节标题的格式）
     */
    private function normalFormat(array $lines): array
    {
        $discs = [];
        $currentDisc = null;
        $currentSection = '';
        $audioIndex = 0;
        $subtitleIndex = 0;

        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (empty($line)) {
                continue;
            }

            // 检测新的DISC
            if (strpos($line, 'DISC INFO') !== false) {
                // 保存之前的DISC（如果存在）
                if ($currentDisc !== null) {
                    $discs[] = $currentDisc;
                }

                // 创建新的DISC
                $currentDisc = [
                    'disc_info' => [],
                    'playlist_report' => [],
                    'video' => [],
                    'audio' => [],
                    'subtitles' => [],
                ];
                $currentSection = 'disc_info';
                $audioIndex = 0;
                $subtitleIndex = 0;

                continue;
            } elseif (strpos($line, 'PLAYLIST REPORT') !== false) {
                $currentSection = 'playlist_report';

                continue;
            } elseif (strpos($line, 'VIDEO') !== false) {
                $currentSection = 'video';

                continue;
            } elseif (strpos($line, 'AUDIO') !== false) {
                $currentSection = 'audio';

                continue;
            } elseif (strpos($line, 'SUBTITLES') !== false) {
                $currentSection = 'subtitles';

                continue;
            } elseif (strpos($line, 'CHAPTERS') !== false || strpos($line, 'STREAM DIAGNOSTICS') !== false) {
                $currentSection = '';

                continue;
            }

            // 解析各个章节的内容
            if ($currentDisc !== null && ! empty($currentSection)) {
                switch ($currentSection) {
                    case 'disc_info':
                        $this->parseDiscInfo($line, $currentDisc['disc_info']);
                        break;
                    case 'playlist_report':
                        $this->parsePlaylistReport($line, $currentDisc['playlist_report']);
                        break;
                    case 'video':
                        $this->parseVideo($line, $currentDisc['video']);
                        break;
                    case 'audio':
                        $this->parseAudio($line, $currentDisc['audio'], $audioIndex);
                        break;
                    case 'subtitles':
                        $this->parseSubtitles($line, $currentDisc['subtitles'], $subtitleIndex);
                        break;
                }
            }
        }

        // 保存最后一个DISC
        if ($currentDisc !== null) {
            $discs[] = $currentDisc;
        }

        // 如果没有找到任何DISC，返回空结构
        if (empty($discs)) {
            return [
                'disc_info' => [],
                'playlist_report' => [],
                'video' => [],
                'audio' => [],
                'subtitles' => [],
            ];
        }

        // 返回第一个DISC的数据（保持向后兼容）
        return $discs[0];
    }

    /**
     * 解析Summary格式（无章节标题的格式）
     */
    private function summaryFormat(array $lines, array $result): array
    {
        $audioIndex = 0;
        $subtitleIndex = 0;

        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (empty($line)) {
                continue;
            }

            // 解析光盘信息
            if (strpos($line, 'Disc Label:') !== false) {
                $result['disc_info']['label'] = trim(substr($line, 11));
            } elseif (strpos($line, 'Disc Size:') !== false) {
                $result['disc_info']['size'] = trim(substr($line, 10));
            } elseif (strpos($line, 'Protection:') !== false) {
                $result['disc_info']['protection'] = trim(substr($line, 11));
            } elseif (strpos($line, 'Playlist:') !== false) {
                $result['playlist_report']['name'] = trim(substr($line, 9));
            } elseif (strpos($line, 'Size:') !== false) {
                $result['playlist_report']['size'] = trim(substr($line, 5));
            } elseif (strpos($line, 'Length:') !== false) {
                $result['playlist_report']['length'] = trim(substr($line, 7));
            } elseif (strpos($line, 'Total Bitrate:') !== false) {
                $result['playlist_report']['total_bitrate'] = trim(substr($line, 14));
            } elseif (strpos($line, 'Video:') !== false) {
                $this->summaryFormatVideo($line, $result['video']);
            } elseif (strpos($line, 'Audio:') !== false) {
                $this->summaryFormatAudio($line, $result['audio'], $audioIndex);
            } elseif (strpos($line, 'Subtitle:') !== false) {
                $this->summaryFormatSubtitle($line, $result['subtitles'], $subtitleIndex);
            }
        }

        return $result;
    }

    /**
     * 解析Summary格式的视频信息
     */
    private function summaryFormatVideo(string $line, array &$video): void
    {
        // 格式：Video: MPEG-4 AVC Video / 31943 kbps / 1080p / 23.976 fps / 16:9 / High Profile 4.1
        if (preg_match('/Video:\s*(.+?)\s*\/\s*(\d+)\s*kbps\s*\/(.+)/', $line, $matches)) {
            $video['codec'] = trim($matches[1]);
            $video['bitrate'] = trim($matches[2]).' kbps';
            $video['description'] = trim($matches[3]);
        }
    }

    /**
     * 解析Summary格式的音频信息
     */
    private function summaryFormatAudio(string $line, array &$audio, int &$audioIndex): void
    {
        // 格式：Audio: Chinese / DTS-HD Master Audio / 2.0 / 48 kHz /   914 kbps / 16-bit (DTS Core: 2.0 / 48 kHz /   768 kbps / 16-bit)
        if (preg_match('/Audio:\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)\s*\/\s*([^*]+?)(?:\s*\((.+)\))?/', $line, $matches)) {
            $bitrate = trim($matches[5]);
            // 如果码率已经包含kbps，就不重复添加
            if (strpos($bitrate, 'kbps') === false) {
                $bitrate .= ' kbps';
            }

            $audio[$audioIndex] = [
                'language' => trim($matches[1]),
                'codec' => trim($matches[2]),
                'channels' => trim($matches[3]),
                'sample_rate' => trim($matches[4]),
                'bitrate' => $bitrate,
                'bit_depth' => trim($matches[6]),
                'description' => isset($matches[7]) ? trim($matches[7]) : '',
            ];
            $audioIndex++;
        }
    }

    /**
     * 解析Summary格式的字幕信息
     */
    private function summaryFormatSubtitle(string $line, array &$subtitles, int &$subtitleIndex): void
    {
        // 格式：Subtitle: English / 38.300 kbps
        if (preg_match('/Subtitle:\s*([^*]+?)\s*\/\s*([^*]+?)\s*kbps/', $line, $matches)) {
            $subtitles[$subtitleIndex] = [
                'language' => trim($matches[1]),
                'bitrate' => trim($matches[2]).' kbps',
                'codec' => 'Presentation Graphics',
                'description' => '',
            ];
            $subtitleIndex++;
        }
    }

    /**
     * 解析光盘信息
     */
    private function parseDiscInfo(string $line, array &$discInfo): void
    {
        if (strpos($line, 'Disc Title:') !== false) {
            $discInfo['title'] = trim(substr($line, 11));
        } elseif (strpos($line, 'Disc Label:') !== false) {
            $discInfo['label'] = trim(substr($line, 11));
        } elseif (strpos($line, 'Disc Size:') !== false) {
            $discInfo['size'] = trim(substr($line, 10));
        } elseif (strpos($line, 'Protection:') !== false) {
            $discInfo['protection'] = trim(substr($line, 11));
        } elseif (strpos($line, 'Extras:') !== false) {
            $discInfo['extras'] = trim(substr($line, 7));
        }
    }

    /**
     * 解析播放列表报告
     */
    private function parsePlaylistReport(string $line, array &$playlistReport): void
    {
        if (strpos($line, 'Name:') !== false) {
            $playlistReport['name'] = trim(substr($line, 5));
        } elseif (strpos($line, 'Length:') !== false) {
            $playlistReport['length'] = trim(substr($line, 7));
        } elseif (strpos($line, 'Size:') !== false) {
            $playlistReport['size'] = trim(substr($line, 5));
        } elseif (strpos($line, 'Total Bitrate:') !== false) {
            $playlistReport['total_bitrate'] = trim(substr($line, 14));
        }
    }

    /**
     * 解析视频信息
     */
    private function parseVideo(string $line, array &$video): void
    {
        // 跳过表头和分隔线
        if (strpos($line, 'Codec') !== false || strpos($line, '-----') !== false || strpos($line, 'Description') !== false) {
            return;
        }

        // 解析视频行 - 包括隐藏视频流（带*号的）
        if (preg_match('/^(\*?\s*)(.+?)\s+([\d,]+)\s+kbps\s+(.+)$/', $line, $matches)) {
            $isHidden = strpos($matches[1], '*') !== false;

            if (! $isHidden) {
                // 主视频流 - 支持多个视频流
                // 添加到数组末尾
                $video[] = [
                    'codec' => trim($matches[2]),
                    'bitrate' => trim($matches[3]).' kbps',
                    'description' => trim($matches[4]),
                ];
                $videoIndex = count($video) - 1;

                // 提取分辨率信息（对每个视频流都提取）
                $description = trim($matches[4]);
                if (preg_match('/(\d+)p/', $description, $resMatches)) {
                    $video['height'] = $resMatches[1];
                }
                // 只有当描述中包含aspect_ratio时才提取
                if (preg_match('/(\d+:\d+)/', $description, $ratioMatches)) {
                    $video['aspect_ratio'] = $ratioMatches[1];
                }
            } else {
                // 隐藏视频流 - 也作为独立的视频流处理，但标记为隐藏
                $video[] = [
                    'codec' => trim($matches[2]),
                    'bitrate' => trim($matches[3]).' kbps',
                    'description' => trim($matches[4]),
                    'hidden' => true,
                ];
            }
        }
    }

    /**
     * 提取字幕和音轨描述中的非英文内容
     */
    private function extractNonEnglishContent(string $text): array
    {
        $result = ['text' => $text, 'non_english_content' => []];

        // 提取所有非英文字符的内容
        if (preg_match_all('/[^\x{0000}-\x{007F}]+/u', $text, $matches)) {
            foreach ($matches[0] as $match) {
                // 去除制表符和括号
                $match = preg_replace('/[\s\t\n\r（）()【】\[\]]+/u', '', $match);
                $match = trim($match);
                if (! empty($match)) {
                    $result['non_english_content'][] = $match;
                }
            }

            // 从原文本中移除非英文字符内容
            $result['text'] = preg_replace('/[^\x{0000}-\x{007F}]+/u', '', $text);
        }

        $result['text'] = trim($result['text']);

        return $result;
    }

    /**
     * 解析音频信息
     */
    private function parseAudio(string $line, array &$audio, int &$audioIndex): void
    {
        // 跳过表头和分隔线
        if (strpos($line, 'Codec') !== false || strpos($line, '-----') !== false || strpos($line, 'Language') !== false) {
            return;
        }

        // 解析音频行 - 格式：DTS-HD Master Audio             English         1564 kbps       2.0 / 48 kHz / 1564 kbps / 24-bit
        // 也包含隐藏音频流（带*号的）
        if (preg_match('/^(\*?\s*)(.+?)\s+([A-Za-z]+)\s+([\d,]+)\s+kbps\s+(.+)$/', $line, $matches)) {
            $description = trim($matches[5]);

            // 提取括号内容
            $extracted = $this->extractNonEnglishContent($description);
            $nonEnglishContent = $extracted['non_english_content'];
            $cleanDescription = $extracted['text'];

            $audio[$audioIndex] = [
                'codec' => trim($matches[2]),
                'language' => trim($matches[3]),
                'bitrate' => trim($matches[4]).' kbps',
                'description' => $cleanDescription,
                'non_english_content' => $nonEnglishContent,
            ];
            $audioIndex++;
        }
    }

    /**
     * 解析字幕信息
     */
    private function parseSubtitles(string $line, array &$subtitles, int &$subtitleIndex): void
    {
        // 跳过表头和分隔线
        if (strpos($line, 'Codec') !== false || strpos($line, '-----') !== false || strpos($line, 'Language') !== false) {
            return;
        }

        // 跳过FILES章节的内容
        if (strpos($line, 'Name') !== false || strpos($line, 'Time In') !== false || strpos($line, 'Length') !== false || strpos($line, 'Size') !== false || strpos($line, 'Total Bitrate') !== false) {
            return;
        }

        // 跳过文件行（如：00003.M2TS      0:00:00.000     2:00:29.416）
        if (preg_match('/^\w+\.M2TS\s+/', $line)) {
            return;
        }

        // 解析字幕行 - 格式：Presentation Graphics           English         21.061 kbps
        // 优先匹配"Presentation Graphics"开头的行
        if (preg_match('/^(Presentation Graphics)\s+([^*]+?)\s+([^*]+?)\s+kbps\s*(.*)$/', $line, $matches)) {
            $codec = trim($matches[1]);
            $language = trim($matches[2]);
            $bitrate = trim($matches[3]).' kbps';
            $description = trim($matches[4]);

            // 只有当语言不为空时才添加
            if (! empty($language)) {
                // 提取括号内容
                $extracted = $this->extractNonEnglishContent($description);
                $nonEnglishContent = $extracted['non_english_content'];
                $cleanDescription = $extracted['text'];

                $subtitles[$subtitleIndex] = [
                    'codec' => $codec,
                    'language' => $language,
                    'bitrate' => $bitrate,
                    'description' => $cleanDescription,
                    'non_english_content' => $nonEnglishContent,
                ];
                $subtitleIndex++;
            }
        }
    }

    /**
     * 获取时长
     */
    public function getDuration(): string
    {
        $length = $this->bdInfoArr['playlist_report']['length'] ?? '';
        if (empty($length)) {
            return '';
        }

        // 转换格式：1:55:22.123 -> 1h 55m 22s 123ms
        if (preg_match('/(\d+):(\d+):(\d+)\.(\d+)/', $length, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = intval($matches[3]);
            $milliseconds = intval($matches[4]);

            return sprintf('%dh %02dm %02ds %03dms', $hours, $minutes, $seconds, $milliseconds);
        }

        return $length;
    }

    /**
     * 获取总码率
     */
    public function getTotalBitrate(): string
    {
        return $this->bdInfoArr['playlist_report']['total_bitrate'] ?? '';
    }

    /**
     * 获取帧率
     */
    public function getFrameRate(): string
    {
        $description = $this->bdInfoArr['video']['description'] ?? '';
        if (preg_match('/(\d+\.?\d*)\s+fps/', $description, $matches)) {
            return $matches[1].' fps';
        }

        return '';
    }

    /**
     * 获取视频配置文件
     */
    public function getProfile(): string
    {
        $profiles = [];

        // 检查所有视频流，跳过隐藏视频流
        foreach ($this->bdInfoArr['video'] as $key => $video) {
            if (is_array($video) && isset($video['description']) && ! isset($video['hidden'])) {
                $description = $video['description'];
                if (preg_match('/([^\/]*?(?:profile|high|level|main)[^\/]*?)(?:\s*\/|$)/i', $description, $matches)) {
                    $profiles[] = trim($matches[1]);
                }
            }
        }

        // 如果没有找到profile，检查是否是summaryFormat格式（关联数组）
        if (empty($profiles) && isset($this->bdInfoArr['video']['description'])) {
            $description = $this->bdInfoArr['video']['description'];
            if (preg_match('/([^\/]*?(?:profile|high|level|main)[^\/]*?)(?:\s*\/|$)/i', $description, $matches)) {
                $profiles[] = trim($matches[1]);
            }
        }

        return implode(' / ', $profiles);
    }

    public function getResolution(): string
    {
        $resolutions = [];

        // 遍历所有视频流提取分辨率和宽高比
        foreach ($this->bdInfoArr['video'] as $index => $video) {
            // 处理数字索引的数组（多视频流格式）或关联数组（单视频流格式）
            if (is_array($video) && isset($video['description'])) {
                $description = $video['description'];
                $resolutionItem = '';

                // 提取"xxxp"格式的分辨率
                if (preg_match('/(\d+p)/', $description, $matches)) {
                    $resolutionItem = $matches[1];
                }

                // 提取宽高比信息
                if (preg_match('/(\d+:\d+)/', $description, $ratioMatches)) {
                    $resolutionItem .= '('.$ratioMatches[1].')';
                }

                if (! empty($resolutionItem)) {
                    $resolutions[] = $resolutionItem;
                }
            }
        }

        // 如果没有找到分辨率，检查是否是summaryFormat格式（关联数组）
        if (empty($resolutions) && isset($this->bdInfoArr['video']['description'])) {
            $description = $this->bdInfoArr['video']['description'];
            $resolutionItem = '';

            // 提取"xxxp"格式的分辨率
            if (preg_match('/(\d+p)/', $description, $matches)) {
                $resolutionItem = $matches[1];
            }

            // 提取宽高比信息
            if (preg_match('/(\d+:\d+)/', $description, $ratioMatches)) {
                $resolutionItem .= '('.$ratioMatches[1].')';
            }

            if (! empty($resolutionItem)) {
                $resolutions[] = $resolutionItem;
            }
        }

        return implode(' / ', $resolutions);
    }

    public function getBitDepth(): string
    {
        // 从第一个视频流获取位深度信息
        $firstVideo = $this->bdInfoArr['video'][0] ?? null;
        if ($firstVideo && isset($firstVideo['description'])) {
            $description = $firstVideo['description'];
            if (preg_match('/(\d+)\s+bits/', $description, $matches)) {
                return $matches[1].' bits';
            }
        }

        // 如果没有找到位深度，检查是否是summaryFormat格式（关联数组）
        if (isset($this->bdInfoArr['video']['description'])) {
            $description = $this->bdInfoArr['video']['description'];
            if (preg_match('/(\d+)\s+bits/', $description, $matches)) {
                return $matches[1].' bits';
            }
        }

        return '';
    }

    public function getVideoFormat(): string
    {
        $formats = [];

        // 检查所有视频流
        foreach ($this->bdInfoArr['video'] as $key => $video) {
            if (is_array($video) && isset($video['codec'])) {
                $formats[] = $video['codec'];
            }
        }

        // 如果没有找到格式，检查是否是summaryFormat格式（关联数组）
        if (empty($formats) && isset($this->bdInfoArr['video']['codec'])) {
            $formats[] = $this->bdInfoArr['video']['codec'];
        }

        return implode(' / ', $formats);
    }

    /**
     * 获取宽高比
     */
    public function getAspectRatio(): string
    {
        return $this->bdInfoArr['video']['aspect_ratio'] ?? '';
    }

    /**
     * 获取Extras信息
     */
    public function getExtras(): string
    {
        return $this->bdInfoArr['disc_info']['extras'] ?? '';
    }

    /**
     * 获取HDR格式
     */
    public function getHDRFormat(): string
    {
        // 从所有视频流获取HDR信息
        $hdrTypes = [];
        $bitDepths = [];
        $nits = [];

        foreach ($this->bdInfoArr['video'] as $video) {
            $description = $video['description'] ?? '';

            // 从VIDEO描述中提取HDR格式
            if (preg_match('/\b(HDR10\+|HDR10|HDR|HLG|Dolby Vision)(?:\s|\/|$)/i', $description, $matches)) {
                $hdrTypes[] = $matches[1];
            }

            // 检查比特深度
            if (preg_match('/(\d+)\s+bits/', $description, $matches)) {
                $bitDepths[] = $matches[1].' bits';
            }

            // 检查亮度
            if (preg_match('/(\d+)nits/', $description, $matches)) {
                $nits[] = $matches[1].'nits';
            }
        }

        // 去重并构建结果
        $result = [];

        // HDR格式
        $hdrTypes = array_unique($hdrTypes);
        if (! empty($hdrTypes)) {
            $result[] = implode(' / ', $hdrTypes);
        }

        // 比特深度
        $bitDepths = array_unique($bitDepths);
        if (! empty($bitDepths)) {
            $result[] = implode(' / ', $bitDepths);
        }

        // 亮度
        $nits = array_unique($nits);
        if (! empty($nits)) {
            $result[] = implode(' / ', $nits);
        }

        return implode(' / ', $result);
    }

    /**
     * 获取音频信息
     */
    public function getAudios(): array
    {
        $result = [];
        $audioIndex = 1;
        foreach ($this->bdInfoArr['audio'] as $audio) {
            $audioInfo = [];

            // 语言
            if (! empty($audio['language'])) {
                $audioInfo[] = $audio['language'];
            }

            // 编解码器
            if (! empty($audio['codec'])) {
                $audioInfo[] = $audio['codec'];
            }

            // 声道信息
            if (! empty($audio['channels'])) {
                $audioInfo[] = $audio['channels'];
            } elseif (! empty($audio['description'])) {
                // 从描述中提取声道信息
                if (preg_match('/(\d+\.\d+)/', $audio['description'], $matches)) {
                    $audioInfo[] = $matches[1];
                }
            }

            // 码率
            if (! empty($audio['bitrate'])) {
                $audioInfo[] = $audio['bitrate'];
            }

            // 括号内容（添加到最后面）
            if (! empty($audio['non_english_content'])) {
                foreach ($audio['non_english_content'] as $nonEnglishItem) {
                    $audioInfo[] = $nonEnglishItem;
                }
            }

            if (! empty($audioInfo)) {
                $result[nexus_trans('torrent.technicalinfo_audio').$audioIndex] = implode(' / ', $audioInfo);
                $audioIndex++;
            }
        }

        return $result;
    }

    /**
     * 获取字幕信息
     */
    public function getSubtitles(): array
    {
        $result = [];
        $subtitleIndex = 1;
        foreach ($this->bdInfoArr['subtitles'] as $subtitle) {
            if (! empty($subtitle['language'])) {
                $subtitleInfo = [$subtitle['language']];

                // 括号内容（添加到最后面）
                if (! empty($subtitle['non_english_content'])) {
                    foreach ($subtitle['non_english_content'] as $nonEnglishItem) {
                        $subtitleInfo[] = $nonEnglishItem;
                    }
                }

                $result[nexus_trans('torrent.technicalinfo_subtitles').$subtitleIndex] = implode(' / ', $subtitleInfo);
                $subtitleIndex++;
            }
        }

        return $result;
    }

    /**
     * 获取所有DISC的数据
     */
    private function getAllDiscs(): array
    {
        $lines = preg_split('/[\r\n]+/', $this->bdInfo);
        $discs = [];
        $currentDisc = null;
        $currentSection = '';
        $audioIndex = 0;
        $subtitleIndex = 0;

        foreach ($lines as $line) {
            $line = $this->trim($line);
            if (empty($line)) {
                continue;
            }

            // 检测新的DISC
            if (strpos($line, 'DISC INFO') !== false) {
                // 保存之前的DISC（如果存在）
                if ($currentDisc !== null) {
                    $discs[] = $currentDisc;
                }

                // 创建新的DISC
                $currentDisc = [
                    'disc_info' => [],
                    'playlist_report' => [],
                    'video' => [],
                    'audio' => [],
                    'subtitles' => [],
                ];
                $currentSection = 'disc_info';
                $audioIndex = 0;
                $subtitleIndex = 0;

                continue;
            } elseif (strpos($line, 'PLAYLIST REPORT') !== false) {
                $currentSection = 'playlist_report';

                continue;
            } elseif (strpos($line, 'VIDEO') !== false) {
                $currentSection = 'video';

                continue;
            } elseif (strpos($line, 'AUDIO') !== false) {
                $currentSection = 'audio';

                continue;
            } elseif (strpos($line, 'SUBTITLES') !== false) {
                $currentSection = 'subtitles';

                continue;
            } elseif (strpos($line, 'CHAPTERS') !== false || strpos($line, 'STREAM DIAGNOSTICS') !== false) {
                $currentSection = '';

                continue;
            }

            // 解析各个章节的内容
            if ($currentDisc !== null && ! empty($currentSection)) {
                switch ($currentSection) {
                    case 'disc_info':
                        $this->parseDiscInfo($line, $currentDisc['disc_info']);
                        break;
                    case 'playlist_report':
                        $this->parsePlaylistReport($line, $currentDisc['playlist_report']);
                        break;
                    case 'video':
                        $this->parseVideo($line, $currentDisc['video']);
                        break;
                    case 'audio':
                        $this->parseAudio($line, $currentDisc['audio'], $audioIndex);
                        break;
                    case 'subtitles':
                        $this->parseSubtitles($line, $currentDisc['subtitles'], $subtitleIndex);
                        break;
                }
            }
        }

        // 保存最后一个DISC
        if ($currentDisc !== null) {
            $discs[] = $currentDisc;
        }

        // 如果没有找到任何DISC（normalFormat格式），检查是否是summaryFormat格式
        if (empty($discs)) {
            // 检查bdInfoArr中是否有有效的媒体数据
            if ((isset($this->bdInfoArr['video']) && ! empty($this->bdInfoArr['video'])) ||
                (isset($this->bdInfoArr['audio']) && ! empty($this->bdInfoArr['audio']))) {
                // 将bdInfoArr作为单个DISC返回
                $discs[] = $this->bdInfoArr;
            }
        }

        return $discs;
    }

    /**
     * 获取汇总信息
     */
    public function getSummaryInfo(): array
    {
        $videos = [
            nexus_trans('torrent.technicalinfo_duration') => $this->getDuration(),
            nexus_trans('torrent.technicalinfo_resolution') => $this->getResolution(),
            nexus_trans('torrent.technicalinfo_bit_rate') => $this->getTotalBitrate(),
            'HDR' => $this->getHDRFormat(),
            nexus_trans('torrent.technicalinfo_bit_depth') => $this->getBitDepth(),
            nexus_trans('torrent.technicalinfo_frame_rate') => $this->getFrameRate(),
            nexus_trans('torrent.technicalinfo_profile') => $this->getProfile(),
            nexus_trans('torrent.technicalinfo_format') => $this->getVideoFormat(),
            nexus_trans('torrent.technicalinfo_extras') => $this->getExtras(),
        ];
        $videos = array_filter($videos) ?: null;
        $audios = $this->getAudios() ?: null;
        $subtitles = $this->getSubtitles() ?: null;

        return compact('videos', 'audios', 'subtitles');
    }

    /**
     * 在详情页面渲染
     */
    public function renderOnDetailsPage(): string
    {
        global $lang_functions;

        // 获取所有DISC
        $allDiscs = $this->getAllDiscs();

        // 检查是否有有效的媒体数据（至少包含VIDEO或AUDIO）
        $hasValidData = false;
        if (! empty($allDiscs)) {
            foreach ($allDiscs as $disc) {
                if ((isset($disc['video']) && ! empty($disc['video'])) ||
                    (isset($disc['audio']) && ! empty($disc['audio']))) {
                    $hasValidData = true;
                    break;
                }
            }
        }

        // 如果没有有效数据，隐藏显示原始BDINFO
        if (! $hasValidData) {
            $rawBdInfo = sprintf('[spoiler=%s][raw]<pre>%s</pre>[/raw][/spoiler]', nexus_trans('torrent.show_hide_bd_info'), $this->bdInfo);

            return sprintf('<div class="nexus-media-info-raw">%s</div>', format_comment($rawBdInfo, false));
        }

        $result = '';

        // 为每个DISC生成表格
        foreach ($allDiscs as $discIndex => $disc) {
            // 临时设置当前DISC数据
            $originalBdInfoArr = $this->bdInfoArr;
            $this->bdInfoArr = $disc;

            $summaryInfo = $this->getSummaryInfo();
            $videos = $summaryInfo['videos'] ?: [];
            $audios = $summaryInfo['audios'] ?: [];
            $subtitles = $summaryInfo['subtitles'] ?: [];

            if (empty($videos) && empty($audios) && empty($subtitles)) {
                continue;
            }

            // 添加DISC标题（如果有多个DISC）
            if (count($allDiscs) > 1) {
                $discTitle = $disc['disc_info']['title'] ?? '';
                $result .= '<h4 style="margin: 10px 0 5px 0; color: #333;">Disc #'.($discIndex + 1).' : '.htmlspecialchars($discTitle).'</h4>';
            }

            $result .= '<table style="border: none;width: 100%"><tbody><tr>';
            $cols = 0;
            if (! empty($videos)) {
                $cols++;
                $result .= $this->buildTdTable($videos);
            }
            if (! empty($audios)) {
                $cols++;
                $result .= $this->buildTdTable($audios);
            }
            if (! empty($subtitles)) {
                $cols++;
                $result .= $this->buildTdTable($subtitles);
            }
            $result .= '</tr>';

            // 恢复原始数据
            $this->bdInfoArr = $originalBdInfoArr;

            $result .= '</tbody></table>';

            // 在DISC之间添加分隔线（除了最后一个）
            if ($discIndex < count($allDiscs) - 1) {
                $result .= '<hr style="margin: 15px 0; border: none; border-top: 1px solid #ddd;">';
            }
        }

        // 添加原始BDINFO
        $rawBdInfo = sprintf('[spoiler=%s][raw]<pre>%s</pre>[/raw][/spoiler]', nexus_trans('torrent.show_hide_bd_info'), $this->bdInfo);
        if (function_exists('format_comment')) {
            $result .= sprintf('<div class="nexus-media-info-raw" style="margin-top: 15px;">%s</div>', format_comment($rawBdInfo, false));
        } else {
            $result .= sprintf('<div class="nexus-media-info-raw" style="margin-top: 15px;">%s</div>', $rawBdInfo);
        }

        return $result;
    }

    /**
     * 构建表格单元格
     */
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
        if (! empty($hiddenParts)) {
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

    /**
     * 清理字符串
     */
    private function trim(string $value): string
    {
        return trim($value, " \n\r\t\v\0\u{A0}");
    }
}
