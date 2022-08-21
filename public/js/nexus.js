jQuery(document).ready(function () {
    jQuery('.spoiler-title').on('click', function () {
        let content = jQuery(this).parent().next();
        if (content.hasClass('collapse')) {
            content.height(content[0].scrollHeight).removeClass('collapse')
        } else {
            content.height(0).addClass('collapse')
        }
    })

    var previewEle = jQuery('#nexus-preview')
    jQuery(".preview").hover(function (e) {
        let _this = jQuery(this);
        let src = _this.attr("src")
        if (src) {
            previewEle.attr("src", src).fadeIn("fast");
        }
    }, function (e) {
        previewEle.fadeOut("fast");
    }).on("mousemove", function (e) {
        previewEle.css({"left": e.pageX + 10, "top": e.pageY + 10})
    })
})
