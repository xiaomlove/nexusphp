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
        if (response.data.aka) {
            form.find("input[name=small_descr]").val(response.data.aka.join("/"))
        }
        if (response.data.imdb_link) {
            form.find("input[name=url]").val(response.data.imdb_link)
        }
    }, 'json')
})
