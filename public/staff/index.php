<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>工作组统计 - 青蛙</title>
  <style>
    #filter{
        width: calc(100% - 60px);
        position: relative;
        top:10px;
        left:50%;
        transform:translateX(-50%);
        border-radius: 6px;
        border: 1px solid #888;
        padding:20px;
    }
    .nexus-username-medal{
        display: none;
    }
  </style>
</head>
<body>
    <div id="filter">
        <label>选择角色<select id="select_roles"></select></label>
        <label>选择日期<input id="bt" type="date" value="2024-03-30"><span id="et"></span></label>
        <button id="submit">提交</button>
    </div>
    <h2>发种统计</h2>
    <table id="torrents">
        <tr>
            <td>
                用户ID
            </td>
            <td>
                用户名
            </td>
            <td>
                发种数量
            </td>
        </tr>
    </table>
    <h2>审种统计</h2>
    <table id="approval">
        <tr>
            <td>
                用户ID
            </td>
            <td>
                用户名
            </td>
            <td>
                审核数量
            </td>
        </tr>
    </table>
</body>

    <script src="../login/scripts/ajax.js"></script>
    <script type="text/javascript">
        function formatDate(date) {
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        let id=(id)=>{return document.getElementById(id)};
        window.onload=()=>{
            let roles=JSON.parse(get('https://demox.qingwa.pro/staff/roles.php'));
            let select_roles=id('select_roles');
            roles.forEach((e,i) => {
                select_roles.innerHTML+="<option value='"+e.id+"'>"+e.name+"</option>";
            });
        };
        id('bt').onchange=()=>{
            let date=new Date(id('bt').value);
            date.setMonth(date.getMonth() + 1);
            id('et').innerHTML='至'+formatDate(date);
        };
        id('submit').onclick=()=>{
            let bt=new Date(id('bt').value).getTime()/1000;
            let selectElement = id("select_roles");
            let selectedValue = selectElement.options[selectElement.selectedIndex].value;
            let json=get("./task.php?role_id="+selectedValue+"&bt="+bt.toString());
            json=JSON.parse(json);
            Array.from(json.torrents).forEach(e=>{
                let uid=e.uid;
                let username=e.username;
                let torrents=e.torrents.length;
                id("torrents").innerHTML+="<tr><td>"+uid+"</td><td>"+username+"</td><td>"+torrents.toString()+"</td></tr>";
            });
            Array.from(json.approval).forEach(e=>{
                let uid=e.uid;
                let username=e.username;
                let torrents=e.torrents.length;
                id("approval").innerHTML+="<tr><td>"+uid+"</td><td>"+username+"</td><td>"+torrents.toString()+"</td></tr>";
            });
        };
    </script>
</html>