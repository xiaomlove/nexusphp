;
jQuery('.spoiler-title').on('click', function () {
    let content = jQuery(this).parent().next();
    if (content.hasClass('collapse')) {
        content.height(content[0].scrollHeight).removeClass('collapse')
    } else {
        content.height(0).addClass('collapse')
    }
});

