function newAlert(icon,msg){
    let div=document.createElement("div");
    div.style.position="fixed";
    div.style.background="#FFF";
    div.style.transition="all .3s ease-in-out 0s";
    div.style.top="-300px";
    div.style.left="50%";
    div.style.transform="translate(-50%,0)";
    div.style.padding="20px 30px";
    div.style.zIndex="10000";
    div.style.borderRadius="6px";
    div.style.userSelect="none";
    div.style.boxShadow="0 0 10px #666";
    div.style.opacity=".8";
    div.style.fontWeight="600";
    div.style.minWidth="260px";
    let icons=[
        "<span class='fas fa-info-circle' style='color:#0055FF'></span>&nbsp;&nbsp;",
        "<span class='fas fa-plus-circle' style='color:#FF0000;transform: rotate(45deg);'></span>&nbsp;&nbsp;",
        "<span class='fas fa-exclamation-circle' style='color:#FFAA00'></span>&nbsp;&nbsp;"
    ];
    div.innerHTML=icons[icon]+msg;
    let body=document.getElementsByTagName("body")[0]
    body.appendChild(div);
    setTimeout(()=>{
        div.style.display="block";
        div.style.top="30px";
        setTimeout(()=>{
            div.style.top="-300px";
            setTimeout(()=>{
                body.removeChild(div);
            },300);
        },2000);
    },1);
}