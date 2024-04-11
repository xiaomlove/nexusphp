<?php
require "../../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);
?>
<!DOCTYPE html>
<html>
    <head>

        <meta charset="utf-8" />
        <title>大转盘 - 青蛙</title>
        <link type="text/css" rel="stylesheet" href="./styles/fontawesome.css" />
        <style>
            body{
                margin:0;
                padding: 0;
                background-color: #7cd8e4;
                width:100%;
                height: 100%;
                /*background: url(./bg.jpg);
                background-size: cover;
                background-repeat: no-repeat;
                background-attachment: fixed;
                background-position: center;*/
            }
            .wheel{
                margin:120px auto;
                width:400px;
                height:400px;
                position: relative;
                border-radius: 50%;
                border:30px solid #ff5e5c;
                background-color: #ff8686;
                overflow: hidden;
                user-select: none;
                box-shadow: 0 0 20px #666;
                transition: transform 3s ease-in-out;
            }
            .prize{
                width:400px;
                height:400px;
                position: absolute;
                left:50%;
                top:-50%;
                background-color: #FFF;
                transform-origin: 0 100%;
                color:#FFF;
                font-size: 30px;
                flex-direction: column;
            }
            .prize:nth-child(1){
                transform: rotate(0deg) skewY(-30deg);
                background-color: #fd686b;
            }
            .prize:nth-child(2){
                transform: rotate(60deg) skewY(-30deg);
                background-color: #ff8686;
            }
            .prize:nth-child(3){
                transform: rotate(120deg) skewY(-30deg);
                background-color: #fd686b;
            }
            .prize:nth-child(4){
                transform: rotate(180deg) skewY(-30deg);
                background-color: #ff8686;
            }
            .prize:nth-child(5){
                transform: rotate(240deg) skewY(-30deg);
                background-color: #fd686b;
            }
            .prize:nth-child(6){
                transform: rotate(300deg) skewY(-30deg);
                background-color: #ff8686;
            }
            .prize>div{
                position: absolute; 
                top:280px; 
                padding: 20px;
                transform: rotate(48deg);
            }
            .btn{
                background-color: #fdda00;
                width: 120px;
                height: 120px;
                font-size: 60px;
                color: #ff8686;
                position: absolute;
                border-radius: 50%;
                left: 50%;
                top:275px;
                transform: translateX(-50%);
                text-align: center;
                line-height: 120px;
                font-weight: 800;
                border: 16px solid #feee8d;
                transition: all .3s;
                user-select: none;
            }
            .btn:hover{
                transform: translateX(-50%) scale(1.05);
            }
            .btn:active{
                transform: translateX(-50%) scale(1.02);
            }
            .nav{
                position: absolute;
                top:0;
                left:0;
                right: 0;
                height: 60px;
                padding-left: 20px;
                padding-top: 30px;
                background-color: rgba(255,255,255,0);
            }
            .nav>a{
                border-radius: 6px 0 0 6px;
                border:1px solid #f45d61;
                color:#f45d61;
                font-size: 20px;
                text-decoration: none;
                outline: none;
                padding:10px 20px;
                user-select: none;
            }
            .nav>span{
                background-color: #f45d61;
                border:1px solid #f45d61;
                color:#fff;
                padding:10px 20px;
                font-size: 20px;
                border-radius: 0 6px 6px 0;
                user-select: none;
            }

            .triangle {
                width: 0;
                height: 0;
                border-left: 30px solid transparent;  
                border-right: 30px solid transparent;
                border-top: 30px solid transparent;
                border-bottom: 80px solid #fdda00; 
                position: absolute;
                top:-100px;
                left:30px;
            }
            #bonus{
                position: fixed;
                right: 100px;
                top:10px;
                height: 50px;
                line-height: 50px;
                font-size: 20px;
                color: #fdda00;
                border-radius: 30px;
                user-select: none;
                z-index:999999;
            }
            .rules{
                margin: 0 auto;
                position: absolute;
                left:0;
                right:0;
                margin-top:-50px;
                font-size: 20px;
                background-color: #f45d61;
                width: 200px;
                text-align: center;
                line-height: 50px;
                border-radius: 16px;
                font-weight: 800;
                color: #FFF;
                z-index: 9999;
                box-shadow: 0 0 20px #666;
            }
            .rules2{
                z-index: 999;
                margin: 0 auto;
                position: absolute;
                left:0;
                right:0;
                margin-top:-20px;
                font-size: 16px;
                background-color: #fff;
                width: calc(100% - 100px);
                text-align: left;
                line-height: 50px;
                border-radius: 16px;
                font-weight: 600;
                color: #444;
                padding: 20px;
                box-shadow: 0 0 20px #666;
            }
            .cloud {
                position: absolute;
                width: 100px;
                height: 40px;
                background: #fff;
                border-radius: 100px 100px 40px 40px;
            }
            .cloud>div{
                background: #fff;
                border-radius: 50%;
                margin-top: -25px;
                width:50px;
                height:50px;
                margin-left:25px;
            }
        </style>
    <body>
        <div class="cloud" style="top:100px;right:60px;"><div></div></div>
        <div class="cloud" style="top:60px;right:150px;transform: scale(.8);"><div></div></div>
        <div class="cloud" style="top:120px;left:60px;"><div></div></div>
        <div class="nav">
            <a href="https://qingwa.pro">青蛙</a><span>大转盘</span>
            <div id='bonus' class="fas fa-coins">&nbsp;&nbsp;100</div>
        </div>
        
        <div class="wheel">
            <div class="prize"><div>一等奖</div></div>
            <div class="prize"><div>二等奖</div></div>
            <div class="prize"><div>三等奖</div></div>
            <div class="prize"><div>四等奖</div></div>
            <div class="prize"><div>五等奖</div></div>
            <div class="prize"><div>六等奖</div></div>
            
        </div>
        <div class="btn"><div class="triangle"></div>呱</div>
        <div class="rules">规则</div>
        <div class="rules2">【一等奖】1TB上传<br />【二等奖】100000蝌蚪<br />【三等奖】10G上传<br />【四等奖】1邀请<br />【五等奖】1G上传<br />【六等奖】100蝌蚪<br />每次抽奖消耗1000蝌蚪。</div>
        <script text="text/javascript" src="./scripts/ajax.js"></script>
        <script text="text/javascript" src="./scripts/new_alert.js"></script>
        <script text="text/javascript">
            window.onload=()=>{
                document.getElementById('bonus').innerHTML='&nbsp;&nbsp;'+get('./gua.php');
            };
            let wa=(n)=>{
                n=parseInt(Math.random()*6)*360-n*60-30;
                document.getElementsByClassName('wheel')[0].style.transform="rotate("+n.toString()+"deg)";
            };
            document.getElementsByClassName('btn')[0].onclick=()=>{
                document.getElementsByClassName('btn')[0].style.pointerEvents='none';
                let res=get('./gua.php?gua=gua');
                switch(res){
                    case 'Error.':
                        newAlert(1,"Error!");
                        break;
                    case '蝌蚪不足！':
                        newAlert(1,"蝌蚪不足");
                        break;
                    default:
                        wa(parseInt(res));
                        a=['获得一等奖 - 1TB上传','获得二等奖 - 100000蝌蚪','获得三等奖 - 10GB上传','获得四等奖 - 1邀请','获得五等奖 - 1GB上传','获得六等奖 - 100蝌蚪'];
                        setTimeout(()=>{
                            newAlert(0,a[parseInt(res)]);
                            document.getElementsByClassName('btn')[0].style.pointerEvents='auto';
                        },3000)
                }
                document.getElementById('bonus').innerHTML='&nbsp;&nbsp;'+get('./gua.php');
            };
        </script>
    </body>
</html>