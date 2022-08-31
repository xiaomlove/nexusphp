jQuery(document).ready(function () {
    jQuery('.spoiler-title').on('click', function () {
        let content = jQuery(this).parent().next();
        if (content.hasClass('collapse')) {
            content.height(content[0].scrollHeight).removeClass('collapse')
        } else {
            content.height(0).addClass('collapse')
        }
    })

    // preview
    function getPosition(e, imgEle) {
        let imgWidth = imgEle.prop('naturalWidth')
        let imgHeight = imgEle.prop("naturalHeight")
        console.log(`imgWidth: ${imgWidth}, imgHeight: ${imgHeight}`)
        let ratio = imgWidth / imgHeight;
        if (imgWidth > window.innerWidth) {
            imgWidth = window.innerWidth;
            imgHeight = imgWidth / ratio;
        }
        if (imgHeight > window.innerHeight) {
            imgHeight = window.innerHeight;
            imgWidth = imgHeight * ratio;
        }
        let width = imgWidth, height= imgHeight;
        let left = e.pageX + 10;
        if (left + imgWidth > window.innerWidth) {
            left = e.pageX - 10 - imgWidth
        }
        let top = e.pageY + 10;
        if (top + imgHeight > window.innerHeight) {
            top = e.pageY - imgHeight / 2
        }
        let result = {left, top, width, height}
        console.log(result)
        return result
    }
    var previewEle = jQuery('#nexus-preview')
    var imgEle, selector = '.preview'
    jQuery("body").on("mouseover", selector, function (e) {
        imgEle = jQuery(this);
        let position = getPosition(e, imgEle)
        let src = imgEle.attr("src")
        if (src) {
            previewEle.attr("src", src).css(position).fadeIn("fast");
        }
    }).on("mouseout", selector, function (e) {
        previewEle.fadeOut("fast");
    }).on("mousemove", selector, function (e) {
        let position = getPosition(e, imgEle)
        previewEle.css(position)
    })

    // lazy load
    if ("IntersectionObserver" in window) {
        const imgList = [...document.querySelectorAll('.nexus-lazy-load')]
        var io = new IntersectionObserver((entries) =>{
            entries.forEach(item => {
                // isIntersecting是一个Boolean值，判断目标元素当前是否可见
                if (item.isIntersecting) {
                    item.target.src = item.target.dataset.src
                    item.target.classList.add('preview')
                    // 图片加载后即停止监听该元素
                    io.unobserve(item.target)
                }
            })
        }, {
            root: document.querySelector('body')
        })

        // observe遍历监听所有img节点
        imgList.forEach(img => io.observe(img))
    }

})
