jQuery(document).ready(function () {
    jQuery('.spoiler-title').on('click', function () {
        let content = jQuery(this).parent().next();
        if (content.hasClass('collapse')) {
            content.height(content[0].scrollHeight).removeClass('collapse')
        } else {
            content.height(0).addClass('collapse')
        }
    })

    function getPosition(e, imgEle) {
        let imgWidth = imgEle.prop('naturalWidth')
        let imgHeight = imgEle.prop("naturalHeight")
        let left = e.pageX + 10;
        if (left + imgWidth > window.innerWidth) {
            left = e.pageX - 10 - imgWidth
        }
        let top = e.pageY + 10;
        if (top + imgHeight > window.innerHeight) {
            top = e.pageY - imgHeight / 2
        }
        return {left, top}
    }
    var previewEle = jQuery('#nexus-preview')
    var imgEle
    jQuery(".preview").hover(function (e) {
        imgEle = jQuery(this);
        let position = getPosition(e, imgEle)
        let src = imgEle.attr("src")
        if (src) {
            previewEle.attr("src", src).css(position).fadeIn("fast");
        }
    }, function (e) {
        previewEle.fadeOut("fast");
    }).on("mousemove", function (e) {
        let position = getPosition(e, imgEle)
        previewEle.css(position)
    })
})
