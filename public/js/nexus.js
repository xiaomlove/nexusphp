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
    jQuery("body").on("mouseover", ".preview", function (e) {
        let src = jQuery(this).attr("src")
        if (src) {
            previewEle.attr("src", src).css({"display": "block", "left": e.pageX + 5, "top": e.pageY + 5})
        }
    });
    jQuery("body").on("mouseout", ".preview", function (e) {
        previewEle.hide()
    });
})
