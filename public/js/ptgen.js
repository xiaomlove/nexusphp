jQuery('.btn-get-pt-gen').on('click', function () {
    let input = jQuery(this).closest('td').find('[data-pt-gen]')
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
        doInsert(response.data, '', false)
    }, 'json')
})
