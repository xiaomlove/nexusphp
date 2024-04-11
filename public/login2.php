<?php
function getCaptcha(){
    $url="https://new.qingwa.pro/login.php";
    $curl=curl_init();
    curl_setopt($curl,CURLOPT_TIMEOUT,10);
    curl_setopt($curl,CURLOPT_URL,$url);
    curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,false);
    curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,false);
    curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
    $ret=curl_exec($curl);
    //die($ret);
    if($ret){
        curl_close($curl);
        $a=strpos($ret,"<img src=\"image.php?action=regimage&amp;imagehash=")+50;
        $ret=substr($ret,$a,32);
        return $ret;
    }else{
        $ret=curl_errno($curl);
        curl_close($curl);
        return "123";
    }
    
}
?>
<!DOCTYPE html>
<html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no" />
        <meta charset="utf-8" />
        <title>LOGIN</title>
        <link type="text/css" rel="stylesheet" href="./login/styles/fontawesome.css" />
        <link type="text/css" rel="stylesheet" href="./login/styles/login.css" />
    </head>
    <body>
        <div id="bg"></div>
        <div id="bg2"></div>
        <div class='login'>
            <div id="left"></div>
            <div id="right">
                <h1>Login</h1>
                <form method="post" action="takelogin.php">
                <div class='input'><span class="fas fa-user" style="color:#888;font-size:20px;padding:10px;"></span><input name="username" id="username" type="text" placeholder="USERNAME"/></div>
                <div class='input'><span class="fas fa-key" style="color:#888;font-size:20px;padding:10px;"></span><input name="password" id="password" type="password" placeholder="PASSWORD"/></div>
                <div class='input'><img id='captchaimg' /><input name="imagestring" id="captcha" type="text" placeholder="CAPTCHA"/></div>
                <h6><span class="fas fa-info-circle"></span>&nbsp;&nbsp;<a href='recover.php'>FORGET YOUR PASSWORD?</a></h6>
                <input type='hidden' name='imagehash' id='imagehash' />
                <input type='hidden' name='two_step_code' value='' />
                <input type='hidden' name='secret' value='' />
                <input id="login" value='LOGIN' type='button'/>
                </form>
            </div>
        </div>
        <script text="text/javascript" src="./login/scripts/ajax.js"></script>
        <script text="text/javascript" src="./login/scripts/new_alert.js"></script>
        <script text="text/javascript" src="./login/scripts/login.js"></script>
        <script text="text/javascript">
            window.onload=()=>{
                bg();
                let captchaurl="<?php echo getCaptcha();?>";
                console.log(captchaurl);
                document.getElementById('captchaimg').setAttribute('src',"https://new.qingwa.pro/image.php?action=regimage&imagehash="+captchaurl);
                document.getElementById('imagehash').setAttribute('value',captchaurl)
            }
        </script>
    </body>
</html>