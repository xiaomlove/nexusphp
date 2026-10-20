/*
 * NovaHD torrent review assistant.
 * Review rules adapted from CS-Torrent-Assistant-New and HDKylin-Torrent-Assistant (MIT).
 */
(function ($) {
    'use strict';

    var data = window.NovaTorrentReviewAssistant;
    var panel = $('#nova-review-assistant');
    if (!data || !panel.length) {
        return;
    }

    var state = {
        baseErrors: [],
        baseWarnings: [],
        imageErrors: [],
        imageWarnings: [],
        checkingImages: false,
        checkedImages: 0,
        validImages: 0
    };

    function addUnique(list, message) {
        if (message && list.indexOf(message) === -1) {
            list.push(message);
        }
    }

    function normalize(value) {
        return String(value || '').toLowerCase().replace(/[._\s-]+/g, '');
    }

    function detectCodec(value) {
        var text = normalize(value);
        if (!text || /other|其他/.test(text)) return '';
        if (/x?265|hevc/.test(text)) return 'h265';
        if (/x?264|avc/.test(text)) return 'h264';
        if (/av1/.test(text)) return 'av1';
        if (/vc1/.test(text)) return 'vc1';
        if (/mpeg2/.test(text)) return 'mpeg2';
        return '';
    }

    function detectResolution(value) {
        var text = normalize(value);
        if (!text || /other|其他/.test(text)) return '';
        if (/(?:width|宽度):?(?:7680|8192)/.test(text)) return '4320p';
        if (/(?:width|宽度):?(?:3840|4096)/.test(text)) return '2160p';
        if (/(?:width|宽度):?2560/.test(text)) return '1440p';
        if (/(?:width|宽度):?(?:1920|2048)/.test(text)) return '1080p';
        if (/(?:width|宽度):?1280/.test(text)) return '720p';
        if (/(?:width|宽度):?(?:640|720)/.test(text)) return '480p';
        if (/7680x|8192x|4320|8k/.test(text)) return '4320p';
        if (/3840x|4096x|2160|4k|uhd/.test(text)) return '2160p';
        if (/2048x1080/.test(text)) return '1080p';
        if (/2560x|1440/.test(text)) return '1440p';
        if (/1920x|1080/.test(text)) return '1080p';
        if (/1280x|720/.test(text)) return '720p';
        if (/720x|640x|480|576/.test(text)) return '480p';
        return '';
    }

    function detectMedium(value) {
        var text = normalize(value);
        if (!text || /other|其他/.test(text)) return '';
        if (/remux/.test(text)) return 'remux';
        if (/webdl|webrip|web/.test(text)) return 'web';
        if (/hdtv/.test(text)) return 'hdtv';
        if (/bluray|blu/.test(text)) return 'bluray';
        if (/dvd/.test(text)) return 'dvd';
        return '';
    }

    function detectAudio(value) {
        var text = normalize(value);
        if (!text || /other|其他/.test(text)) return '';
        if (/truehdatmos/.test(text)) return 'truehdatmos';
        if (/truehd/.test(text)) return 'truehd';
        if (/dtshdma|dtshdmaster/.test(text)) return 'dtshdma';
        if (/dtsx/.test(text)) return 'dtsx';
        if (/eac3|ddp|ddplus/.test(text)) return 'eac3';
        if (/ac3|dolbydigital/.test(text)) return 'ac3';
        if (/flac/.test(text)) return 'flac';
        if (/aac/.test(text)) return 'aac';
        if (/lpcm/.test(text)) return 'lpcm';
        if (/dts/.test(text)) return 'dts';
        return '';
    }

    function compareTaxonomy(label, selected, detected, severity) {
        if (!selected || !detected || selected === detected) {
            return;
        }
        addUnique(severity === 'error' ? state.baseErrors : state.baseWarnings, label + '与主标题不一致');
    }

    function runBaseChecks() {
        state.baseErrors = [];
        state.baseWarnings = [];
        var title = String(data.title || '').trim();
        var subtitle = String(data.subtitle || '').trim();
        var description = String(data.descriptionText || '').trim();
        var taxonomy = data.taxonomy || {};

        if (!title) addUnique(state.baseErrors, '主标题为空');
        if (!subtitle) addUnique(state.baseErrors, '副标题为空');
        if (!String(data.category || '').trim()) addUnique(state.baseErrors, '未选择资源分类');
        if (!description) addUnique(state.baseErrors, '未填写资源简介');
        if (/\s{2,}/.test(title)) addUnique(state.baseWarnings, '主标题包含连续空格');

        if (data.isVideo) {
            if (!taxonomy.medium) addUnique(state.baseErrors, '未选择媒介');
            if (!taxonomy.codec) addUnique(state.baseErrors, '未选择视频编码');
            if (!taxonomy.resolution) addUnique(state.baseErrors, '未选择分辨率');
            if (!taxonomy.audioCodec) addUnique(state.baseWarnings, '未选择音频编码');
            if (!data.technicalInfoPresent) addUnique(state.baseErrors, '缺少 MediaInfo 或 BDInfo');
            if (!data.externalUrl && !/(imdb\.com|douban\.com|themoviedb\.org|\btt\d{6,}\b)/i.test(description)) {
                addUnique(state.baseWarnings, '简介中未检测到 IMDb、豆瓣或 TMDB 信息');
            }
        }

        if (data.technicalInfoHasBbcode) addUnique(state.baseErrors, 'MediaInfo 中含有 BBCode 标签');
        if (data.descriptionContainsMediaInfo && data.technicalInfoPresent) addUnique(state.baseWarnings, '简介中可能重复放置了 MediaInfo');

        var titleCodec = detectCodec(title);
        var selectedCodec = detectCodec(taxonomy.codec);
        var technicalCodec = detectCodec(data.technicalInfoSample);
        compareTaxonomy('视频编码', selectedCodec, titleCodec, 'error');
        compareTaxonomy('MediaInfo 视频编码', selectedCodec, technicalCodec, 'error');

        var titleResolution = detectResolution(title);
        var selectedResolution = detectResolution(taxonomy.resolution);
        var technicalResolution = detectResolution(data.technicalInfoSample);
        compareTaxonomy('分辨率', selectedResolution, titleResolution, 'error');
        if ((titleResolution || selectedResolution) && technicalResolution && (titleResolution || selectedResolution) !== technicalResolution) {
            addUnique(state.baseErrors, 'MediaInfo 分辨率与主标题明显不一致');
        }

        compareTaxonomy('媒介', detectMedium(taxonomy.medium), detectMedium(title), 'warning');
        compareTaxonomy('音频编码', detectAudio(taxonomy.audioCodec), detectAudio(title), 'warning');
    }

    function checkImage(url) {
        return new Promise(function (resolve) {
            var settled = false;
            var image = new Image();
            var finish = function (valid, timedOut) {
                if (settled) return;
                settled = true;
                clearTimeout(timer);
                resolve({url: url, valid: valid, timedOut: timedOut});
            };
            var timer = setTimeout(function () { finish(false, true); }, 10000);
            image.onload = function () { finish(image.naturalWidth > 0, false); };
            image.onerror = function () { finish(false, false); };
            image.src = url;
        });
    }

    function runImageChecks() {
        var urls = Array.isArray(data.imageUrls) ? data.imageUrls : [];
        state.imageErrors = [];
        state.imageWarnings = [];
        state.checkedImages = urls.length;
        state.validImages = 0;
        state.checkingImages = true;
        render();

        if (!urls.length) {
            if (data.isVideo) addUnique(state.imageErrors, '缺少海报或截图');
            state.checkingImages = false;
            render();
            return Promise.resolve();
        }

        return Promise.all(urls.map(checkImage)).then(function (results) {
            var failed = results.filter(function (result) { return !result.valid; });
            state.validImages = results.length - failed.length;
            failed.slice(0, 5).forEach(function (result) {
                addUnique(state.imageErrors, (result.timedOut ? '图片加载超时：' : '图片加载失败：') + result.url);
            });
            if (failed.length > 5) addUnique(state.imageErrors, '另有 ' + (failed.length - 5) + ' 张图片加载失败');
            if (data.isVideo && state.validImages < 3) addUnique(state.imageWarnings, '有效海报和截图少于 3 张');
            state.checkingImages = false;
            render();
        });
    }

    function combinedErrors() {
        return state.baseErrors.concat(state.imageErrors);
    }

    function combinedWarnings() {
        return state.baseWarnings.concat(state.imageWarnings);
    }

    function renderList(selector, items, emptyText) {
        var list = $(selector).empty();
        if (!items.length) {
            $('<li class="is-clear"></li>').text(emptyText).appendTo(list);
            return;
        }
        items.forEach(function (item) {
            $('<li></li>').text(item).appendTo(list);
        });
    }

    function render() {
        if (!data.enabled) return;
        var errors = combinedErrors();
        var warnings = combinedWarnings();
        $('#nova-review-error-count').text(errors.length);
        $('#nova-review-warning-count').text(warnings.length);
        $('#nova-review-image-count').text(state.checkingImages ? '检查中' : state.validImages + '/' + state.checkedImages);
        renderList('#nova-review-errors', errors, '未检测到必须修正的问题');
        renderList('#nova-review-warnings', warnings, '未检测到需要复核的问题');

        var result = '可通过';
        if (state.checkingImages) result = '检查中';
        else if (errors.length) result = '需修正';
        else if (warnings.length) result = '建议复核';
        $('#nova-review-result').text(result);
        $('#nova-review-pass').prop('disabled', state.checkingImages || errors.length > 0);
        $('#nova-review-notify').prop('disabled', state.checkingImages || errors.length === 0);
    }

    function issueText() {
        var errors = combinedErrors();
        var warnings = combinedWarnings();
        var lines = [];
        if (errors.length) {
            lines.push('需要修正：');
            errors.forEach(function (item, index) { lines.push((index + 1) + '. ' + item); });
        }
        if (warnings.length) {
            if (lines.length) lines.push('');
            lines.push('建议复核：');
            warnings.forEach(function (item, index) { lines.push((index + 1) + '. ' + item); });
        }
        return lines.join('\n') || '审种助手未检测到问题';
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }
        var input = $('<textarea></textarea>').val(text).appendTo('body').select();
        document.execCommand('copy');
        input.remove();
        return Promise.resolve();
    }

    function openApproval(comment, status) {
        layer.open({
            type: 2,
            title: '种子审核',
            area: ['70%', '620px'],
            content: '/web/torrent-approval-page?torrent_id=' + data.torrentId,
            success: function (layerObject) {
                var iframe = layerObject.find('iframe')[0];
                if (!iframe) return;
                iframe.addEventListener('load', function () {
                    try {
                        var doc = iframe.contentDocument;
                        if (status !== null && status !== undefined) {
                            var radio = doc.querySelector('input[name="approval_status"][value="' + status + '"]');
                            if (radio) radio.click();
                        }
                        var textarea = doc.querySelector('#approval-comment');
                        if (textarea && comment) textarea.value = comment;
                        if (iframe.contentWindow.layui && iframe.contentWindow.layui.form) {
                            iframe.contentWindow.layui.form.render();
                        }
                    } catch (error) {
                        console.error(error);
                    }
                });
            }
        });
    }

    function quickPass() {
        var errors = combinedErrors();
        if (state.checkingImages || errors.length) {
            layer.alert('检查尚未完成或仍有必须修正的问题');
            return;
        }
        var warningCount = combinedWarnings().length;
        var confirmText = warningCount
            ? '当前没有错误，但有 ' + warningCount + ' 项警告。确认审核通过吗？'
            : '审种助手未检测到问题，确认审核通过吗？';
        layer.confirm(confirmText, function (confirmIndex) {
            layer.close(confirmIndex);
            var button = $('#nova-review-pass').prop('disabled', true).text('提交中');
            $.get('/web/torrent-approval-page?torrent_id=' + data.torrentId).done(function (html) {
                var wrapper = $('<div></div>').append($.parseHTML(html, document, false));
                var token = wrapper.find('input[name="_token"]').val();
                if (!token) {
                    layer.alert('无法获取审核安全令牌，请使用审核窗口操作');
                    button.prop('disabled', false).text('一键通过');
                    return;
                }
                $.post('/web/torrent-approval', {
                    _token: token,
                    torrent_id: data.torrentId,
                    approval_status: data.approvalAllow,
                    comment: '审种助手检查通过'
                }, function (response) {
                    if (response.ret != 0) {
                        layer.alert(response.msg || '审核提交失败');
                        button.prop('disabled', false).text('一键通过');
                        return;
                    }
                    layer.alert('审核已通过', function () { window.location.reload(); });
                }, 'json').fail(function () {
                    layer.alert('审核提交失败，请使用审核窗口操作');
                    button.prop('disabled', false).text('一键通过');
                });
            }).fail(function () {
                layer.alert('无法读取审核页面');
                button.prop('disabled', false).text('一键通过');
            });
        });
    }

    $('#nova-review-rerun').on('click', function () {
        runBaseChecks();
        runImageChecks();
    });

    $('#nova-review-notify').on('click', function () {
        var errors = combinedErrors();
        if (state.checkingImages || !errors.length) {
            layer.alert('当前没有需要发送的整改问题');
            return;
        }
        var text = issueText();
        layer.confirm('确定将资源设为审核不通过，并把整改问题通过站内信发送给发布员吗？', function (index) {
            layer.close(index);
            var button = $('#nova-review-notify').prop('disabled', true).text('发送中');
            $.post('torrent-review-assistant-action.php', {
                action: 'notify_correction',
                token: data.actionToken,
                torrent_id: data.torrentId,
                issues: text
            }, function (response) {
                if (!response.success) {
                    layer.alert(response.message || '整改通知发送失败');
                    button.prop('disabled', false).text('发送整改通知');
                    return;
                }
                layer.alert(response.message, function () { window.location.reload(); });
            }, 'json').fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '整改通知发送失败';
                layer.alert(message);
                button.prop('disabled', false).text('发送整改通知');
            });
        });
    });

    $('#nova-review-open').on('click', function () {
        openApproval('', null);
    });

    $('#nova-review-pass').on('click', quickPass);

    $('#nova-review-toggle').on('click', function () {
        var button = $(this);
        var enabled = button.attr('data-enabled');
        var actionText = enabled === 'yes' ? '开启' : '关闭';
        layer.confirm('确定在全站' + actionText + '审种助手吗？', function (index) {
            layer.close(index);
            button.prop('disabled', true).text('处理中');
            $.post('torrent-review-assistant-toggle.php', {
                enabled: enabled,
                token: data.toggleToken
            }, function (response) {
                if (!response.success) {
                    layer.alert(response.message || '设置修改失败');
                    button.prop('disabled', false).text(actionText + '助手');
                    return;
                }
                layer.alert(response.message, function () { window.location.reload(); });
            }, 'json').fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '设置修改失败';
                layer.alert(message);
                button.prop('disabled', false).text(actionText + '助手');
            });
        });
    });

    if (data.enabled) {
        runBaseChecks();
        runImageChecks();
    }
})(jQuery);
