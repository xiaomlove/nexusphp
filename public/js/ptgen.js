jQuery('.btn-get-pt-gen').on('click', function () {
    let input = jQuery(this).closest('td').find('[data-pt-gen]')
    let form = jQuery(this).closest('form')
    let value = input.val().trim()
    if (value == '') {
        return
    }
    let params = {
        action: 'getPtGen',
        params: {url: value}
    }
    jQuery('body').loading({
        stoppable: false
    });
    jQuery.post('ajax.php', params, function (response) {
        jQuery('body').loading('stop');
        if (response.ret != 0) {
            alert(response.msg)
            return
        }
        doInsert(response.data.format, '', false)
        if (response.data.aka && response.data.site === 'douban') {
            form.find("input[name=small_descr]").val(response.data.aka.join("/"))
        }
        if (response.data.imdb_link) {
            form.find("input[data-pt-gen=url]").val(response.data.imdb_link)
        }
    }, 'json')
})

//auto fill quality
function autoSelect(value) {
    // console.log(`autoSelect: ${value}`)
    value = value.replace(/[-\/\.]+/ig, '').toUpperCase();
    let names = ["source_sel", "medium_sel", "codec_sel", "audiocodec_sel", "standard_sel", "processing_sel", "team_sel"];
    for (let i = 0; i < names.length; i++) {
        const name = names[i];
        const select = jQuery("select[name="+name+"]")
        if (select.prop('disabled')) {
            console.log("name: " + name + " is disabled, skip")
            continue
        }
        // console.log("check name: " + name)
        select.children("option").each(function (index, option) {
            let _option = jQuery(option)
            let optionText = _option.text().replace(/[-\/\.]+/ig, '').toUpperCase();
            // console.log("check option text: " + optionText + " match value: " + value)
            if (optionText == value) {
                console.log(`name: ${name}, optionText: ${optionText} === value: ${value}, break`)
                select.val(option.getAttribute('value'))
                return false
            }
        })
    }
}
jQuery("#compose").on("click", ".nexus-action-btn",function () {
    let box = jQuery(this).closest(".nexus-input-box")
    let inputValue = box.find("[name=name]").val();
    if (inputValue.trim() == '') {
        return
    }
    let parts = inputValue.split(/[\s]+/i)
    let count = parts.length;
    for (let i = 0; i < count; i++) {
        if (i < count - 1) {
            autoSelect(parts[i])
        } else {
            let arr = parts[i].split("-")
            if (arr[0]) {
                autoSelect(arr[0])
            }
            if (arr[1]) {
                autoSelect(arr[1])
            }
        }
    }
})

//change section
function showHideQuality(activeSelect) {
    let mode = activeSelect.getAttribute("data-mode")
    let value = activeSelect.value
    console.log(mode, value)
    if (value == 0) {
        //enable all section
        jQuery("select[name=type]").prop("disabled", false);
        //disable all quality tr
        jQuery("tr.mode").hide().find("select").prop("disabled", true);
    } else {
        jQuery("select[name=type]").prop("disabled", true);
        activeSelect.disabled = false
        //active current section quality tr
        jQuery("tr.mode_" + mode).show().find("select").prop("disabled", false);
    }
}
jQuery("#compose").on("change", "select[name=type]", function () {
    showHideQuality(this)
});
