<?php
require "../../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);
?>
<!DOCTYPE html>
<html>
    <head>

        <meta charset="utf-8" />
        <title>蝌蚪寄养所</title>
        <link type="text/css" rel="stylesheet" href="./styles/fontawesome.css" />
        <style>
            body{
                margin:0;
                padding: 0;
                background-color: #7cd8e4;
                width:100%;
                height: 100%;
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
            #bonus{
                position: absolute;
                right: 100px;
                top:10px;
                height: 50px;
                line-height: 50px;
                font-size: 20px;
                color: #fdda00;
                border-radius: 30px;
                user-select: none;
                z-index: 999999;
            }
            #body{
                width: calc(100% - 100px);
                box-shadow: 0 0 10px #CCC;
                padding: 0px 20px 20px 20px;
                border-radius: 20px;
                min-height: 100px;
                margin: 20px auto;
                position: absolute;
                top:80px;
                background-color: #FFF;
                left:0;
                right: 0;
            }
            #cundan{
                width: 100%;
            }
            label{
                background-color: #eee;
                border-radius: 6px;
                display: inline-block;
                line-height: 52px;
                height: 52px;
                width:100%;
                padding-left: 10px;
                user-select: none;
                box-shadow: 0 0 10px #ccc;
                transition: all .3s;
            }
            input{
                margin-left: 10px;
                width:calc(100% - 120px);
                float: right;
                line-height: 50px;
                outline: none;
                font-size: 16px;
                border-radius: 0 6px 6px 0;
                padding:0 20px;
                border: 1px solid #ccc;
                transition: all .3s;
            }
            input:focus{
                border: 2px solid #7cd8e4;
            }
            ul{
                display: block;
                text-align: center;
                list-style: none; 
                padding: 0;
            }
            li{
                display: inline-block;
                height: 50px;
                line-height: 50px;
                font-size: 16px;
                background: #FFF;
                color:#444;
                width: 100px;
                text-align: center;
                user-select: none;
                border-top: 1px solid #7cd8e4;
                border-left: 1px solid #7cd8e4;
                border-bottom: 1px solid #7cd8e4;
                margin: 0;
                font-weight: 600;
                transition: all .3s;
            }
            li:first-child{
                border-radius: 6px 0 0 6px;
                color: #FFF;
            }
            li:last-child{
                border: 1px solid #7cd8e4;
                border-radius: 0 6px 6px 0;
            }
            h2,h4{
                user-select: none;
            }
            button{
                outline: 0;
                border: 2px solid #7cd8e4;
                border-radius: 30px;
                height: 50px;
                padding: 0 20px;
                font-size: 14px;
                font-weight: 600;
                color:#444;
                background-color: #FFF;
                transition: all .3s;
            }
            button:hover{
                background-color: #7cd8e4;
                color: #FFF;
            }
        </style>
    <body>
        <div class="nav">
            <a href="https://qingwa.pro">青蛙</a><span>寄养所</span>
            <div id='bonus' class="fas fa-coins">&nbsp;&nbsp;100</div>
        </div>
        <div id="body">
            <h2>我要寄养</h2>
            <label id='ckje'>寄养数量<input type='text' id='cunkuanjine' /></label>
            <h4>
            选择寄养周期<font color='red'>(未到期取出将扣除50%蝌蚪且不计算利息，请谨慎选择！)</font>
            </h4>
            <ul>
                <li style="background-color: #7cd8e4;">30天</li><li style="background-color: #fff;">90天</li><li style="background-color: #fff;">180天</li><li style="background-color: #fff;">360天</li><li style="background-color: #fff;">活期</li>
            </ul>
            <p id="yujishouyi">预计收益(含寄养数)：0</p>
            <p id="yujishouyi">若寄养30天/90天/180天/360天，每年能生出7.3%/14.6%/29.2%/43.8%的新蝌蚪</p>
            <p style="text-align: center;"><button id="cunkuan">&nbsp;&nbsp;存&nbsp;款&nbsp;&nbsp;</button></p>
            <h2>储蓄明细</h2>
            <table id='cundan'>
                
            </table>
            <div id='top100'></div>
        </div>
        <script text="text/javascript" src="./scripts/ajax.js"></script>
        <script text="text/javascript" src="./scripts/new_alert.js"></script>
        <script text="text/javascript">            
            let calc=()=>{
                let lilv=[0.0002,0.0004,0.0008,0.0012,0];
                let cunkuanjine=document.getElementById('cunkuanjine').value;
                Array.from(document.getElementsByTagName('li')).forEach((e,i)=>{
                    if(e.style.backgroundColor=="#7cd8e4" ||e.style.backgroundColor=="rgb(124, 216, 228)"){
                        document.getElementById('yujishouyi').innerHTML="预计收益(含寄养数量)："+(parseInt(cunkuanjine)+[30,90,180,360,0][i]*lilv[i]*parseInt(cunkuanjine)).toString();
                    }
                });
            };
            let refresh=()=>{
                document.getElementById('bonus').innerHTML='&nbsp;&nbsp;'+get('./bonus.php');
                document.getElementById('cundan').innerHTML="<tr style='text-align:center;font-weight:800;user-select:none;'><td>寄养开始时间</td><td>寄养截止时间</td><td>寄养数量</td><td>得到蝌蚪（含寄养数量）</td><td>操作</td></tr>";
                let cundan=get("cundan.php");
                cundan=JSON.parse(cundan);
                let html='';
                cundan.forEach((e)=>{
                    html+="<tr style='text-align:center;'>";
                    html+="<td>"+e.begin_time+"</td>";
                    html+="<td>"+e.ddl+"</td>";
                    html+="<td>"+e.bonus+"</td>";
                    html+="<td>"+e.bonus2+"</td>";
                    html+="<td><button onclick='qukuan("+e.id+")'>取出蝌蚪</button></td>";
                    html+="</tr>";
                });
                document.getElementById('cundan').innerHTML+=html;
                document.getElementById('top100').innerHTML=get("./top100.php");
            };
            let qukuan=(id)=>{
                let res=get("qu.php?id="+id);
                newAlert(res!="Error."?0:1,res);
                refresh();
            };
            document.getElementById("cunkuan").onclick=()=>{
                let cunkuanjine=document.getElementById('cunkuanjine').value;
                let ddl=0;
                Array.from(document.getElementsByTagName('li')).forEach((e,i)=>{
                    if(e.style.backgroundColor=="#7cd8e4" ||e.style.backgroundColor=="rgb(124, 216, 228)"){
                        ddl=i;
                    }
                });
                document.getElementById('cunkuanjine').value='';
                Array.from(document.getElementsByTagName('li')).forEach(element => {
                    element.style.backgroundColor='#fff';
                    element.style.color='#444';
                });
                document.getElementsByTagName('li')[0].style.backgroundColor='#7cd8e4';
                document.getElementsByTagName('li')[0].style.color='#fff';
                let res=get("cun.php?cun="+cunkuanjine.toString()+"&ddl="+ddl.toString());
                newAlert(res!="Error."?0:1,res);
                refresh();
            };
            window.onload=()=>{
                refresh();
            };
            Array.from(document.getElementsByTagName('li')).forEach(element => {
                element.onclick=()=>{
                    Array.from(document.getElementsByTagName('li')).forEach(e=>{
                        e.style.backgroundColor="#FFF";
                        e.style.color="#444";
                    });
                    element.style.backgroundColor='#7cd8e4';
                    element.style.color='#fff';
                    calc();
                };
            });
            document.getElementById('cunkuanjine').oninput=()=>{
                calc();
            };
        </script>
    </body>
</html>