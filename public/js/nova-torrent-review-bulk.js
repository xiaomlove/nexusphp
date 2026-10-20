(function ($) {
    'use strict';

    var data = window.NovaTorrentReviewBulk;
    var toolbar = $('#nova-review-bulk');
    if (!data || !toolbar.length) {
        return;
    }

    var seen = {};
    $('table.torrents > tbody > tr').each(function () {
        var row = $(this);
        var link = row.find('a[href*="details.php?id="]').first();
        if (!link.length) return;
        var match = link.attr('href').match(/[?&]id=(\d+)/);
        if (!match || seen[match[1]]) return;
        seen[match[1]] = true;
        var label = $('<label class="nova-review-row-pick" title="选择此种子进行批量审核"></label>');
        $('<input type="checkbox" class="nova-review-bulk-check">').val(match[1]).appendTo(label);
        link.closest('td').prepend(label);
    });

    function updateCount() {
        var all = $('.nova-review-bulk-check');
        var selected = all.filter(':checked');
        $('#nova-review-bulk-count').text(selected.length);
        $('#nova-review-bulk-submit').prop('disabled', selected.length === 0);
        $('#nova-review-bulk-all').prop('checked', all.length > 0 && selected.length === all.length);
    }

    $('#nova-review-bulk-all').on('change', function () {
        $('.nova-review-bulk-check').prop('checked', this.checked);
        updateCount();
    });

    $(document).on('change', '.nova-review-bulk-check', updateCount);

    $('#nova-review-bulk-submit').on('click', function () {
        var button = $(this);
        var ids = $('.nova-review-bulk-check:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length) return;
        layer.confirm('确定忽略审种助手错误，批量通过选中的 ' + ids.length + ' 个待审核种子吗？', function (index) {
            layer.close(index);
            button.prop('disabled', true).text('处理中');
            $.post('torrent-review-assistant-action.php', {
                action: 'force_approve_batch',
                token: data.token,
                torrent_ids: ids
            }, function (response) {
                if (!response.success) {
                    layer.alert(response.message || '批量审核失败');
                    button.prop('disabled', false).text('忽略错误批量同意');
                    return;
                }
                layer.alert(response.message, function () { window.location.reload(); });
            }, 'json').fail(function (xhr) {
                var message = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : '批量审核失败';
                layer.alert(message);
                button.prop('disabled', false).text('忽略错误批量同意');
            });
        });
    });

    updateCount();
})(jQuery);

