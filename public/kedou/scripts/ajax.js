function get(url){
    let xhr=new XMLHttpRequest();
    xhr.open("GET",url,false);
    xhr.send(null);
    return xhr.responseText;
}
function post(url,data){
    let xhr=new XMLHttpRequest();
    xhr.open("POST",url,false);
    let send_data=JSON.stringify(data).replaceAll("{","");
    send_data=send_data.replaceAll("}","");
    send_data=send_data.replaceAll("\"","");
    send_data=send_data.replaceAll("\'","");
    send_data=send_data.replaceAll(":","=");
    send_data=send_data.replaceAll(",","&");
    xhr.setRequestHeader ('Content-type', 'application/x-www-form-urlencoded');
    xhr.send(send_data);
    return xhr.responseText;
}