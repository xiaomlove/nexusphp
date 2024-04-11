document.getElementsByTagName("input")[0].onfocus=(e)=>{
    document.getElementsByClassName("input")[0].style.border="1px solid var(--primary-color)";
    document.getElementsByClassName("input")[0].style.boxShadow="0 0 10px var(--primary-color)";
    document.getElementsByClassName("input")[0].style.background="rgba(255, 255, 255, 1)";
    document.getElementsByClassName("fas")[0].style.color="var(--primary-color)";
};
document.getElementsByTagName("input")[0].onblur=(e)=>{
    document.getElementsByClassName("input")[0].style.border="1px solid #888";
    document.getElementsByClassName("input")[0].style.boxShadow="0 0 0px #888";
    document.getElementsByClassName("input")[0].style.background="rgba(255, 255, 255, 0)";
    document.getElementsByClassName("fas")[0].style.color="#888";
};
document.getElementsByTagName("input")[1].onfocus=(e)=>{
    document.getElementsByClassName("input")[1].style.border="1px solid var(--primary-color)";
    document.getElementsByClassName("input")[1].style.boxShadow="0 0 10px var(--primary-color)";
    document.getElementsByClassName("input")[1].style.background="rgba(255, 255, 255, 1)";
    document.getElementsByClassName("fas")[1].style.color="var(--primary-color)";
};
document.getElementsByTagName("input")[1].onblur=(e)=>{
    document.getElementsByClassName("input")[1].style.border="1px solid #888";
    document.getElementsByClassName("input")[1].style.boxShadow="0 0 0px #888";
    document.getElementsByClassName("input")[1].style.background="rgba(255, 255, 255, 0)";
    document.getElementsByClassName("fas")[1].style.color="#888";
};
document.getElementsByTagName("input")[2].onfocus=(e)=>{
    document.getElementsByClassName("input")[2].style.border="1px solid var(--primary-color)";
    document.getElementsByClassName("input")[2].style.boxShadow="0 0 10px var(--primary-color)";
    document.getElementsByClassName("input")[2].style.background="rgba(255, 255, 255, 1)";
};
document.getElementsByTagName("input")[2].onblur=(e)=>{
    document.getElementsByClassName("input")[2].style.border="1px solid #888";
    document.getElementsByClassName("input")[2].style.boxShadow="0 0 0px #888";
    document.getElementsByClassName("input")[2].style.background="rgba(255, 255, 255, 0)";
};
document.getElementById("login").onclick=()=>{
    let username=document.getElementById("username").value;
    let password=document.getElementById("password").value;
    if(username=="" || password==""){
        newAlert(1,"Please type your username and password.");
        return;
    }
    document.getElementById("login").disabled = true;
    document.getElementById("login").innerText="Just a moment.";
    document.getElementsByTagName('form')[0].submit();
    /*let ret=post("./login.php",{
        "username":username,
        "password":password
    });
    if(ret=="Success."){
        newAlert(0,"Success.");
        window.location="./index.php";
    }else{
        newAlert(1,"Please check your username and password.");
        document.getElementById("login").disabled = false;
        document.getElementById("login").innerText="LOGIN";
    }*/
};
document.onkeyup=(e)=>{
    if(e.code=="Enter"){
        document.getElementById("login").onclick();
    }
};
function bg(){
    let canvas=document.createElement("canvas");
    canvas.setAttribute("width","640px");
    canvas.setAttribute("height","640px");
    let bgs=[
        "001.jpg",
        "002.jpg",
        "003.jpg",
        "004.jpg",
        "005.jpg",
        "006.jpg",
        "007.jpg",
        "008.jpg",
        "009.jpg",
        "010.jpg",
        "011.jpg",
        "012.jpg",
        "013.jpg",
        "014.jpg",
        "015.jpg",
        "016.jpg",
        "017.jpg",
        "018.jpg",
        "019.jpg",
        "020.jpg",
        "021.jpg",
        "022.jpg",
        "023.jpg",
        "024.jpg",
        "025.jpg",
        "026.jpg",
        "027.jpg",
        "028.jpg",
        "029.jpg",
        "030.jpg",
        "031.jpg",
        "032.jpg",
        "033.jpg",
        "034.jpg",
        "035.jpg",
        "036.jpg",
        "037.jpg",
        "038.jpg",
        "039.jpg",
        "040.jpg",
        "041.jpg",
        "042.jpg",
        "043.jpg",
        "044.jpg",
        "045.jpg",
        "046.jpg",
        "047.jpg",
        "048.jpg",
        "049.jpg",
        "050.jpg",
    ];
    bgs=[
        "001.jpg",
        "002.jpg",
        "003.jpg",
        "004.jpg",
        "005.jpg",
        "006.jpg",
        "007.jpg",
        "008.jpg",
        "009.jpg",
        "010.jpg"
    ];
    let filename=bgs[Math.floor(Math.random()*bgs.length)];
    let filename2="login"+filename.substring(0,3)+".png";
    filename="./login/images/bg/"+filename;
    filename2="./login/images/"+filename2;
    let img=new Image();
    let ctx=canvas.getContext("2d");
    img.onload=()=>{
        ctx.drawImage(img,0,0);
        img.style.display="none";
        let imgdata=ctx.getImageData(0,0,640,320);
        let red=0;
        let green=0;
        let blue=0;
        for(let i=0;i<imgdata.data.length;i+=160){
            red+=imgdata.data[i];
            green+=imgdata.data[i+1];
            blue+=imgdata.data[i+2];
        }
        red/=(640*320/40);
        green/=(640*320/40);
        blue/=(640*320/40);
        document.body.style.setProperty("--primary-color","rgba("+red.toString()+","+green.toString()+","+blue.toString()+",1)");
    };
    img.src=filename;
    document.getElementById('bg').style.backgroundImage="url('"+filename+"')";    
    document.getElementById("left").style.backgroundImage="url('"+filename2+"')";
}
document.getElementById("left").onclick=()=>{
    bg();
};