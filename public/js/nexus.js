jQuery(document).ready(function () {
    jQuery('.spoiler-title').on('click', function () {
        let content = jQuery(this).parent().next();
        if (content.hasClass('collapse')) {
            content.height(content[0].scrollHeight).removeClass('collapse')
        } else {
            content.height(0).addClass('collapse')
        }
    })

    function getImgPosition(e, imgEle) {
        // console.log(e, imgEle)
        let imgWidth = imgEle.prop('naturalWidth')
        let imgHeight = imgEle.prop("naturalHeight")
        let ratio = imgWidth / imgHeight;
        let offsetX = 10;
        let offsetY = 10;
        let width = window.innerWidth - e.clientX;
        let height = window.innerHeight - e.clientY;
        let changeOffsetY = 0;
        let changeOffsetX = false;
        if (e.clientX > window.innerWidth / 2 && e.clientX + imgWidth > window.innerWidth) {
            changeOffsetX = true
            width = e.clientX
        }
        if (e.clientY > window.innerHeight / 2) {
            if (e.clientY + imgHeight/2 > window.innerHeight) {
                changeOffsetY = 1
                height = e.clientY
            } else if (e.clientY + imgHeight > window.innerHeight) {
                changeOffsetY = 2
                height = e.clientY
            }
        }
        let log = `innerWidth: ${window.innerWidth}, innerHeight: ${window.innerHeight}, pageX: ${e.pageX}, pageY: ${e.pageY}, imgWidth: ${imgWidth}, imgHeight: ${imgHeight}, width: ${width}, height: ${height}, offsetX: ${offsetX}, offsetY: ${offsetY}, changeOffsetX: ${changeOffsetX}, changeOffsetY: ${changeOffsetY}`
        console.log(log)
        if (imgWidth > width) {
            imgWidth = width;
            imgHeight = imgWidth / ratio;
        }
        if (imgHeight > height) {
            imgHeight = height;
            imgWidth = imgHeight * ratio;
        }
        if (changeOffsetX) {
            offsetX = -(e.clientX - width + 10)
        }
        if (changeOffsetY == 1) {
            offsetY = - (imgHeight - (window.innerHeight - e.clientY))
        } else if (changeOffsetY == 2) {
            offsetY = - imgHeight/2
        }
        return {imgWidth, imgHeight,offsetX, offsetY}
    }

    // preview
    function getPosition(e, position) {
        return {
            left: e.pageX + position.offsetX,
            top: e.pageY + position.offsetY,
            width: position.imgWidth,
            height: position.imgHeight
        }
    }
    var previewEle = jQuery('#nexus-preview')
    var imgEle, selector = 'img.preview', imgPosition
    jQuery("body").on("mouseover", selector, function (e) {
        imgEle = jQuery(this);
        // previewEle = jQuery('<img style="display: none;position:absolute;">').appendTo(imgEle.parent())
        imgPosition = getImgPosition(e, imgEle)
        let position = getPosition(e, imgPosition)
        let src = imgEle.attr("src")
        if (src) {
            previewEle.attr("src", src).css(position).fadeIn("fast");
        }
    }).on("mouseout", selector, function (e) {
        // previewEle.remove()
        // previewEle = null
        previewEle.hide()
    }).on("mousemove", selector, function (e) {
        let position = getPosition(e, imgPosition)
        previewEle.css(position)
    })

    // lazy load
    if ("IntersectionObserver" in window) {
        const imgList = [...document.querySelectorAll('.nexus-lazy-load')]
        var io = new IntersectionObserver((entries) =>{
            entries.forEach(entry  => {
                const el = entry.target
                const intersectionRatio = entry.intersectionRatio
                // console.log(`el, ${el.getAttribute('data-src')}, intersectionRatio: ${intersectionRatio}`)
                if (intersectionRatio > 0 && intersectionRatio <= 1 && !el.classList.contains('preview')) {
                    // console.log(`el, ${el.getAttribute('data-src')}, loadImg`)
                    const source = el.dataset.src
                    el.src = source
                    el.classList.add('preview')
                }
                el.onload = el.onerror = () => io.unobserve(el)
            })
        })

        imgList.forEach(img => io.observe(img))
    }

    //claim
    jQuery("body").on("click", "[data-claim_id]", function () {
        let _this = jQuery(this)
        let box = _this.closest('td')
        let claimId = _this.attr("data-claim_id")
        let torrentId = _this.attr("data-torrent_id")
        let action = _this.attr("data-action")
        let reload = _this.attr("data-reload")
        let confirmText = _this.attr("data-confirm")
        let showStyle = "width: max-content;display: flex;align-items: center";
        let hideStyle = "width: max-content;display: none;align-items: center";
        let params = {}
        if (claimId > 0) {
            params.id = claimId
        } else {
            params.torrent_id = torrentId
        }
        let modalConfig = {title: "Info", btn: ['OK', 'Cancel'], btnAlign: 'c'}
        layer.confirm(confirmText, modalConfig, function (confirmIndex) {
            jQuery.post("ajax.php", {"action": action, params: params}, function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, modalConfig)
                    return
                }
                if (reload > 0) {
                    window.location.reload();
                    return;
                }
                if (claimId > 0) {
                    //do remove, show add
                    box.find("[data-action=addClaim]").attr("style", showStyle).attr("data-claim_id", 0)
                    box.find("[data-action=removeClaim]").attr("style", hideStyle)
                } else {
                    //do add, show remove, update claim_id
                    box.find("[data-action=addClaim]").attr("style", hideStyle)
                    box.find("[data-action=removeClaim]").attr("style", showStyle).attr("data-claim_id", response.data.id)
                }
                layer.close(confirmIndex)
            }, "json")
        })
    })

})
