<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FaqTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('faq')->delete();
        
        \DB::table('faq')->insert(array (
            0 => 
            array (
                'id' => 1,
                'link_id' => 1,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '站点信息',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 1,
            ),
            1 => 
            array (
                'id' => 2,
                'link_id' => 2,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '用户信息',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 2,
            ),
            2 => 
            array (
                'id' => 3,
                'link_id' => 3,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '数据统计',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 3,
            ),
            3 => 
            array (
                'id' => 4,
                'link_id' => 4,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '发布',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 4,
            ),
            4 => 
            array (
                'id' => 5,
                'link_id' => 5,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '下载',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 5,
            ),
            5 => 
            array (
                'id' => 6,
                'link_id' => 6,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '我该怎样提升下载速度？',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 6,
            ),
            6 => 
            array (
                'id' => 7,
                'link_id' => 7,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '我的网络提供商（ISP）使用了透明代理。我应该怎么做？',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 7,
            ),
            7 => 
            array (
                'id' => 8,
                'link_id' => 8,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '为什么我连不上？难道我被网站屏蔽了？',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 8,
            ),
            8 => 
            array (
                'id' => 9,
                'link_id' => 9,
                'lang_id' => 25,
                'type' => 'categ',
                'question' => '如果从这里找不到我的问题的解答，我该怎么办？',
                'answer' => '',
                'flag' => 1,
                'categ' => 0,
                'order' => 9,
            ),
            9 => 
            array (
                'id' => 10,
                'link_id' => 10,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '到底什么是BT（Bittorrent）？我要如何才能弄到这类程序？',
                'answer' => '参看<a class="faqlink" href="http://www.btfaq.com/">Brian的BitTorrent FAQ及指导</a>。',
                'flag' => 1,
                'categ' => 1,
                'order' => 1,
            ),
            10 => 
            array (
                'id' => 11,
                'link_id' => 11,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我们捐出来的钱上哪儿去了？',
                'answer' => '所有捐助的钱用于维护服务器的费用。',
                'flag' => 1,
                'categ' => 1,
                'order' => 2,
            ),
            11 => 
            array (
                'id' => 12,
                'link_id' => 12,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我从哪里可以搞到源代码的拷贝？',
                'answer' => '这个Tracker使用的是NexusPHP的代码。代码获取事宜，请看<a class="faqlink" href="aboutnexus.php">这里</a>。<br />
NexusPHP的代码是根据TBSource的源码改写的。TBDev网站，Tracker源代码以及社区论坛都在这里：<a class="faqlink" href="http://www.tbdev.net/">TBDev.net</a>。',
                'flag' => 1,
                'categ' => 1,
                'order' => 3,
            ),
            12 => 
            array (
                'id' => 13,
                'link_id' => 13,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我注册了一个账号但是却没有收到验证邮件！',
                'answer' => '这很有可能是Tracker所在的服务器网络出现了问题。你可以填写<a href="confirm_resend.php" class="faqlink">这个表格</a>让服务器重发验证邮件。<br />
通常没有得到确认的注册帐户会在24小时后被删除，所以你可以在第二天重试。需要注意的是，如果你第一次没有收到确认信，第二次有很大的可能仍然无法收到确认信，所以换一个E-mail地址是一个不错的主意。',
                'flag' => 1,
                'categ' => 2,
                'order' => 1,
            ),
            13 => 
            array (
                'id' => 14,
                'link_id' => 14,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我忘记了我的用户名/密码了，能帮我找回来么？',
                'answer' => '请填写<a class="faqlink" href="recover.php">这个表单</a>，我们将把登录详情发还给你。',
                'flag' => 1,
                'categ' => 2,
                'order' => 2,
            ),
            14 => 
            array (
                'id' => 15,
                'link_id' => 15,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我可以给我的账号改个名字么？',
                'answer' => '我们并不支持给账号改名。',
                'flag' => 1,
                'categ' => 2,
                'order' => 3,
            ),
            15 => 
            array (
                'id' => 16,
                'link_id' => 16,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '你可以删除一个（已确认的）账号么？',
                'answer' => '我们一般不提供该类服务，所以不要请求删除任何一个被你邀请的帐号。',
                'flag' => 1,
                'categ' => 2,
                'order' => 4,
            ),
            16 => 
            array (
                'id' => 17,
                'link_id' => 17,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '那么，什么是我的分享率（Ratio）？',
                'answer' => '就在页面功能分区的下面。<br />
<br />
<img src="pic/ratio.png" alt="ratio" />
<br />
<br />
区分你的整体分享率和独立分享率是很重要的。整体分享率关注的是自从你加入站点以来，账号的整体上传与下载量。而独立分享率则针对每一个你正在下载或做种的文件。
<br />
<br />
你可能看到两种符号来代替数字："Inf."，这个是无限（Infinity）的缩写，意思是你的下载量为0字节，而上传量则是任意一个非零的值(上传量/下载量=无穷大)；"---"，应该被视为“不可用”，这说明了你的下载量和上传量都是0字节。(上传量/下载量=0/0 这是一个不确定的量)。
',
                'flag' => 1,
                'categ' => 2,
                'order' => 5,
            ),
            17 => 
            array (
                'id' => 18,
                'link_id' => 18,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我的IP地址显示在我的个人信息页面上？',
                'answer' => '只有你自己和网站Moderator及以上的管理员可以看到你的IP地址和Email。普通用户是看不到这些信息的。',
                'flag' => 1,
                'categ' => 2,
                'order' => 6,
            ),
            18 => 
            array (
                'id' => 21,
                'link_id' => 21,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我的“可连接”是“否”？（以及为什么我需要关注这个问题？）',
                'answer' => 'Tracker服务器认为你在防火墙后，或者在NAT桥接后，并且无法接收其他Peer的连接请求。
<br />
<br />
这意味着其它的大批Peer无法连接到你，只能由你连接到他们。更糟糕的情况是，如果两个Peer都处于这样的状态，他们将完全无法连接到对方。这对于整体速度有着非常不利的影响。
<br />
<br />
对于这个问题，有以下解决方式：对于防火墙，打开用于接收连接的端口（即你在BT客户端中定义的端口）；对于NAT，你需要配置NAT服务器使用Basic NAT方式而不是NAPT（不同的路由有着不同的运行方式。翻阅你的路由文档或客服论坛。你也可以在<a class="faqlink" href="http://portforward.com/">PortForward</a>找到关于此问题的大量信息）。





',
                'flag' => 1,
                'categ' => 2,
                'order' => 9,
            ),
            19 => 
            array (
                'id' => 22,
                'link_id' => 22,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '不同的用户等级代表了什么含义？',
                'answer' => '<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="200" valign="top">&nbsp; <b class="Peasant_Name">Peasant</b></td>
<td class="embedded" width="5">&nbsp;</td>
<td class="embedded"> 被降级的用户，他们有30天时间来提升分享率，否则他们会被踢。不能发表趣味盒内容；不能申请友情链接；不能上传字幕。
</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">新用户的默认级别。只能在每周六中午12点至每周日晚上11点59分发布种子。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="PowerUser_Name">Power User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到一个邀请名额；可以直接发布种子；可以查看NFO文档；可以查看用户列表；可以请求续种； 可以发送邀请； 可以查看排行榜；可以查看其它用户的种子历史(如果用户隐私等级未设置为"强")； 可以删除自己上传的字幕。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="EliteUser_Name">Elite User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded"><b class="EliteUser_Name">Elite User</b>及以上用户封存账号后不会被删除。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="CrazyUser_Name">Crazy User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到两个邀请名额；可以在做种/下载/发布的时候选择匿名模式。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="InsaneUser_Name">Insane User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">可以查看普通日志。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VeteranUser_Name">Veteran User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到三个邀请名额；可以查看其它用户的评论、帖子历史。<b class="VeteranUser_Name">Veteran User</b>及以上用户会永远保留账号。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="ExtremeUser_Name">Extreme User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">可以更新过期的外部信息；可以查看Extreme User论坛。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="UltimateUser_Name">Ultimate User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到五个邀请名额。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="NexusMaster_Name">Nexus Master</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到十个邀请名额。</td>
</tr>
<tr>
<td class="embedded"  valign="top">&nbsp; <img class="star" src="pic/trans.gif" alt="Star" /></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">为网站捐款的主。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VIP_Name">贵宾(VIP)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">和<b class="NexusMaster_Name">Nexus Master</b>拥有相同权限并被认为是精英成员。免除自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">其它</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">自定义等级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Retiree_Name">养老族(Retiree)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">退休后的管理组成员。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Uploader_Name">发布员(Uploader)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">专注的发布者。免除自动降级；可以查看匿名用户的真实身份。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Moderator_Name">总版主(Moderator)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">可以查看管理组信箱、举报信箱；管理趣味盒内容、投票内容；可以编辑或删除任何发布的种子；可以管理候选；可以管理论坛帖子、用户评论；可以查看机密日志；可以删除任何字幕；可以管理日志中的代码、史册；可以查看用户的邀请记录；可以管理用户帐号的一般信息。<b>不能</b>管理友情链接、最近消息、论坛版块；<b>不能</b>将种子设为置顶或促销；<b>不能</b>查看用户IP或Email等机密信息；不能删除账号。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Administrator_Name">管理员(Administrator)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">除了不能改变站点设定、管理捐赠外，可以做任何事。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="SysOp_Name">维护开发员(Sysop)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">网站开发/维护人员，可以改变站点设定，不能管理捐赠。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="StaffLeader_Name">主管(Staff Leader)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">网站主管，可以做任何事。</td>
</tr>
</table>',
                'flag' => 1,
                'categ' => 2,
                'order' => 10,
            ),
            20 => 
            array (
                'id' => 23,
                'link_id' => 23,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '提升和降级又是怎样被执行的呢？',
                'answer' => '<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="200" valign="top">&nbsp; <b class="Peasant_Name">Peasant</b></td>
<td class="embedded" width="5">&nbsp;</td>
<td class="embedded" valign="top">当以下情况时将被自动降至本级：<br />
1.如果你已经下载了超过50GB，你应该有大于0.4的分享率。<br />
2.如果你已经下载了超过100GB，你应该有大于0.5的分享率。<br />
3.如果你已经下载了超过200GB，你应该有大于0.6的分享率。<br />
4.如果你已经下载了超过400GB，你应该有大于0.7的分享率。<br />
5.如果你已经下载了超过800GB，你应该有大于0.8的分享率。</td>
</tr>

<tr>
<td class="embedded" valign="top">&nbsp; <b class="PowerUser_Name">Power User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少4周，并且下载至少50G，分享率大于1.05。<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于0.95，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="EliteUser_Name">Elite User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少8周，并且下载至少120G，分享率大于1.55。
<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于1.45，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="CrazyUser_Name">Crazy User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少15周，并且下载至少300G，分享率大于2.05。
<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于1.95，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="InsaneUser_Name">Insane User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少25周，并且下载至少500G，分享率大于2.55。<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于2.45，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VeteranUser_Name">Veteran User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少40周，并且下载至少750G，分享率大于3.05。<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于2.95，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="ExtremeUser_Name">Extreme User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少60周，并且下载至少1TB，分享率大于3.55。<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于3.45，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="UltimateUser_Name">Ultimate User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少80周，并且下载至少1.5TB，分享率大于4.05。<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于3.95，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="NexusMaster_Name">Nexus Master</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必须注册至少100周，并且下载至少3TB，分享率大于4.55。<br />
当条件符合时将被自动提升。注意，无论何时，如果你的分享率低于4.45，你将自动降级。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <img class="star" src="pic/trans.gif" alt="Star" /></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">只需捐款，详见<a class="faqlink" href="donate.php">这里</a>。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VIP_Name">贵宾(VIP)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">由管理员仔细斟酌后分配给他们认为对于站点某方面有特殊贡献的用户。<br />
（任何索取贵宾等级的要求将被自动无视）</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">其它</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">用户使用魔力值兑换，或由管理员仔细斟酌后授权。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Retiree_Name">养老族(Retiree)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">由管理员授予。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Uploader_Name">发布员(Uploader)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">由管理员分配(参见\'发布\'部分以了解详情)。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Moderator_Name">总版主(Moderator)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">无需多问，我们会找到你的！</td>
</tr>
</table>',
                'flag' => 1,
                'categ' => 2,
                'order' => 11,
            ),
            21 => 
            array (
                'id' => 25,
                'link_id' => 25,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我的朋友不能加入？',
                'answer' => '用户数目有所限制（你可以查看“首页-&gt;站点数据-&gt;上限”）。当达到那个数目时，我们会停止接受新成员。<br />
由于非活跃用户(长期不使用账号的用户)引起账号资源浪费，因此我们将清理该部分用户，为其他想加入用户腾出空间。<br />
关于非活跃用户清理规则，参考<a class="faqlink" href="rules.php">规则</a>。',
                'flag' => 1,
                'categ' => 2,
                'order' => 13,
            ),
            22 => 
            array (
                'id' => 26,
                'link_id' => 26,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '怎样自定义头像？',
                'answer' => '首先，找一个你喜欢的图片，当然了它不能违反我们的<a class="faqlink" href="rules.php">规则</a>。然后你要找一个存放它的地方，比方说我们自己的<a class="faqlink" href="bitbucket-upload.php">上传器</a>。出于服务器负载的考虑，我们更希望你将图片上传到别的网站，然后将其URL粘贴到你的<a class="faqlink" href="usercp.php?action=personal">控制面板</a>的头像URL部分。 <br />
<br />
请不要为了仅仅测试头像而发帖。如果一切顺利，你将在你的详情页面看到它。',
                'flag' => 1,
                'categ' => 2,
                'order' => 14,
            ),
            23 => 
            array (
                'id' => 27,
                'link_id' => 27,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '最常见的数据未能得到更新的原因',
                'answer' => '<ul>
<li>服务器过载/未响应。只要尝试着保持会话直到服务器恢复响应(不推荐连续手动刷新，这样会加重服务器负载)。</li>
<li>你正在使用不稳定的客户端。如果你想要使用测试版或者CVS版本，你需要自己承担由此带来的风险。</li>
</ul>',
                'flag' => 1,
                'categ' => 3,
                'order' => 1,
            ),
            24 => 
            array (
                'id' => 28,
                'link_id' => 28,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '最佳的尝试',
                'answer' => '<ul>
<li>如果你当前正下载/上传的种子并不在你的用户详情页面中，你可以等一会或者强制进行手动更新。</li>
<li>确保你正确地关闭了客户端软件，否则Tracker服务器无法收到"event=completed"的信号。</li>
<li>如果Tracker服务器挂了，不要停止上传。只要在你退出客户端之前服务器恢复工作，所有的数据都会得到正确地更新。</li>
</ul>',
                'flag' => 1,
                'categ' => 3,
                'order' => 2,
            ),
            25 => 
            array (
                'id' => 29,
                'link_id' => 29,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我可以任意选择Bittorrent客户端软件么？',
                'answer' => '根据<a class="faqlink" href="aboutnexus.php">NexusPHP</a>对常见BitTorrent客户端的测试，目前本站Tracker<b>只允许</b>使用以下的BitTorrent客户端软件。<br />
<a class="faqlink" href="aboutnexus.php">NexusPHP</a>的测试报告可<a class="faqlink" href="http://www.nexusphp.com/wiki/%E5%AE%A2%E6%88%B7%E7%AB%AF%E6%B5%8B%E8%AF%95%E6%8A%A5%E5%91%8A">在此查看</a>。
<br />
<b>Windows:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2及后续版本</li>
<li><a class="faqlink" href="http://www.utorrent.com">uTorrent</a>: 1.6.1, 1.7.5, 1.7.6, 1.7.7, 1.8Beta(Build 10364), 2.0(Build 17624)及后续版本</li>
<li><a class="faqlink" href="http://www.bittorrent.com">BitTorrent</a>: 6.0.1, 6.0.2, 6.0.3及后续版本</li>
<li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6及后续版本</li>
<li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0及后续版本</li>
</ul>
<b>Linux:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2及后续版本</li>
<li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6及后续版本</li>
<li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0及后续版本</li>
<li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21及后续版本</li>
<li><a class="faqlink" href="http://libtorrent.rakshasa.no">rTorrent</a>: 0.8.0（配合libtorrent 0.12.0或后续版本）及后续版本</li>
<li><a class="faqlink" href="http://www.rahul.net/dholmes/ctorrent/">Enhanced CTorrent</a>: 3.3.2及后续版本</li>
</ul>
<b>MacOS X:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2及后续版本</li>
<li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21及后续版本</li>
<li><a class="faqlink" href="http://sourceforge.net/projects/bitrocket/">BitRocket</a>: 0.3.3(32)及后续版本</li>
</ul>
<b>Symbian (仅供测试):</b>
<ul>
<li><a class="faqlink" href="http://amorg.aut.bme.hu/projects/symtorrent">SymTorrent</a>: 1.41及后续版本</li>
</ul>
<br />

<b>以上客户端在https支持方面</b>
<ul>
<li>uTorrent 1.61: 无法准确解析https的tracker, 同时在使用会将自己标识为uTorrent 1.5</li>
<li>Rufus: 没有https支持, 并且已经几年没有继续开发</li>
<li>rtorrent: 需要手工设置SSL证书, 详细信息请自行查阅其官方网站说明</li>
</ul>

<br />

同时请尽量避免使用处于测试期的客户端软件, 如uTorrent 1.8.0B。

为了从本站得到最好的下载体验,我们高度推荐<a class="faqlink" href="http://www.utorrent.com/download.php">uTorrent</a> 以及<a class="faqlink" href="http://azureus.sourceforge.net/download.php">Azureus</a>这两个软件的最新稳定版。',
                'flag' => 1,
                'categ' => 5,
                'order' => 3,
            ),
            26 => 
            array (
                'id' => 30,
                'link_id' => 30,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我正在下载/做种的一个种子会在我的详情页面中被列出多次？',
            'answer' => '如果因为某些原因(比方说死机，客户端失去响应)你的客户端非正常退出并且你又重新打开它，它会被分配到一个全新的Peer ID, 这样它显示为一个全新的种子。而旧的种子将永远不会收到“event=completed”或“event=stopped”的信号，因此将一直被列出直到Tracker清理超时的Peer。 无视了它就可以了，它最后会消失的。',
                'flag' => 1,
                'categ' => 3,
                'order' => 4,
            ),
            27 => 
            array (
                'id' => 31,
                'link_id' => 31,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我已经完成/取消的种子仍然在我的详情页面里？',
                'answer' => '有一些客户端，比如说TorrentStorm和Nova Torrent，在取消或者完成一个任务的时候不能向Tracker服务器正确发送信号。在那样的情况下，Tracker服务器将一直保持等待信号的状态（因此会一直列出种子的状态为做种/下载的状态）直到Tracker清理超时的Peer。无视了它就可以了，它最后会消失的。',
                'flag' => 1,
                'categ' => 3,
                'order' => 5,
            ),
            28 => 
            array (
                'id' => 33,
                'link_id' => 33,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '多IP（我可以从不同的电脑登录/下载吗？）',
                'answer' => '是的，目前Tracker服务器支持单个用户从任意个数的IP地址同时访问/下载。<br />
然而，对于单种是有限制的。对于每个种子，最多允许3个做种的连接，最多只允许1个下载的连接（这意味着，对于某个种子你一次只能在一个地方下载）。',
                'flag' => 1,
                'categ' => 3,
                'order' => 7,
            ),
            29 => 
            array (
                'id' => 36,
                'link_id' => 36,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我不能发布种子？',
                'answer' => '请看<a class="faqlink" href="rules.php">规则</a>。',
                'flag' => 1,
                'categ' => 4,
                'order' => 1,
            ),
            30 => 
            array (
                'id' => 37,
                'link_id' => 37,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我需要满足哪些条件才能加入发布小组呢？',
                'answer' => '你所必须满足的条件：
<ul>
<li>具有稳定的资源来源</li>
<li>平均每周资源发布数量不少于5个</li>
</ul>
你必须确保发布的文件符合以下特征:
<ul>
<li>不超过7天之前</li>
<li>你必须要有做种的能力，或者可以确保至少有效供种24小时。</li>
<li>你需要有至少达到2MBit的上传带宽。</li>
</ul>
如果你认为你符合以上条件，那么不要犹豫，<a class="faqlink" href="contactstaff.php">联系管理组</a>吧。<br />
<b>记住！</b> 仔细填写你的申请！确保申请中包含你的上传速度以及你计划发布的内容的类型。<br />
只有仔细填写并且经过慎重考虑的申请才会纳入我们的视野。',
                'flag' => 1,
                'categ' => 4,
                'order' => 2,
            ),
            31 => 
            array (
                'id' => 38,
                'link_id' => 38,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我可以发布你们这里的种子到别的Tracker服务器么？',
                'answer' => '不能。我们是一个封闭的、限制用户数的社区。只有注册用户才能够使用我们的Tracker。将我们的种子文件发布到其他Tracker服务器是徒劳的，因为绝大多数试图下载这些文件的人将无法连接到我们的服务器。将我们的种子发布到其他Tracker只让那里的用户恼怒（因为无法下载）并且对我们产生敌对情绪，所以这种行为是我们是不能容忍的。<br />
<br />
如果其他网站的管理员向我们举报我们的种子被发布在他们的站点上，对此负责的用户将因此被移出我们的社区。<br />
<br />
但是对于从我们这里下载得到的文件，你可以随意操控它们（发布者明确说明为<b>独占</b>的资源除外）。你可以制作另一个种子，写入其他Tracker的地址，并发布到你喜欢的站点上。',
                'flag' => 1,
                'categ' => 4,
                'order' => 3,
            ),
            32 => 
            array (
                'id' => 39,
                'link_id' => 39,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '下载来的文件该怎么打开？',
                'answer' => '在这份<a class="faqlink" href="formats.php">指引</a>里面找找看吧。',
                'flag' => 1,
                'categ' => 5,
                'order' => 1,
            ),
            33 => 
            array (
                'id' => 40,
                'link_id' => 40,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我下载了一部电影，但是不明白CAM/TS/TC/SCR是什么意思?',
                'answer' => '在这份<a class="faqlink" href="videoformats.php">指引</a>里面找找看吧。',
                'flag' => 1,
                'categ' => 5,
                'order' => 2,
            ),
            34 => 
            array (
                'id' => 41,
                'link_id' => 41,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么一个可用的种子忽然就消失了？',
                'answer' => '以下原因都会导致这个现象的出现:<br />
(<b>1</b>) 按照<a class="faqlink" href="rules.php">网站规则</a>，这个种子不符合规则已被删除。
<br />
(<b>2</b>) 发布者因为文件存在问题而删除了它。一般来说，发布者会发布一个替代的版本的。<br />
(<b>3</b>) 在长时间断种后种子被自动删除了。',
                'flag' => 1,
                'categ' => 5,
                'order' => 3,
            ),
            35 => 
            array (
                'id' => 42,
                'link_id' => 42,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '我该怎样续传文件或者给一个文件续种呢？',
                'answer' => '打开扩展名为.torrent的文件，当你的客户端软件询问保存的目录时，选择已经存在的文件存放的目录，它就能够开始续传/续种了。',
                'flag' => 1,
                'categ' => 5,
                'order' => 4,
            ),
            36 => 
            array (
                'id' => 43,
                'link_id' => 43,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我的下载有时候停在99%?',
                'answer' => '你所下载到的片段越多，寻找拥有你所缺少的片段的同伴就将变得越困难。这就是为什么有时候在下载即将完成的时候，速度会变得非常慢，甚至完全停止了。只要耐心等候，或早或晚剩下的部分一定能够下载到的。',
                'flag' => 1,
                'categ' => 5,
                'order' => 5,
            ),
            37 => 
            array (
                'id' => 44,
                'link_id' => 44,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '"部分哈希验证出错"这条信息是什么意思？',
                'answer' => 'BT的客户端会检查它所收到的数据的完整性。如果某一个片段在验证中出错，那么这个片段将被重新下载。偶尔的哈希验证错误是非常常见的现象，不用担心。<br />
<br />
有些客户端有屏蔽向你发送错误数据的其他客户端的功能。如果有这个选项，最好打开它。因为这样可以保证如果某个同伴经常向你发送错误的数据时，它将会被屏蔽。',
                'flag' => 1,
                'categ' => 5,
                'order' => 6,
            ),
            38 => 
            array (
                'id' => 45,
                'link_id' => 45,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '100MB大小的种子，我怎么下了120MB的东西回来?',
                'answer' => '参见哈希验证失败的那个问题。如果你的客户端收到了错误的数据，那么它将会重新下载这一部分，因此总下载量有可能比种子大小略微大一些。确保“屏蔽发送错误数据的客户端”的那个选项被打开了，这样可以减少额外的下载。',
                'flag' => 1,
                'categ' => 5,
                'order' => 7,
            ),
            39 => 
            array (
                'id' => 46,
                'link_id' => 46,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么我的客户端的Tracker服务器状态出现"Your ratio is too low! You need to wait xx h to start"的错误？',
                'answer' => '在<b>新</b>种子被上传到Tracker服务器之后，有一些用户必须等一些时间才能开始下载。<br>
这个延迟只会影响那些分享率较低且下载量大于10G的用户。<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.4</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延迟</td>
<td class="embedded" width="100">24小时</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.5</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延迟</td>
<td class="embedded" width="100">12小时</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.6</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延迟</td>
<td class="embedded" width="100">6小时</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.8</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延迟</td>
<td class="embedded" width="100">3小时</td>
</tr>
</table>',
                'flag' => 0,
                'categ' => 5,
                'order' => 8,
            ),
            40 => 
            array (
                'id' => 47,
                'link_id' => 47,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '为什么发生了这个错误？ "Port xxxx is blacklisted"?',
            'answer' => '你的客户端向Tracker服务器报告它正在使用某个默认的BT协议端口(6881-6889)或是任何其他常见P2P端口来作为连接端口。<br />
<br />
本站不允许这些通常被P2P协议默认使用的端口。原因是目前ISP常常对这些端口进行限速。<br />
<br />
被屏蔽的端口如下：<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">Direct Connect</td>
<td class="embedded" width="100">411 - 413</td>
</tr>
<tr>
<td class="embedded" width="100">BitTorrent</td>
<td class="embedded" width="100">6881 - 6889</td>
</tr>
<tr>
<td class="embedded" width="100">Kazza</td>
<td class="embedded" width="100">1214</td>
</tr>
<tr>
<td class="embedded" width="100">Gnutella</td>
<td class="embedded" width="100">6346 - 6347</td>
</tr>
<tr>
<td class="embedded" width="100">Emule</td>
<td class="embedded" width="100">4662</td>
</tr>
<tr>
<td class="embedded" width="100">WinMX</td>
<td class="embedded" width="100">6699</td>
</tr>
</table>
<br />
要使用我们的Tracker服务器，你必须配置你的客户端使用未在上面列出的端口范围(从49152到65535都是不错的选择，参看<a class="faqlink" href="http://www.iana.org/assignments/port-numbers">IANA</a>)。注意某些客户端，如Azureus 2.0.7.0或更高版本，对所有的种子都使用同一端口。而其他大多数客户端为每一个种子开放一个端口，你在选择端口范围应考虑到这个问题（一般来说端口范围小于10。设置一个过大的范围并不一定有好处，而且可能有安全隐患)。 <br />
<br />
这些端口是用于同伴间通信的，而非用于客户端连接到Tracker服务器。因此这个改动并不会影响你使用其他Tracker服务器（事实上，它甚至可能<i>加快</i>所有Tracker服务器上种子的速度）。你的客户端也仍然能够连接到那些使用默认端口的同伴。如果你的客户端不支持自己自定义端口，那么换一个吧。<br />
<br />
不要向我们询问，或者在论坛里面提问究竟应该选择什么端口。我们的用户选择的端口越随机，ISP就越难以捉摸清楚我们使用的端口，从而无法对我们的端口进行限速。如果我们只是简单地规定一个范围，那么ISP又会很快对那个范围内的端口进行限速的。<br />
<br />
最后还要说的是，记得在你的路由和/或防火墙上打开你选择的端口，如果你有这些东西的话。',
                'flag' => 1,
                'categ' => 5,
                'order' => 9,
            ),
            41 => 
            array (
                'id' => 48,
                'link_id' => 48,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '那么这个呢？ “IO错误 - [错误号13] 许可被拒绝”？',
                'answer' => '如果你只是想要解决这个问题的话，重新启动你的电脑应该就可以了。否则的话，继续读下去。<br />
<br />
IO错误指的是输入-输出错误，这是一个文件系统错误，而非来自我们的Tracker服务器。当你的客户端由于某些原因不能打开种子中未下载完成的文件时，这个错误就会发生。 通常原因是客户端的两个实例同时在运行：当上一次关闭客户端时因为某种原因它没有被真正关闭，从而始终在后台运行。因此文件被锁定，使得新的实例不能打开这个文件。<br />
<br />
另外一个不常出现的原因是因为老旧的FAT文件系统。某些系统崩溃可能导致未下载完成的文件损坏，接着就出现了这个错误。运行一次scandisk应该可以解决这个问题。注意，只有当你使用Windows 9X操作系统（只支持FAT文件系统）或者在Windows NT/2000/XP中使用FAT文件系统的时候才比较可能出现这个问题。NTFS文件系统要健壮许多，不太可能出现这样的问题。',
                'flag' => 1,
                'categ' => 5,
                'order' => 10,
            ),
            42 => 
            array (
                'id' => 50,
                'link_id' => 50,
                'lang_id' => 25,
                'type' => 'item',
                'question' => '不要马上下载新发布的种子',
            'answer' => '下载速度主要取决于上传者/下载者比(SLR)。如果是种子是新发布的且非常受欢迎而SLR又很低的种子，下载速度低是常见的问题。<br />
<br />
请牢记你不喜欢低速下载。所以多<b>上传</b>让别人不用忍耐同样的困扰。
<br />
<br />由其是当你的带宽较窄时，不要马上下载新发布的种子。最好的下载速度通常在一个种子生命周期的一半时出现，此时SLR达到最大值（不过，这时下载的缺点是你的上传量不会很多。究竟如何平衡优点与缺点，取决于你自己)。',
            'flag' => 1,
            'categ' => 6,
            'order' => 1,
        ),
        43 => 
        array (
            'id' => 51,
            'link_id' => 51,
            'lang_id' => 25,
            'type' => 'item',
            'question' => '限制上传速度',
            'answer' => '上传速度将从以下两种方式显著影响下载速度：
<ul>
<li>Bittorrent的同伴倾向于回馈那些给它们提上传的同伴。这就意味着如果A和B正在同时下载一个文件，而A又在高速向B传送数据，那么B将会尝试着回报A。因此高速上传将导致高速下载。</li>

<li>由于TCP协议的工作方式，当A正从B下载某些东西的时候，A必须不停地向B发送成功收到B所发送来的数据的信号（被称为确认──ACK ── 某种“已收到”的信息)。如果A没有发送ACK信号，那么B将暂停向A发送数据并且进入等候状态。如果A正在全速上传，很有可能没有用来发送ACK信号的带宽，因此ACK信号被耽搁。在这种情况下，过高速度的上传导致了低速的下载。</li>
</ul>
实际的效果是以上两个原因的结合。上传速度应该在保证ACK能正常传输的前提下被设得尽量高。 <b>一个很好的实践理论是保持上传速度在理论上限的80%。</b>你也可以细细研究什么是最适合你的速度（同时也请记住高速上传对于你的分享率很有帮助）。<br />
<br />
如果你正在运行一个以上的种子任务，你应该考虑的是全局上传速度。某些客户端能限制了全局上传速度，其他的则能对每一个种子分别进行设置。了解你的客户端。如果你正在使用你的电脑做别的某些应用（比方说上网或者从FTP拖东西），也需考虑一下它们对于全局上传的影响。',
        'flag' => 1,
        'categ' => 6,
        'order' => 2,
    ),
    44 => 
    array (
        'id' => 52,
        'link_id' => 52,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '限制同时连接数',
        'answer' => '某些操作系统（例如Windows 9X）对于大量连接数支持不完善，甚至有可能因此而崩溃。也有一些家用路由（尤其当运行着NAT以及/或者打开防火墙状态检查服务时）会因大量连接数而变得很慢或者崩溃。对于连接数没有固定的最佳值，你可以试试看60或者100。记住这些连接数是累加的，所以如果你的客户端运行了两个会话，这两个数字要加在一起。',
        'flag' => 1,
        'categ' => 6,
        'order' => 3,
    ),
    45 => 
    array (
        'id' => 53,
        'link_id' => 53,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '限制同时上传数',
        'answer' => '上传数难道和连接数有什么不一样吗？是的，两者是不同的。连接数限制了你的客户端所能对话以及/或者下载的同伴数。上传数则限制了你的客户端实际能上传的同伴数。理想的数值一般要比连接数低许多，并且与你的物理带宽高度相关。',
        'flag' => 1,
        'categ' => 6,
        'order' => 4,
    ),
    46 => 
    array (
        'id' => 54,
        'link_id' => 54,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '那怎么办？等待一会吧',
        'answer' => '如我们在上面所解释的，在BT下载过程中一个同伴更加倾向于回报那些上传给它的同伴。当你开始一个新的下载任务时，你没有文件片断提高给其他同伴，因此他们通常会无视你。这使得开始阶段的下载非常缓慢，由其是当你所连接到的同伴只有很少是（或者没有）做种者。一旦你获得了一些能用于共享的文件片断，下载速度会有所改观。',
        'flag' => 1,
        'categ' => 6,
        'order' => 5,
    ),
    47 => 
    array (
        'id' => 55,
        'link_id' => 55,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '为什么在我下载的时候浏览网页速度如此之慢？',
        'answer' => '你的下载带宽是有限的。如果你正在下载一个速度很快的种子，它几乎肯定将占满你的下载带宽。这是浏览网页显然就会变慢。很多BT客户端允许你限制下载速度，你可以试试。<br />
<br />
浏览网页只是作为举例之一，同样的问题也会出现在游戏，即时通信软件上......',
        'flag' => 1,
        'categ' => 6,
        'order' => 6,
    ),
    48 => 
    array (
        'id' => 56,
        'link_id' => 56,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '什么是代理？',
        'answer' => '基本上说，代理就是中间人。当你通过代理访问某个站点的时候，你的请求将首先被发送到代理处，然后由代理转发到你所请求的站点，而不是你直接连通到网站。下面是几种典型的代理分类（术语不是那么的规范）：<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" valign="top" width="100">&nbsp;透明代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">透明代理在客户端无需配置。他将自动把80端口的信息重定向到代理(有时候也作为非匿名代理的同义词)；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;显式代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">浏览器必须经过配置才能使用代理；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;匿名代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">代理将不会把客户标记发送至服务器端 (HTTP_X_FORWARDED_FOR头标志将不被发送。服务器也因此看不到你的IP)；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;高度匿名代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">代理将不会把客户标记或代理标记发送至服务器端 (HTTP_X_FORWARDED_FOR和HTTP_VIA and HTTP_PROXY_CONNECTION头标志将不被发送。服务器看不到你的IP，甚至不知道你正在使用代理)；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;公用</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">(不言自明了)。</td>
</tr>
</table>
<br />
透明代理可能是匿名的，也可能是非匿名的，且匿名也有相应的等级。',
        'flag' => 1,
        'categ' => 7,
        'order' => 1,
    ),
    49 => 
    array (
        'id' => 57,
        'link_id' => 57,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '我该怎样发现自己正处于一个（透明的/匿名的）代理后面？',
        'answer' => '试试看<a href="http://proxyjudge.org" class="faqlink">ProxyJudge</a>。它列出了服务器端从你这里所收到的HTTP的头标志。相关的几个标志是HTTP_CLIENT_IP，HTTP_X_FORWARDED_FOR和REMOTE_ADDR。',
        'flag' => 1,
        'categ' => 7,
        'order' => 2,
    ),
    50 => 
    array (
        'id' => 75,
        'link_id' => 75,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '我的“可连接”是“否”尽管我并没有NAT或者防火墙？',
        'answer' => 'Tracker服务器在寻找你的真实IP方面非常有天赋。但是这需要代理发送HTTP头标志HTTP_X_FORWARDED_FOR。如果你的ISP的代理没有这么做的话，Tracker服务器将把代理的IP当作你的客户端的IP。所以当你登录之后，Tracker服务器尝试连接你的客户端来确信你没有使用NAT/防火墙，服务器实际上将试图连接代理的IP（使用你的BT客户端报告的接收连接端口）。很自然地，代理服务器并没有监听那个端口。如此连接会失败，而Tracker服务器也因此认为你使用了NAT/防火墙。',
        'flag' => 1,
        'categ' => 7,
        'order' => 3,
    ),
    51 => 
    array (
        'id' => 62,
        'link_id' => 62,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '也许是因为我的IP地址被列入黑名单了？',
        'answer' => '这个网站有屏蔽被禁用户或攻击者的IP地址的功能。该功能在Apache/PHP层面上起作用，且只能屏蔽从这些地址<i>登录</i>网站。这不会阻止你<i>连接</i>到网站，更无法从底层的协议层面进行屏蔽。即便你的地址已经被列入了黑名单，你应该仍能够通过ping/traceroute命令连同服务器。如果你不能连通服务器，那么问题的原因在别处。<br />
<br />
如果你的IP地址因某种原因错误地被我们屏蔽了，请联系我们。',
        'flag' => 1,
        'categ' => 8,
        'order' => 1,
    ),
    52 => 
    array (
        'id' => 63,
        'link_id' => 63,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '你的ISP屏蔽了本站的地址',
        'answer' => '首先，这一般不像你的ISP的所作所为。DNS域名解析以及/或者网络问题是常见的真凶。
<br />
对此我们无能为力。你应该联系你的ISP（或者换一个）。记住你仍然可以通过代理访问本站，参看此常见问题关于代理的部分。在这种情况下，代理是否匿名或者使用哪个监听端口都不重要了。<br />
<br />
注意，你的“可连接状态”将因此一直被列为“否”，因为此时Tracker服务器无法检查你的BT客户端接收连接的状态。',
        'flag' => 1,
        'categ' => 8,
        'order' => 2,
    ),
    53 => 
    array (
        'id' => 65,
        'link_id' => 65,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '不妨试试这个',
        'answer' => '用任何手段在<a class="faqlink" href="forums.php">论坛</a>发帖。通常情况下你能得到友善而有用的帮助。这里给你一些基本的指导：<ul>
<li>确保你的问题的确不在这篇FAQ中。这样的发帖导致的结果就是返回这篇FAQ。</li>
<li>在发帖之前，看看置顶。很多时候，还没有被我们的FAQ所收录的最新的信息都可以在那里被找到。</li>
<li>帮助我们也就是帮助你自己。不要仅仅说“嘿我这里出问题了！！”提供一些详情，这样我们就不用猜测或者浪费时间来询问了。你使用的是什么客户端？什么操作系统？怎样的网络设置？如果发生了错误，是什么样的具体错误信息？有问题的种子文件是哪个？你所提供的信息越多，对你的帮助也就越容易，你的帖子也就更加容易得到回复。</li>
<li>不需要说的是：保持礼貌。趾高气扬得命令别人帮助你很少会有用。</li></ul>',
        'flag' => 1,
        'categ' => 9,
        'order' => 1,
    ),
    54 => 
    array (
        'id' => 66,
        'link_id' => 66,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '什么是"Your slot limit is reached! You may at most download xx torrents at the same time"错误？',
        'answer' => '这是“最大连接数系统”。最大连接数系统用于限制那些分享率较低且下载量大于10GB的用户的最大同时下载数。<br /><br />
规则如下: <br /><br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.5</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大连接数</td>
<td class="embedded" width="40">1</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.65</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大连接数</td>
<td class="embedded" width="40">2</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.8</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大连接数</td>
<td class="embedded" width="40">3</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.95</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大连接数</td>
<td class="embedded" width="40">4</td>
</tr>
<tr>
<td class="embedded" width="100">分享率大于</td>
<td class="embedded" width="40">0.95</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大连接数</td>
<td class="embedded" width="40">无限制</td>
</tr>
</table>
<br />
无论何时，同时的上传数不设限。但是如果你当前的下载数已达到了你的最大连接数，这时开启新的做种任务，你也会收到同样的错误提示。在这种情况下，你必须先关闭一个下载任务，然后开启所有的上传任务，然后继续刚才关闭的下载任务。因为当你的连接数被占满，系统将不加验证直接拒绝任何下载或上传的请求。 <br />
<br /><br />
你可以在任意时间察看自己的连接数，该信息在页面顶端信息栏中被列出。',
        'flag' => 0,
        'categ' => 5,
        'order' => 12,
    ),
    55 => 
    array (
        'id' => 67,
        'link_id' => 67,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '什么是密钥系统？它是怎么工作的？',
        'answer' => '密钥系统在BT客户端连接Tracker服务器时起到验证身份的作用。每一个用户都有一个系统随机生成的密钥。当用户下载某个种子文件时，他的私人密钥被添加到种子文件的Tracker服务器URL中。通过这种方式，你可以在家中或者办公室使用不同的IP来做种某个文件。

',
        'flag' => 1,
        'categ' => 5,
        'order' => 13,
    ),
    56 => 
    array (
        'id' => 68,
        'link_id' => 68,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '“Unknown passkey”这错误是咋回事？',
        'answer' => '首先，如果你不是我们的注册用户，将会发生这个错误。其次，有可能你没有在登录状态下从我们的网页里下载种子文件。如果是这两种情况，只要注册一个帐号或者登录后下载种子文件就可以了。<br />
另外，当你注册了一个新用户并且开始第一次下载的时候，或者你刚刚重置了你的密钥时，也可能出现这个错误。原因是系统并非实时检查密钥的改变。如果是因为这个原因，那么就让种子任务等一会儿，最后一定能收到Tracker服务器发回的成功信息。',
        'flag' => 1,
        'categ' => 5,
        'order' => 14,
    ),
    57 => 
    array (
        'id' => 69,
        'link_id' => 69,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '什么时候我需要重置我的密钥?',
        'answer' => '<ul><li> 如果你的密钥被泄漏，且别的用户正在使用这个密钥（即使用你的帐户）下载文件。在这种情况下，你能在你的详情页面看到你并未下载或上传的种子。</li>
<li>当你的客户端崩溃，或者你的连接并没有正常被终止。在这样的情况下，就算你已经关闭了你的客户端，你仍然在你的详情页面看到正在上传/下载的记录。通常情况下这些“冗余种子”将在30分钟之内被自动清除，但是如果你马上继续你的下载而服务器又提示“You already are downloading the same torrent”的错误，那么你需要重置你的密钥，然后重新下载种子文件，之后继续下载过程。 </li></ul>',
        'flag' => 1,
        'categ' => 5,
        'order' => 15,
    ),
    58 => 
    array (
        'id' => 70,
        'link_id' => 70,
        'lang_id' => 25,
        'type' => 'item',
        'question' => 'DHT是什么？为什么一定要关闭这个功能？',
        'answer' => 'DHT必须从你的客户端被禁止。DHT可能导致你的数据被错误地记录，可以视为一种作弊行为。任何使用DHT的用户将因作弊而被系统禁止。<br />
幸运的是，目前Tracker服务器会自动分析用户上传的种子文件，强制去除DHT的支持。这也是为什么种子发布者必须重新下载种子才能正常做种的原因之一。',
        'flag' => 1,
        'categ' => 5,
        'order' => 16,
    ),
    59 => 
    array (
        'id' => 71,
        'link_id' => 71,
        'lang_id' => 25,
        'type' => 'categ',
        'question' => 'How can I help translate the site language into my native language?',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 8,
    ),
    60 => 
    array (
        'id' => 72,
        'link_id' => 72,
        'lang_id' => 25,
        'type' => 'item',
        'question' => 'What skills do I need to do the translation?',
    'answer' => 'Translate the site into another language is quite easy. You needn\'t be skilled in PHP or dynamic website design. In fact, all you need is proficient understanding of English (the default site language) and the language you plan to translate into. Maybe some basic knowledge in HTML would help.<br /><br />
Moreover, we give a detailed tutorial on how to do the translation <a href="#73" class="faqlink"><b>HERE</b></a>. And our coders would be more than pleased to answer the questions you may encounter.<br /><br />
Translate the whole site into another language would take estimated 10 hours. And extra time is needed to maintain the translation when site code is updated.<br /><br />
So, if you think you could help, feel free to <a class="faqlink" href="contactstaff.php"><b>CONTACT US</b></a>. Needless to say, you would be rewarded.',
        'flag' => 1,
        'categ' => 71,
        'order' => 1,
    ),
    61 => 
    array (
        'id' => 73,
        'link_id' => 73,
        'lang_id' => 25,
        'type' => 'item',
        'question' => 'The translation tutorial',
        'answer' => '<ul>
<li>How does multi-language work?<br />
Currently we use language files to store all the static words that a user can see on web pages. <br />
Every php code file has a corresponding language file for a certain language. And we named the language file \'lang_\' plus the filename of the php code file. i.e. the language file of the php code file \'details.php\' would be \'lang_details.php\'. <br />
We has some mechanism in php codes to read the exact language files of user\'s preferred language, and you shouldn\'t worry about that.<br /></li>
<li>What\'s in language files?<br />
In a language file is an array of strings. These strings contain all the static words that a user can see on web pages. When we need to say some words on a php code file, we call for a string in the array. And the output of the php code file, that is what users see on the web pages, would show the value of the string.<br />
Sounds dizzying? Well, you need not care about all these. All you gotta do is translate the values of the strings in the language files into another language. <b>Let\'s see an example</b>:<br /><br />
The following is the content of the English language file \'lang_users.php\', which works for the php code file \'users.php\'. <br /><br />
<img src="pic/langfileeng.png" alt="langfileeng" /><br />
If you wanna translate it into Simplified Chinese, all you need is edit the file into this:<br />
<img src="pic/langfilechs.png" alt="langfilechs" /><br />
See, in every line, the left part, that is before <i>=&gt;</i>, is the name of a string, which you shouldn\'t touch. All you need to is translate the right part, after <i>=&gt;</i>, which is the value of the string, into another language.<br />
Sometimes you need to look at the corresponding web pages to get the context meaning of some words.<br /></li>
<li>Sounds easy? See what do you need to do.<br />
If you feel like to help us, <a class="faqlink" href="aboutnexus.php#contact"><b>CONTACT US</b></a> and we will send you a pack of the English language files (or any other available language if you prefer). Received it, you can start translating the value of strings (which is in English), into another language. It should take you several hours to do the whole work. After this, send back the translated language files to us.<br />
If no bugs or hacking codes are found in testing, we would put the new language into work.<br />
Sometimes the language files need to be updated, typically adding new strings, when site codes are updated. If you feel like it, you can help maintain the language files.<br /></li>
<li><font color="red"><b>IMPORTANT</b></font><br />
The text of language files must be encoded in UTF-8. When saving files, be sure to set the character encoding to UTF-8. Otherwise mojibake may happen.</li></ul>',
        'flag' => 1,
        'categ' => 71,
        'order' => 2,
    ),
    62 => 
    array (
        'id' => 74,
        'link_id' => 74,
        'lang_id' => 25,
        'type' => 'item',
        'question' => '为什么我的磁盘还有充足的空间，客户端却提示磁盘剩余空间不足？',
        'answer' => '很可能是因为你的磁盘分区的文件系统为FAT32，该文件系统不支持大于4GB的单个文件。如果你使用的是Windows系统，你可以在磁盘的属性中查看其文件系统信息。你可以将磁盘文件系统转换成更高级的NTFS来解决该问题。<br />
你可以在命令行窗口使用convert命令将FAT32无损转成NTFS文件系统格式。<br /><br />
<b>命令说明</b>
CONVERT volume /FS:NTFS [/V] [/CvtArea:filename] [/NoSecurity] [/X]<br />
volume     指定驱动器号(后面跟一个冒号)、装载点或卷名。<br />
/FS:NTFS   指定要被转换成 NTFS 的卷。<br />
/V       指定 Convert 应该用详述模式运行。<br />
/CvtArea:filename       将根目录中的一个接续文件指定为NTFS 系统文件的占位符。<br />
/NoSecurity       指定每个人都可以访问转换的文件和目录的安全设置。<br />
/X       如果必要，先强行卸载卷。该卷的所有打开的句柄则无效。 <br /><br />
<b>简单说</b><br />：
打开 命令提示符窗口。<br />
在命令提示符窗口，请键入：<br />
convert drive_letter:/fs:ntfs<br />
例如：转换D盘为NTFS，可输入convert D:/fs:ntfs',
        'flag' => 1,
        'categ' => 5,
        'order' => 17,
    ),
    63 => 
    array (
        'id' => 374,
        'link_id' => 74,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我的磁碟還有充足的空間，用戶端卻輔助說明磁碟剩餘空間不足？',
        'answer' => '很可能是因為你的磁碟割區的檔案系統為FAT32，該檔案系統不支援大于4GB的單個檔案。如果你使用的是Windows系統，你可以在磁碟的屬性中檢視其檔案系統資訊。你可以將磁碟檔案系統轉換成更進階的NTFS來解決該問題。<br />
你可以在指令行視窗使用convert指令將FAT32無損轉成NTFS檔案系統格式。<br /><br />
<b>指令說明</b>
CONVERT volume /FS:NTFS [/V] [/CvtArea:filename] [/NoSecurity] [/X]<br />
volume     指定磁碟機號(后面跟一個冒號)、裝載點或卷名。<br />
/FS:NTFS   指定要被轉換成 NTFS 的卷。<br />
/V       指定 Convert 應該用詳述型態執行。<br />
/CvtArea:filename       將根目錄中的一個接續檔案指定為NTFS 系統檔案的占位符。<br />
/NoSecurity       指定每個人都可以存取轉換的檔案和目錄的安全設定。<br />
/X       如果必要，先強行卸載卷。該卷的所有開啟的控點則無效。 <br /><br />
<b>簡單說</b><br />：
開啟 指令輔助說明符視窗。<br />
在指令輔助說明符視窗，請鍵入：<br />
convert drive_letter:/fs:ntfs<br />
例如：轉換D槃為NTFS，可匯入convert D:/fs:ntfs',
        'flag' => 1,
        'categ' => 5,
        'order' => 17,
    ),
    64 => 
    array (
        'id' => 368,
        'link_id' => 68,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '“Unknown passkey”這錯誤是咋回事？',
        'answer' => '首先，如果你不是我們的註冊用戶，將會發生這個錯誤。其次，有可能你沒有在登入狀態下從我們的網頁裡下載種子檔案。如果是這兩種情況，只要註冊一個帳號或者登入后下載種子檔案就可以了。<br />
另外，當你註冊了一個新用戶并且開始第一次下載的時候，或者你剛剛重置了你的加密鍵時，也可能出現這個錯誤。原因是系統并非實時檢查加密鍵的改變。如果是因為這個原因，那么就讓種子工作等一會兒，最后一定能收到Tracker伺服器發回的成功資訊。',
        'flag' => 1,
        'categ' => 5,
        'order' => 14,
    ),
    65 => 
    array (
        'id' => 369,
        'link_id' => 69,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '什么時候我需要重置我的加密鍵?',
        'answer' => '<ul><li> 如果你的加密鍵被泄漏，且別的用戶正在使用這個加密鍵（即使用你的帳戶）下載檔案。在這種情況下，你能在你的詳情頁面看到你并未下載或上傳的種子。</li>
<li>當你的用戶端崩潰，或者你的連線并沒有正常被終止。在這樣的情況下，就算你已經關閉了你的用戶端，你仍然在你的詳情頁面看到正在上傳/下載的記錄。通常情況下這些“多餘種子”將在30分鐘之內被自動清除，但是如果你馬上繼續你的下載而伺服器又輔助說明“You already are downloading the same torrent”的錯誤，那么你需要重置你的加密鍵，然后重新下載種子檔案，之后繼續下載過程。 </li></ul>',
        'flag' => 1,
        'categ' => 5,
        'order' => 15,
    ),
    66 => 
    array (
        'id' => 370,
        'link_id' => 70,
        'lang_id' => 28,
        'type' => 'item',
        'question' => 'DHT是什么？為什么一定要關閉這個功能？',
        'answer' => 'DHT必須從你的用戶端被禁止。DHT可能導致你的資料被錯誤地記錄，可以視為一種作弊行為。任何使用DHT的用戶將因作弊而被系統禁止。<br />
幸運的是，目前Tracker伺服器會自動解析用戶上傳的種子檔案，強制去除DHT的支援。這也是為什么種子發布者必須重新下載種子才能正常做種的原因之一。',
        'flag' => 1,
        'categ' => 5,
        'order' => 16,
    ),
    67 => 
    array (
        'id' => 371,
        'link_id' => 71,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => 'How can I help translate the site language into my native language?',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 8,
    ),
    68 => 
    array (
        'id' => 372,
        'link_id' => 72,
        'lang_id' => 28,
        'type' => 'item',
        'question' => 'What skills do I need to do the translation?',
    'answer' => 'Translate the site into another language is quite easy. You needn\'t be skilled in PHP or dynamic website design. In fact, all you need is proficient understanding of English (the default site language) and the language you plan to translate into. Maybe some basic knowledge in HTML would help.<br /><br />
Moreover, we give a detailed tutorial on how to do the translation <a href="#73" class="faqlink"><b>HERE</b></a>. And our coders would be more than pleased to answer the questions you may encounter.<br /><br />
Translate the whole site into another language would take estimated 10 hours. And extra time is needed to maintain the translation when site code is updated.<br /><br />
So, if you think you could help, feel free to <a class="faqlink" href="contactstaff.php"><b>CONTACT US</b></a>. Needless to say, you would be rewarded.',
        'flag' => 1,
        'categ' => 71,
        'order' => 1,
    ),
    69 => 
    array (
        'id' => 373,
        'link_id' => 73,
        'lang_id' => 28,
        'type' => 'item',
        'question' => 'The translation tutorial',
        'answer' => '<ul>
<li>How does multi-language work?<br />
Currently we use language files to store all the static words that a user can see on web pages. <br />
Every php code file has a corresponding language file for a certain language. And we named the language file \'lang_\' plus the filename of the php code file. i.e. the language file of the php code file \'details.php\' would be \'lang_details.php\'. <br />
We has some mechanism in php codes to read the exact language files of user\'s preferred language, and you shouldn\'t worry about that.<br /></li>
<li>What\'s in language files?<br />
In a language file is an array of strings. These strings contain all the static words that a user can see on web pages. When we need to say some words on a php code file, we call for a string in the array. And the output of the php code file, that is what users see on the web pages, would show the value of the string.<br />
Sounds dizzying? Well, you need not care about all these. All you gotta do is translate the values of the strings in the language files into another language. <b>Let\'s see an example</b>:<br /><br />
The following is the content of the English language file \'lang_users.php\', which works for the php code file \'users.php\'. <br /><br />
<img src="pic/langfileeng.png" alt="langfileeng" /><br />
If you wanna translate it into Simplified Chinese, all you need is edit the file into this:<br />
<img src="pic/langfilechs.png" alt="langfilechs" /><br />
See, in every line, the left part, that is before <i>=&gt;</i>, is the name of a string, which you shouldn\'t touch. All you need to is translate the right part, after <i>=&gt;</i>, which is the value of the string, into another language.<br />
Sometimes you need to look at the corresponding web pages to get the context meaning of some words.<br /></li>
<li>Sounds easy? See what do you need to do.<br />
If you feel like to help us, <a class="faqlink" href="aboutnexus.php#contact"><b>CONTACT US</b></a> and we will send you a pack of the English language files (or any other available language if you prefer). Received it, you can start translating the value of strings (which is in English), into another language. It should take you several hours to do the whole work. After this, send back the translated language files to us.<br />
If no bugs or hacking codes are found in testing, we would put the new language into work.<br />
Sometimes the language files need to be updated, typically adding new strings, when site codes are updated. If you feel like it, you can help maintain the language files.<br /></li>
<li><font color="red"><b>IMPORTANT</b></font><br />
The text of language files must be encoded in UTF-8. When saving files, be sure to set the character encoding to UTF-8. Otherwise mojibake may happen.</li></ul>',
        'flag' => 1,
        'categ' => 71,
        'order' => 2,
    ),
    70 => 
    array (
        'id' => 362,
        'link_id' => 62,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '也許是因為我的IP位址被列入黑名單了？',
        'answer' => '這個網站有屏蔽被禁用戶或攻擊者的IP位址的功能。該功能在Apache/PHP層面上起作用，且只能屏蔽從這些位址<i>登入</i>網站。這不會阻止你<i>連線</i>到網站，更無法從底層的協定層面進行屏蔽。即便你的位址已經被列入了黑名單，你應該仍能夠通過ping/traceroute指令連同伺服器。如果你無法連通伺服器，那么問題的原因在別處。<br />
<br />
如果你的IP位址因某種原因錯誤地被我們屏蔽了，請聯繫我們。',
        'flag' => 1,
        'categ' => 8,
        'order' => 1,
    ),
    71 => 
    array (
        'id' => 363,
        'link_id' => 63,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '你的ISP屏蔽了本站的位址',
        'answer' => '首先，這一般不像你的ISP的所作所為。DNS域名解析以及/或者網路問題是常見的真凶。
<br />
對此我們無能為力。你應該聯繫你的ISP（或者換一個）。記住你仍然可以通過代理存取本站，參看此常見問題關于代理的部分。在這種情況下，代理是否匿名或者使用哪個監聽通訊埠都不重要了。<br />
<br />
注意，你的“可連線狀態”將因此一直被列為“否”，因為此時Tracker伺服器無法檢查你的BT用戶端接收連線的狀態。',
        'flag' => 1,
        'categ' => 8,
        'order' => 2,
    ),
    72 => 
    array (
        'id' => 365,
        'link_id' => 65,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '不妨試試這個',
        'answer' => '用任何手段在<a class="faqlink" href="forums.php">論壇</a>發帖。通常情況下你能得到友善而有用的輔助說明。這裡給你一些基本的指導：<ul>
<li>確保你的問題的確不在這篇FAQ中。這樣的發帖導致的結果就是傳回這篇FAQ。</li>
<li>在發帖之前，看看置頂。很多時候，還沒有被我們的FAQ所收錄的最新的資訊都可以在那裡被找到。</li>
<li>輔助說明我們也就是輔助說明你自己。不要僅僅說“嘿我這裡出問題了！！”提供一些詳情，這樣我們就不用猜測或者浪費時間來詢問了。你使用的是什么用戶端？什么作業系統？怎樣的網路設定？如果發生了錯誤，是什么樣的具體錯誤資訊？有問題的種子檔案是哪個？你所提供的資訊越多，對你的輔助說明也就越容易，你的帖子也就更加容易得到回覆。</li>
<li>不需要說的是：保持禮貌。趾高氣揚得指令別人輔助說明你很少會有用。</li></ul>',
        'flag' => 1,
        'categ' => 9,
        'order' => 1,
    ),
    73 => 
    array (
        'id' => 366,
        'link_id' => 66,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '什么是"Your slot limit is reached! You may at most download xx torrents at the same time"錯誤？',
        'answer' => '這是“最大連線數系統”。最大連線數系統用于限制那些分享率較低且下載量大于10GB的用戶的最大同時下載數。<br /><br />
規則如下: <br /><br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.5</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大連線數</td>
<td class="embedded" width="40">1</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.65</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大連線數</td>
<td class="embedded" width="40">2</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.8</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大連線數</td>
<td class="embedded" width="40">3</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.95</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大連線數</td>
<td class="embedded" width="40">4</td>
</tr>
<tr>
<td class="embedded" width="100">分享率大于</td>
<td class="embedded" width="40">0.95</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">最大連線數</td>
<td class="embedded" width="40">無限制</td>
</tr>
</table>
<br />
無論何時，同時的上傳數不設限。但是如果你目前的下載數已達到了你的最大連線數，這時開啟新的做種工作，你也會收到同樣的錯誤輔助說明。在這種情況下，你必須先關閉一個下載工作，然后開啟所有的上傳工作，然后繼續剛才關閉的下載工作。因為當你的連線數被占滿，系統將不加驗證直接拒絕任何下載或上傳的要求。 <br />
<br /><br />
你可以在任意時間察看自己的連線數，該資訊在頁面頂端資訊欄中被列出。',
        'flag' => 0,
        'categ' => 5,
        'order' => 12,
    ),
    74 => 
    array (
        'id' => 367,
        'link_id' => 67,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '什么是加密鍵系統？它是怎么工作的？',
        'answer' => '加密鍵系統在BT用戶端連線Tracker伺服器時起到驗證身份的作用。每一個用戶都有一個系統隨機生成的加密鍵。當用戶下載某個種子檔案時，他的私人加密鍵被添加到種子檔案的Tracker伺服器URL中。通過這種方式，你可以在家中或者辦公室使用不同的IP來做種某個檔案。

',
        'flag' => 1,
        'categ' => 5,
        'order' => 13,
    ),
    75 => 
    array (
        'id' => 301,
        'link_id' => 1,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '網站資訊',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 1,
    ),
    76 => 
    array (
        'id' => 302,
        'link_id' => 2,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '用戶資訊',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 2,
    ),
    77 => 
    array (
        'id' => 303,
        'link_id' => 3,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '資料統計',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 3,
    ),
    78 => 
    array (
        'id' => 304,
        'link_id' => 4,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '發布',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 4,
    ),
    79 => 
    array (
        'id' => 305,
        'link_id' => 5,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '下載',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 5,
    ),
    80 => 
    array (
        'id' => 306,
        'link_id' => 6,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '我該怎樣提升下載速度？',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 6,
    ),
    81 => 
    array (
        'id' => 307,
        'link_id' => 7,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '我的網路提供商（ISP）使用了透通代理。我應該怎么做？',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 7,
    ),
    82 => 
    array (
        'id' => 308,
        'link_id' => 8,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '為什么我連不上？難道我被網站屏蔽了？',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 8,
    ),
    83 => 
    array (
        'id' => 309,
        'link_id' => 9,
        'lang_id' => 28,
        'type' => 'categ',
        'question' => '如果從這裡找不到我的問題的解答，我該怎么辦？',
        'answer' => '',
        'flag' => 1,
        'categ' => 0,
        'order' => 9,
    ),
    84 => 
    array (
        'id' => 310,
        'link_id' => 10,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '到底什么是BT（Bittorrent）？我要如何才能弄到這類程式？',
        'answer' => '參看<a class="faqlink" href="http://www.btfaq.com/">Brian的BitTorrent FAQ及指導</a>。',
        'flag' => 1,
        'categ' => 1,
        'order' => 1,
    ),
    85 => 
    array (
        'id' => 311,
        'link_id' => 11,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我們捐出來的錢上哪兒去了？',
        'answer' => '所有捐助的錢用于維護伺服器的費用。',
        'flag' => 1,
        'categ' => 1,
        'order' => 2,
    ),
    86 => 
    array (
        'id' => 312,
        'link_id' => 12,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我從哪裡可以搞到來源碼的拷貝？',
        'answer' => '這個Tracker使用的是NexusPHP的程式碼。程式碼抓取事宜，請看<a class="faqlink" href="aboutnexus.php">這裡</a>。<br />
NexusPHP的程式碼是根據TBSource的源碼改寫的。TBDev網站，Tracker來源碼以及社區論壇都在這裡：<a class="faqlink" href="http://www.tbdev.net/">TBDev.net</a>。',
        'flag' => 1,
        'categ' => 1,
        'order' => 3,
    ),
    87 => 
    array (
        'id' => 313,
        'link_id' => 13,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我註冊了一個賬號但是卻沒有收到驗證郵件！',
        'answer' => '這很有可能是Tracker所在的伺服器網路出現了問題。你可以填寫<a href="confirm_resend.php" class="faqlink">這個表格</a>讓伺服器重發驗證郵件。<br />
通常沒有得到確認的註冊帳戶會在24小時后被移除，所以你可以在第二天重試。需要注意的是，如果你第一次沒有收到確認信，第二次有很大的可能仍然無法收到確認信，所以換一個E-mail位址是一個不錯的主意。',
        'flag' => 1,
        'categ' => 2,
        'order' => 1,
    ),
    88 => 
    array (
        'id' => 314,
        'link_id' => 14,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我忘記了我的用戶名/密碼了，能幫我找回來么？',
        'answer' => '請填寫<a class="faqlink" href="recover.php">這個表單</a>，我們將把登入詳情發還給你。',
        'flag' => 1,
        'categ' => 2,
        'order' => 2,
    ),
    89 => 
    array (
        'id' => 315,
        'link_id' => 15,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我可以給我的賬號改個名字么？',
        'answer' => '我們并不支援給賬號改名。',
        'flag' => 1,
        'categ' => 2,
        'order' => 3,
    ),
    90 => 
    array (
        'id' => 316,
        'link_id' => 16,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '你可以移除一個（已確認的）賬號么？',
        'answer' => '我們一般不提供該類服務，所以不要要求移除任何一個被你邀請的帳號。',
        'flag' => 1,
        'categ' => 2,
        'order' => 4,
    ),
    91 => 
    array (
        'id' => 317,
        'link_id' => 17,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '那么，什么是我的分享率（Ratio）？',
        'answer' => '就在頁面功能割區的下面。<br />
<br />
<img src="pic/ratio.png" alt="ratio" />
<br />
<br />
區分你的整體分享率和獨立分享率是很重要的。整體分享率關注的是自從你加入網站以來，賬號的整體上傳與下載量。而獨立分享率則針對每一個你正在下載或做種的檔案。
<br />
<br />
你可能看到兩種象徵式來代替數字："Inf."，這個是無限（Infinity）的縮寫，意思是你的下載量為0位元組，而上傳量則是任意一個非零的值(上傳量/下載量=無窮大)；"---"，應該被視為“不可用”，這說明了你的下載量和上傳量都是0位元組。(上傳量/下載量=0/0 這是一個不確定的量)。
',
        'flag' => 1,
        'categ' => 2,
        'order' => 5,
    ),
    92 => 
    array (
        'id' => 318,
        'link_id' => 18,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我的IP位址察看在我的個人資訊頁面上？',
        'answer' => '只有你自己和網站Moderator及以上的管理員可以看到你的IP位址和Email。普通用戶是看不到這些資訊的。',
        'flag' => 1,
        'categ' => 2,
        'order' => 6,
    ),
    93 => 
    array (
        'id' => 321,
        'link_id' => 21,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我的“可連線”是“否”？（以及為什么我需要關注這個問題？）',
        'answer' => 'Tracker伺服器認為你在防火牆后，或者在NAT橋接后，并且無法接收其他Peer的連線要求。
<br />
<br />
這意味着其他的大批Peer無法連線到你，只能由你連線到他們。更糟糕的情況是，如果兩個Peer都處于這樣的狀態，他們將完全無法連線到對方。這對于整體速度有着非常不利的影響。
<br />
<br />
對于這個問題，有以下解決方式：對于防火牆，開啟用于接收連線的通訊埠（即你在BT用戶端中定義的通訊埠）；對于NAT，你需要配置NAT伺服器使用Basic NAT方式而不是NAPT（不同的路線有着不同的執行方式。翻閱你的路線文件或客服論壇。你也可以在<a class="faqlink" href="http://portforward.com/">PortForward</a>找到關于此問題的大量資訊）。





',
        'flag' => 1,
        'categ' => 2,
        'order' => 9,
    ),
    94 => 
    array (
        'id' => 322,
        'link_id' => 22,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '不同的用戶等級代表了什么含義？',
        'answer' => '<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="200" valign="top">&nbsp; <b class="Peasant_Name">Peasant</b></td>
<td class="embedded" width="5">&nbsp;</td>
<td class="embedded"> 被降級的用戶，他們有30天時間來提升分享率，否則他們會被踢。無法發表趣味盒內容；無法申請友情鏈結；無法上傳字幕。
</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">新用戶的預設級別。只能在每周六中午12點至每周日晚上11點59分發布種子。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="PowerUser_Name">Power User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到一個邀請名額；可以直接發布種子；可以檢視NFO文件；可以檢視用戶清單；可以要求續種； 可以傳送邀請； 可以檢視排行榜；可以檢視其他用戶的種子曆史(如果用戶隱私等級未設定為"強")； 可以移除自己上傳的字幕。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="EliteUser_Name">Elite User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded"><b class="EliteUser_Name">Elite User</b>及以上用戶封存賬號后不會被移除。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="CrazyUser_Name">Crazy User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到兩個邀請名額；可以在做種/下載/發布的時候選取匿名型態。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="InsaneUser_Name">Insane User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">可以檢視普通日誌。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VeteranUser_Name">Veteran User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到三個邀請名額；可以檢視其他用戶的評論、帖子曆史。<b class="VeteranUser_Name">Veteran User</b>及以上用戶會永遠保留賬號。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="ExtremeUser_Name">Extreme User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">可以更新過期的外部資訊；可以檢視Extreme User論壇。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="UltimateUser_Name">Ultimate User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到五個邀請名額。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="NexusMaster_Name">Nexus Master</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">得到十個邀請名額。</td>
</tr>
<tr>
<td class="embedded"  valign="top">&nbsp; <img class="star" src="pic/trans.gif" alt="Star" /></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">為網站捐款的主。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VIP_Name">貴賓(VIP)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">和<b class="NexusMaster_Name">Nexus Master</b>擁有相同許可權并被認為是精英成員。免除自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">其他</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">自訂等級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Retiree_Name">養老族(Retiree)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">退休后的管理組成員。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Uploader_Name">發布員(Uploader)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">專注的發布者。免除自動降級；可以檢視匿名用戶的真實身份。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Moderator_Name">總版主(Moderator)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">可以檢視管理組郵箱、舉報郵箱；管理趣味盒內容、投票內容；可以編輯或移除任何發布的種子；可以管理候選；可以管理論壇帖子、用戶評論；可以檢視機密日誌；可以移除任何字幕；可以管理日誌中的程式碼、史冊；可以檢視用戶的邀請記錄；可以管理用戶帳號的一般資訊。<b>無法</b>管理友情鏈結、最近訊息、論壇版塊；<b>無法</b>將種子設為置頂或促銷；<b>無法</b>檢視用戶IP或Email等機密資訊；無法移除賬號。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Administrator_Name">管理員(Administrator)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">除了無法改變網站設定、管理捐贈外，可以做任何事。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="SysOp_Name">維護開發員(Sysop)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">網站開發/維護人員，可以改變網站設定，無法管理捐贈。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="StaffLeader_Name">主管(Staff Leader)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">網站主管，可以做任何事。</td>
</tr>
</table>',
        'flag' => 1,
        'categ' => 2,
        'order' => 10,
    ),
    95 => 
    array (
        'id' => 323,
        'link_id' => 23,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '提升和降級又是怎樣被執行的呢？',
        'answer' => '<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="200" valign="top">&nbsp; <b class="Peasant_Name">Peasant</b></td>
<td class="embedded" width="5">&nbsp;</td>
<td class="embedded" valign="top">當以下情況時將被自動降至本級：<br />
1.如果你已經下載了超過50GB，你應該有大于0.4的分享率。<br />
2.如果你已經下載了超過100GB，你應該有大于0.5的分享率。<br />
3.如果你已經下載了超過200GB，你應該有大于0.6的分享率。<br />
4.如果你已經下載了超過400GB，你應該有大于0.7的分享率。<br />
5.如果你已經下載了超過800GB，你應該有大于0.8的分享率。</td>
</tr>

<tr>
<td class="embedded" valign="top">&nbsp; <b class="PowerUser_Name">Power User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少4周，并且下載至少50G，分享率大于1.05。<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于0.95，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="EliteUser_Name">Elite User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少8周，并且下載至少120G，分享率大于1.55。
<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于1.45，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="CrazyUser_Name">Crazy User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少15周，并且下載至少300G，分享率大于2.05。
<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于1.95，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="InsaneUser_Name">Insane User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少25周，并且下載至少500G，分享率大于2.55。<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于2.45，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VeteranUser_Name">Veteran User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少40周，并且下載至少750G，分享率大于3.05。<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于2.95，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="ExtremeUser_Name">Extreme User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少60周，并且下載至少1TB，分享率大于3.55。<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于3.45，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="UltimateUser_Name">Ultimate User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少80周，并且下載至少1.5TB，分享率大于4.05。<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于3.95，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="NexusMaster_Name">Nexus Master</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">必須註冊至少100周，并且下載至少3TB，分享率大于4.55。<br />
當條件符合時將被自動提升。注意，無論何時，如果你的分享率低于4.45，你將自動降級。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <img class="star" src="pic/trans.gif" alt="Star" /></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">只需捐款，詳見<a class="faqlink" href="donate.php">這裡</a>。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VIP_Name">貴賓(VIP)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">由管理員仔細斟酌后配置給他們認為對于網站某方面有特殊貢獻的用戶。<br />
（任何索取貴賓等級的要求將被自動無視）</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">其他</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">用戶使用魔力值兌換，或由管理員仔細斟酌后授權。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Retiree_Name">養老族(Retiree)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">由管理員授予。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Uploader_Name">發布員(Uploader)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">由管理員配置(參見\'發布\'部分以了解詳情)。</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Moderator_Name">總版主(Moderator)</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">無需多問，我們會找到你的！</td>
</tr>
</table>',
        'flag' => 1,
        'categ' => 2,
        'order' => 11,
    ),
    96 => 
    array (
        'id' => 325,
        'link_id' => 25,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我的朋友無法加入？',
        'answer' => '用戶數目有所限制（你可以檢視“首頁-&gt;網站資料-&gt;上限”）。當達到那個數目時，我們會停止接受新成員。<br />
由于非活躍用戶(長期不使用賬號的用戶)引起賬號資源浪費，因此我們將清理該部分用戶，為其他想加入用戶騰出空間。<br />
關于非活躍用戶清理規則，參照<a class="faqlink" href="rules.php">規則</a>。',
        'flag' => 1,
        'categ' => 2,
        'order' => 13,
    ),
    97 => 
    array (
        'id' => 326,
        'link_id' => 26,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '怎樣自訂頭像？',
        'answer' => '首先，找一個你喜歡的圖片，當然了它無法違反我們的<a class="faqlink" href="rules.php">規則</a>。然后你要找一個存放它的地方，比方說我們自己的<a class="faqlink" href="bitbucket-upload.php">上傳器</a>。出于伺服器負載的考慮，我們更希望你將圖片上傳到別的網站，然后將其URL黏貼到你的<a class="faqlink" href="usercp.php?action=personal">控制面板</a>的頭像URL部分。 <br />
<br />
請不要為了僅僅測試頭像而發帖。如果一切順利，你將在你的詳情頁面看到它。',
        'flag' => 1,
        'categ' => 2,
        'order' => 14,
    ),
    98 => 
    array (
        'id' => 327,
        'link_id' => 27,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '最常見的資料未能得到更新的原因',
        'answer' => '<ul>
<li>伺服器超載/未回應。只要嘗試着保持階段作業直到伺服器回復回應(不建議連續手動清除，這樣會加重伺服器負載)。</li>
<li>你正在使用不穩定的用戶端。如果你想要使用測試版或者CVS版本，你需要自己承擔由此帶來的風險。</li>
</ul>',
        'flag' => 1,
        'categ' => 3,
        'order' => 1,
    ),
    99 => 
    array (
        'id' => 328,
        'link_id' => 28,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '最佳的嘗試',
        'answer' => '<ul>
<li>如果你目前正下載/上傳的種子并不在你的用戶詳情頁面中，你可以等一會或者強制進行手動更新。</li>
<li>確保你正確地關閉了用戶端軟體，否則Tracker伺服器無法收到"event=completed"的訊號。</li>
<li>如果Tracker伺服器掛了，不要停止上傳。只要在你結束用戶端之前伺服器回復工作，所有的資料都會得到正確地更新。</li>
</ul>',
        'flag' => 1,
        'categ' => 3,
        'order' => 2,
    ),
    100 => 
    array (
        'id' => 329,
        'link_id' => 29,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我可以任意選取Bittorrent用戶端軟體么？',
        'answer' => '根據<a class="faqlink" href="aboutnexus.php">NexusPHP</a>對常見BitTorrent用戶端的測試，目前本站Tracker<b>只允許</b>使用以下的BitTorrent用戶端軟體。<br />
<a class="faqlink" href="aboutnexus.php">NexusPHP</a>的測試報告可<a class="faqlink" href="http://www.nexusphp.com/wiki/%E5%AE%A2%E6%88%B7%E7%AB%AF%E6%B5%8B%E8%AF%95%E6%8A%A5%E5%91%8A">在此檢視</a>。
<br />
<b>Windows:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2及后續版本</li>
<li><a class="faqlink" href="http://www.utorrent.com">uTorrent</a>: 1.6.1, 1.7.5, 1.7.6, 1.7.7, 1.8Beta(Build 10364), 2.0(Build 17624)及后續版本</li>
<li><a class="faqlink" href="http://www.bittorrent.com">BitTorrent</a>: 6.0.1, 6.0.2, 6.0.3及后續版本</li>
<li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6及后續版本</li>
<li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0及后續版本</li>
</ul>
<b>Linux:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2及后續版本</li>
<li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6及后續版本</li>
<li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0及后續版本</li>
<li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21及后續版本</li>
<li><a class="faqlink" href="http://libtorrent.rakshasa.no">rTorrent</a>: 0.8.0（配合libtorrent 0.12.0或后續版本）及后續版本</li>
<li><a class="faqlink" href="http://www.rahul.net/dholmes/ctorrent/">Enhanced CTorrent</a>: 3.3.2及后續版本</li>
</ul>
<b>MacOS X:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2及后續版本</li>
<li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21及后續版本</li>
<li><a class="faqlink" href="http://sourceforge.net/projects/bitrocket/">BitRocket</a>: 0.3.3(32)及后續版本</li>
</ul>
<b>Symbian (僅供測試):</b>
<ul>
<li><a class="faqlink" href="http://amorg.aut.bme.hu/projects/symtorrent">SymTorrent</a>: 1.41及后續版本</li>
</ul>
<br />

<b>以上用戶端在https支援方面</b>
<ul>
<li>uTorrent 1.61: 無法准確解析https的tracker, 同時在使用會將自己識別為uTorrent 1.5</li>
<li>Rufus: 沒有https支援, 并且已經几年沒有繼續開發</li>
<li>rtorrent: 需要手工設定SSL證書, 詳細資訊請自行查閱其官方網站說明</li>
</ul>

<br />

同時請儘量避免使用處于測試期的用戶端軟體, 如uTorrent 1.8.0B。

為了從本站得到最好的下載體驗,我們高度建議<a class="faqlink" href="http://www.utorrent.com/download.php">uTorrent</a> 以及<a class="faqlink" href="http://azureus.sourceforge.net/download.php">Azureus</a>這兩個軟體的最新穩定版。',
        'flag' => 1,
        'categ' => 5,
        'order' => 3,
    ),
    101 => 
    array (
        'id' => 330,
        'link_id' => 30,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我正在下載/做種的一個種子會在我的詳情頁面中被列出多次？',
    'answer' => '如果因為某些原因(比方說死機，用戶端失去回應)你的用戶端非正常結束并且你又重新開啟它，它會被配置到一個全新的Peer ID, 這樣它察看為一個全新的種子。而舊的種子將永遠不會收到“event=completed”或“event=stopped”的訊號，因此將一直被列出直到Tracker清理逾時的Peer。 無視了它就可以了，它最后會消失的。',
        'flag' => 1,
        'categ' => 3,
        'order' => 4,
    ),
    102 => 
    array (
        'id' => 331,
        'link_id' => 31,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我已經完成/取消的種子仍然在我的詳情頁面裡？',
        'answer' => '有一些用戶端，比如說TorrentStorm和Nova Torrent，在取消或者完成一個工作的時候無法向Tracker伺服器正確傳送訊號。在那樣的情況下，Tracker伺服器將一直保持等待訊號的狀態（因此會一直列出種子的狀態為做種/下載的狀態）直到Tracker清理逾時的Peer。無視了它就可以了，它最后會消失的。',
        'flag' => 1,
        'categ' => 3,
        'order' => 5,
    ),
    103 => 
    array (
        'id' => 333,
        'link_id' => 33,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '多IP（我可以從不同的電腦登入/下載嗎？）',
        'answer' => '是的，目前Tracker伺服器支援單個用戶從任意個數的IP位址同時存取/下載。<br />
然而，對于單種是有限制的。對于每個種子，最多允許3個做種的連線，最多只允許1個下載的連線（這意味着，對于某個種子你一次只能在一個地方下載）。',
        'flag' => 1,
        'categ' => 3,
        'order' => 7,
    ),
    104 => 
    array (
        'id' => 336,
        'link_id' => 36,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我無法發布種子？',
        'answer' => '請看<a class="faqlink" href="rules.php">規則</a>。',
        'flag' => 1,
        'categ' => 4,
        'order' => 1,
    ),
    105 => 
    array (
        'id' => 337,
        'link_id' => 37,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我需要滿足哪些條件才能加入發布小組呢？',
        'answer' => '你所必須滿足的條件：
<ul>
<li>具有穩定的資源來源</li>
<li>平均每周資源發布數量不少于5個</li>
</ul>
你必須確保發布的檔案符合以下特征:
<ul>
<li>不超過7天之前</li>
<li>你必須要有做種的能力，或者可以確保至少有效供種24小時。</li>
<li>你需要有至少達到2MBit的上傳頻寬。</li>
</ul>
如果你認為你符合以上條件，那么不要猶豫，<a class="faqlink" href="contactstaff.php">聯繫管理組</a>吧。<br />
<b>記住！</b> 仔細填寫你的申請！確保申請中包含你的上傳速度以及你計划發布的內容的類別。<br />
只有仔細填寫并且經過慎重考慮的申請才會納入我們的視野。',
        'flag' => 1,
        'categ' => 4,
        'order' => 2,
    ),
    106 => 
    array (
        'id' => 338,
        'link_id' => 38,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我可以發布你們這裡的種子到別的Tracker伺服器么？',
        'answer' => '無法。我們是一個封閉的、限制用戶數的社區。只有註冊用戶才能夠使用我們的Tracker。將我們的種子檔案發布到其他Tracker伺服器是徒勞的，因為絕大多數嘗試下載這些檔案的人將無法連線到我們的伺服器。將我們的種子發布到其他Tracker只讓那裡的用戶惱怒（因為無法下載）并且對我們產生敵對情緒，所以這種行為是我們是無法容忍的。<br />
<br />
如果其他網站的管理員向我們舉報我們的種子被發布在他們的網站上，對此負責的用戶將因此被移出我們的社區。<br />
<br />
但是對于從我們這裡下載得到的檔案，你可以隨意操控它們（發布者明確說明為<b>獨占</b>的資源除外）。你可以制作另一個種子，寫入其他Tracker的位址，并發布到你喜歡的網站上。',
        'flag' => 1,
        'categ' => 4,
        'order' => 3,
    ),
    107 => 
    array (
        'id' => 339,
        'link_id' => 39,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '下載來的檔案該怎么開啟？',
        'answer' => '在這份<a class="faqlink" href="formats.php">指引</a>裡面找找看吧。',
        'flag' => 1,
        'categ' => 5,
        'order' => 1,
    ),
    108 => 
    array (
        'id' => 340,
        'link_id' => 40,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我下載了一部電影，但是不明白CAM/TS/TC/SCR是什么意思?',
        'answer' => '在這份<a class="faqlink" href="videoformats.php">指引</a>裡面找找看吧。',
        'flag' => 1,
        'categ' => 5,
        'order' => 2,
    ),
    109 => 
    array (
        'id' => 341,
        'link_id' => 41,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么一個可用的種子忽然就消失了？',
        'answer' => '以下原因都會導致這個現象的出現:<br />
(<b>1</b>) 按照<a class="faqlink" href="rules.php">網站規則</a>，這個種子不符合規則已被移除。
<br />
(<b>2</b>) 發布者因為檔案存在問題而移除了它。一般來說，發布者會發布一個交替的版本的。<br />
(<b>3</b>) 在長時間斷種后種子被自動移除了。',
        'flag' => 1,
        'categ' => 5,
        'order' => 3,
    ),
    110 => 
    array (
        'id' => 342,
        'link_id' => 42,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '我該怎樣續傳檔案或者給一個檔案續種呢？',
        'answer' => '開啟副檔名為.torrent的檔案，當你的用戶端軟體詢問儲存的目錄時，選取已經存在的檔案存放的目錄，它就能夠開始續傳/續種了。',
        'flag' => 1,
        'categ' => 5,
        'order' => 4,
    ),
    111 => 
    array (
        'id' => 343,
        'link_id' => 43,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我的下載有時候停在99%?',
        'answer' => '你所下載到的片段越多，搜尋擁有你所缺少的片段的同伴就將變得越困難。這就是為什么有時候在下載即將完成的時候，速度會變得非常慢，甚至完全停止了。只要耐心等候，或早或晚剩餘的部分一定能夠下載到的。',
        'flag' => 1,
        'categ' => 5,
        'order' => 5,
    ),
    112 => 
    array (
        'id' => 344,
        'link_id' => 44,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '"部分哈希驗證出錯"這條資訊是什么意思？',
        'answer' => 'BT的用戶端會檢查它所收到的資料的完整性。如果某一個片段在驗證中出錯，那么這個片段將被重新下載。偶爾的哈希驗證錯誤是非常常見的現象，不用擔心。<br />
<br />
有些用戶端有屏蔽向你傳送錯誤資料的其他用戶端的功能。如果有這個選項，最好開啟它。因為這樣可以保證如果某個同伴經常向你傳送錯誤的資料時，它將會被屏蔽。',
        'flag' => 1,
        'categ' => 5,
        'order' => 6,
    ),
    113 => 
    array (
        'id' => 345,
        'link_id' => 45,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '100MB大小的種子，我怎么下了120MB的東西回來?',
        'answer' => '參見哈希驗證失敗的那個問題。如果你的用戶端收到了錯誤的資料，那么它將會重新下載這一部分，因此總下載量有可能比種子大小略微大一些。確保“屏蔽傳送錯誤資料的用戶端”的那個選項被開啟了，這樣可以減少其餘的下載。',
        'flag' => 1,
        'categ' => 5,
        'order' => 7,
    ),
    114 => 
    array (
        'id' => 346,
        'link_id' => 46,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么我的用戶端的Tracker伺服器狀態出現"Your ratio is too low! You need to wait xx h to start"的錯誤？',
        'answer' => '在<b>新</b>種子被上傳到Tracker伺服器之后，有一些用戶必須等一些時間才能開始下載。<br>
這個延遲只會影響那些分享率較低且下載量大于10G的用戶。<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.4</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延遲</td>
<td class="embedded" width="100">24小時</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.5</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延遲</td>
<td class="embedded" width="100">12小時</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.6</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延遲</td>
<td class="embedded" width="100">6小時</td>
</tr>
<tr>
<td class="embedded" width="100">分享率低于</td>
<td class="embedded" width="40">0.8</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">延遲</td>
<td class="embedded" width="100">3小時</td>
</tr>
</table>',
        'flag' => 0,
        'categ' => 5,
        'order' => 8,
    ),
    115 => 
    array (
        'id' => 347,
        'link_id' => 47,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '為什么發生了這個錯誤？ "Port xxxx is blacklisted"?',
    'answer' => '你的用戶端向Tracker伺服器報告它正在使用某個預設的BT協定通訊埠(6881-6889)或是任何其他常見P2P通訊埠來作為連線通訊埠。<br />
<br />
本站不允許這些通常被P2P協定預設使用的通訊埠。原因是目前ISP常常對這些通訊埠進行限速。<br />
<br />
被屏蔽的通訊埠如下：<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">Direct Connect</td>
<td class="embedded" width="100">411 - 413</td>
</tr>
<tr>
<td class="embedded" width="100">BitTorrent</td>
<td class="embedded" width="100">6881 - 6889</td>
</tr>
<tr>
<td class="embedded" width="100">Kazza</td>
<td class="embedded" width="100">1214</td>
</tr>
<tr>
<td class="embedded" width="100">Gnutella</td>
<td class="embedded" width="100">6346 - 6347</td>
</tr>
<tr>
<td class="embedded" width="100">Emule</td>
<td class="embedded" width="100">4662</td>
</tr>
<tr>
<td class="embedded" width="100">WinMX</td>
<td class="embedded" width="100">6699</td>
</tr>
</table>
<br />
要使用我們的Tracker伺服器，你必須配置你的用戶端使用未在上面列出的通訊埠範圍(從49152到65535都是不錯的選取，參看<a class="faqlink" href="http://www.iana.org/assignments/port-numbers">IANA</a>)。注意某些用戶端，如Azureus 2.0.7.0或更高版本，對所有的種子都使用同一通訊埠。而其他大多數用戶端為每一個種子開放一個通訊埠，你在選取通訊埠範圍應考慮到這個問題（一般來說通訊埠範圍小于10。設定一個過大的範圍并不一定有好處，而且可能有安全隱患)。 <br />
<br />
這些通訊埠是用于同伴間通訊的，而非用于用戶端連線到Tracker伺服器。因此這個改動并不會影響你使用其他Tracker伺服器（事實上，它甚至可能<i>加快</i>所有Tracker伺服器上種子的速度）。你的用戶端也仍然能夠連線到那些使用預設通訊埠的同伴。如果你的用戶端不支援自己自訂通訊埠，那么換一個吧。<br />
<br />
不要向我們詢問，或者在論壇裡面提問究竟應該選取什么通訊埠。我們的用戶選取的通訊埠越隨機，ISP就越難以捉摸清楚我們使用的通訊埠，從而無法對我們的通訊埠進行限速。如果我們只是簡單地規定一個範圍，那么ISP又會很快對那個範圍內的通訊埠進行限速的。<br />
<br />
最后還要說的是，記得在你的路線和/或防火牆上開啟你選取的通訊埠，如果你有這些東西的話。',
        'flag' => 1,
        'categ' => 5,
        'order' => 9,
    ),
    116 => 
    array (
        'id' => 348,
        'link_id' => 48,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '那么這個呢？ “IO錯誤 - [錯誤號13] 許可被拒絕”？',
        'answer' => '如果你只是想要解決這個問題的話，重新啟動你的電腦應該就可以了。否則的話，繼續讀下去。<br />
<br />
IO錯誤指的是匯入-匯出錯誤，這是一個檔案系統錯誤，而非來自我們的Tracker伺服器。當你的用戶端由于某些原因無法開啟種子中未下載完成的檔案時，這個錯誤就會發生。 通常原因是用戶端的兩個實例同時在執行：當上一次關閉用戶端時因為某種原因它沒有被真正關閉，從而始終在背景執行。因此檔案被鎖定，使得新的實例無法開啟這個檔案。<br />
<br />
另外一個不常出現的原因是因為老舊的FAT檔案系統。某些系統崩潰可能導致未下載完成的檔案損壞，接着就出現了這個錯誤。執行一次scandisk應該可以解決這個問題。注意，只有當你使用Windows 9X作業系統（只支援FAT檔案系統）或者在Windows NT/2000/XP中使用FAT檔案系統的時候才比對可能出現這個問題。NTFS檔案系統要健壯許多，不太可能出現這樣的問題。',
        'flag' => 1,
        'categ' => 5,
        'order' => 10,
    ),
    117 => 
    array (
        'id' => 350,
        'link_id' => 50,
        'lang_id' => 28,
        'type' => 'item',
        'question' => '不要馬上下載新發布的種子',
    'answer' => '下載速度主要取決于上傳者/下載者比(SLR)。如果是種子是新發布的且非常受歡迎而SLR又很低的種子，下載速度低是常見的問題。<br />
<br />
請牢記你不喜歡低速下載。所以多<b>上傳</b>讓別人不用忍耐同樣的困擾。
<br />
<br />由其是當你的頻寬較窄時，不要馬上下載新發布的種子。最好的下載速度通常在一個種子生命周期的一半時出現，此時SLR達到最大值（不過，這時下載的缺點是你的上傳量不會很多。究竟如何平衡優點與缺點，取決于你自己)。',
    'flag' => 1,
    'categ' => 6,
    'order' => 1,
),
118 => 
array (
    'id' => 351,
    'link_id' => 51,
    'lang_id' => 28,
    'type' => 'item',
    'question' => '限制上傳速度',
    'answer' => '上傳速度將從以下兩種方式顯著影響下載速度：
<ul>
<li>Bittorrent的同伴傾向于回饋那些給它們提上傳的同伴。這就意味着如果A和B正在同時下載一個檔案，而A又在高速向B傳輸資料，那么B將會嘗試着回報A。因此高速上傳將導致高速下載。</li>

<li>由于TCP協定的工作方式，當A正從B下載某些東西的時候，A必須不停地向B傳送成功收到B所傳送來的資料的訊號（被稱為確認──ACK ── 某種“已收到”的資訊)。如果A沒有傳送ACK訊號，那么B將暫停向A傳送資料并且進入等候狀態。如果A正在全速上傳，很有可能沒有用來傳送ACK訊號的頻寬，因此ACK訊號被耽擱。在這種情況下，過高速度的上傳導致了低速的下載。</li>
</ul>
實際的效果是以上兩個原因的結合。上傳速度應該在保證ACK能正常傳送的前提下被設得儘量高。 <b>一個很好的實踐理論是保持上傳速度在理論上限的80%。</b>你也可以細細研究什么是最適合你的速度（同時也請記住高速上傳對于你的分享率很有輔助說明）。<br />
<br />
如果你正在執行一個以上的種子工作，你應該考慮的是全域上傳速度。某些用戶端能限制了全域上傳速度，其他的則能對每一個種子分別進行設定。了解你的用戶端。如果你正在使用你的電腦做別的某些應用（比方說上網或者從FTP拖東西），也需考慮一下它們對于全域上傳的影響。',
'flag' => 1,
'categ' => 6,
'order' => 2,
),
119 => 
array (
'id' => 352,
'link_id' => 52,
'lang_id' => 28,
'type' => 'item',
'question' => '限制同時連線數',
'answer' => '某些作業系統（例如Windows 9X）對于大量連線數支援不完善，甚至有可能因此而崩潰。也有一些家用路線（尤其當執行着NAT以及/或者開啟防火牆狀態檢查服務時）會因大量連線數而變得很慢或者崩潰。對于連線數沒有固定的最佳值，你可以試試看60或者100。記住這些連線數是累加的，所以如果你的用戶端執行了兩個階段作業，這兩個數字要加在一起。',
'flag' => 1,
'categ' => 6,
'order' => 3,
),
120 => 
array (
'id' => 353,
'link_id' => 53,
'lang_id' => 28,
'type' => 'item',
'question' => '限制同時上傳數',
'answer' => '上傳數難道和連線數有什么不一樣嗎？是的，兩者是不同的。連線數限制了你的用戶端所能對話以及/或者下載的同伴數。上傳數則限制了你的用戶端實際能上傳的同伴數。理想的數值一般要比連線數低許多，并且與你的物理頻寬高度相關。',
'flag' => 1,
'categ' => 6,
'order' => 4,
),
121 => 
array (
'id' => 354,
'link_id' => 54,
'lang_id' => 28,
'type' => 'item',
'question' => '那怎么辦？等待一會吧',
'answer' => '如我們在上面所解譯的，在BT下載過程中一個同伴更加傾向于回報那些上傳給它的同伴。當你開始一個新的下載工作時，你沒有檔案片斷提高給其他同伴，因此他們通常會無視你。這使得開始階段的下載非常緩慢，由其是當你所連線到的同伴只有很少是（或者沒有）做種者。一旦你獲得了一些能用于共用的檔案片斷，下載速度會有所改觀。',
'flag' => 1,
'categ' => 6,
'order' => 5,
),
122 => 
array (
'id' => 355,
'link_id' => 55,
'lang_id' => 28,
'type' => 'item',
'question' => '為什么在我下載的時候瀏覽網頁速度如此之慢？',
'answer' => '你的下載頻寬是有限的。如果你正在下載一個速度很快的種子，它几乎肯定將占滿你的下載頻寬。這是瀏覽網頁顯然就會變慢。很多BT用戶端允許你限制下載速度，你可以試試。<br />
<br />
瀏覽網頁只是作為舉例之一，同樣的問題也會出現在游戲，即時通訊軟體上......',
'flag' => 1,
'categ' => 6,
'order' => 6,
),
123 => 
array (
'id' => 356,
'link_id' => 56,
'lang_id' => 28,
'type' => 'item',
'question' => '什么是代理？',
'answer' => '基本上說，代理就是中間人。當你通過代理存取某個網站的時候，你的要求將首先被傳送到代理處，然后由代理轉寄到你所要求的網站，而不是你直接連通到網站。下面是几種典型的代理分類（朮語不是那么的規格）：<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" valign="top" width="100">&nbsp;透通代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">透通代理在用戶端無需配置。他將自動把80通訊埠的資訊重導到代理(有時候也作為非匿名代理的同義詞)；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;顯式代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">瀏覽器必須經過配置才能使用代理；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;匿名代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">代理將不會把用戶記號傳送至伺服器端 (HTTP_X_FORWARDED_FOR頭旗標將不被傳送。伺服器也因此看不到你的IP)；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;高度匿名代理</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">代理將不會把用戶記號或代理記號傳送至伺服器端 (HTTP_X_FORWARDED_FOR和HTTP_VIA and HTTP_PROXY_CONNECTION頭旗標將不被傳送。伺服器看不到你的IP，甚至不知道你正在使用代理)；</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;公用</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">(不言自明了)。</td>
</tr>
</table>
<br />
透通代理可能是匿名的，也可能是非匿名的，且匿名也有相應的等級。',
'flag' => 1,
'categ' => 7,
'order' => 1,
),
124 => 
array (
'id' => 357,
'link_id' => 57,
'lang_id' => 28,
'type' => 'item',
'question' => '我該怎樣發現自己正處于一個（透通的/匿名的）代理后面？',
'answer' => '試試看<a href="http://proxyjudge.org" class="faqlink">ProxyJudge</a>。它列出了伺服器端從你這裡所收到的HTTP的頭旗標。相關的几個旗標是HTTP_CLIENT_IP，HTTP_X_FORWARDED_FOR和REMOTE_ADDR。',
'flag' => 1,
'categ' => 7,
'order' => 2,
),
125 => 
array (
'id' => 375,
'link_id' => 75,
'lang_id' => 28,
'type' => 'item',
'question' => '我的“可連線”是“否”儘管我并沒有NAT或者防火牆？',
'answer' => 'Tracker伺服器在搜尋你的真實IP方面非常有天賦。但是這需要代理髮送HTTP頭旗標HTTP_X_FORWARDED_FOR。如果你的ISP的代理沒有這么做的話，Tracker伺服器將把代理的IP當作你的用戶端的IP。所以當你登入之后，Tracker伺服器嘗試連線你的用戶端來確信你沒有使用NAT/防火牆，伺服器實際上將嘗試連線代理的IP（使用你的BT用戶端報告的接收連線通訊埠）。很自然地，代理伺服器并沒有監聽那個通訊埠。如此連線會失敗，而Tracker伺服器也因此認為你使用了NAT/防火牆。',
'flag' => 1,
'categ' => 7,
'order' => 3,
),
126 => 
array (
'id' => 401,
'link_id' => 1,
'lang_id' => 6,
'type' => 'categ',
'question' => 'Site information',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 1,
),
127 => 
array (
'id' => 402,
'link_id' => 2,
'lang_id' => 6,
'type' => 'categ',
'question' => 'User information',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 2,
),
128 => 
array (
'id' => 403,
'link_id' => 3,
'lang_id' => 6,
'type' => 'categ',
'question' => 'Stats',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 3,
),
129 => 
array (
'id' => 404,
'link_id' => 4,
'lang_id' => 6,
'type' => 'categ',
'question' => 'Uploading',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 4,
),
130 => 
array (
'id' => 405,
'link_id' => 5,
'lang_id' => 6,
'type' => 'categ',
'question' => 'Downloading',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 5,
),
131 => 
array (
'id' => 406,
'link_id' => 6,
'lang_id' => 6,
'type' => 'categ',
'question' => 'How can I improve my download speed?',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 6,
),
132 => 
array (
'id' => 407,
'link_id' => 7,
'lang_id' => 6,
'type' => 'categ',
'question' => 'My ISP uses a transparent proxy. What should I do?',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 7,
),
133 => 
array (
'id' => 408,
'link_id' => 8,
'lang_id' => 6,
'type' => 'categ',
'question' => 'Why can\'t I connect? Is the site blocking me?',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 8,
),
134 => 
array (
'id' => 409,
'link_id' => 9,
'lang_id' => 6,
'type' => 'categ',
'question' => 'What if I can\'t find the answer to my problem here?',
'answer' => '',
'flag' => 1,
'categ' => 0,
'order' => 9,
),
135 => 
array (
'id' => 410,
'link_id' => 10,
'lang_id' => 6,
'type' => 'item',
'question' => 'What is this bittorrent all about anyway? How do I get the files?',
'answer' => 'Check out <a class="faqlink" href="http://www.btfaq.com/">Brian\'s BitTorrent FAQ and Guide</a>.',
'flag' => 1,
'categ' => 1,
'order' => 1,
),
136 => 
array (
'id' => 411,
'link_id' => 11,
'lang_id' => 6,
'type' => 'item',
'question' => 'Where does the donated money go?',
'answer' => 'All donated money goes to the cost of server that this tracker is on.',
'flag' => 1,
'categ' => 1,
'order' => 2,
),
137 => 
array (
'id' => 412,
'link_id' => 12,
'lang_id' => 6,
'type' => 'item',
'question' => 'Where can I get a copy of the source code?',
'answer' => 'This tracker is powered by NexusPHP. If you like to use NexusPHP to power your tracker, <a class="faqlink" href="aboutnexus.php">Contact Us</a>.<br />
The first stage of NexusPHP forks from TBSource. You may find more information about TBSource at <a class="faqlink" href="http://www.tbdev.net/">TBDev.net</a>.',
'flag' => 1,
'categ' => 1,
'order' => 3,
),
138 => 
array (
'id' => 413,
'link_id' => 13,
'lang_id' => 6,
'type' => 'item',
'question' => 'I registered an account but did not receive the confirmation e-mail!',
'answer' => 'One possible reason may be that the network around the tracker has encountered some problems. You can use <a href="confirm_resend.php" class="faqlink">this form</a> ask the tracker to resend confirmation e-mail.<br />
Typically registered users would be deleted after 24 hours if not confirmed, so you may try again the next day. Note though that if you didn\'t receive the email the first time it will probably not succeed the second time either so you should really try another email address.',
'flag' => 1,
'categ' => 2,
'order' => 1,
),
139 => 
array (
'id' => 414,
'link_id' => 14,
'lang_id' => 6,
'type' => 'item',
'question' => 'I\'ve lost my user name or password! Can you send it to me?',
'answer' => 'Please use <a class="faqlink" href="recover.php">this form</a> to have the login details mailed back to you.',
'flag' => 1,
'categ' => 2,
'order' => 2,
),
140 => 
array (
'id' => 415,
'link_id' => 15,
'lang_id' => 6,
'type' => 'item',
'question' => 'Can you rename my account? ',
'answer' => 'No, we do not rename accounts. Don\'t ask for it.',
'flag' => 1,
'categ' => 2,
'order' => 3,
),
141 => 
array (
'id' => 416,
'link_id' => 16,
'lang_id' => 6,
'type' => 'item',
'question' => 'Can you delete my (confirmed) account?',
'answer' => 'No, we don\'t delete accounts. Don\'t ask for it.',
'flag' => 1,
'categ' => 2,
'order' => 4,
),
142 => 
array (
'id' => 417,
'link_id' => 17,
'lang_id' => 6,
'type' => 'item',
'question' => 'So, what\'s MY ratio?',
'answer' => 'It\'s on the left-top of pages.<br />
<br />
<img src="pic/ratio.png" alt="ratio" />
<br />
<br />
It\'s important to distinguish between your overall ratio and the individual ratio on each torrent you may be seeding or leeching. The overall ratio takes into account the total uploaded and downloaded from your account since you joined the site. The individual ratio takes into account those values for each torrent.
<br />
<br />
You may see two symbols instead of a number: "Inf.", which is just an abbreviation for Infinity, and means that you have downloaded 0 bytes while uploading a non-zero amount (ul/dl becomes infinity); "---", which should be read as "non-available", and shows up when you have both downloaded and uploaded 0 bytes (ul/dl = 0/0 which is an indeterminate amount).
',
'flag' => 1,
'categ' => 2,
'order' => 5,
),
143 => 
array (
'id' => 418,
'link_id' => 18,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why is my IP displayed on my details page?',
'answer' => 'Only you and the site moderators can view your IP address and email. Regular users do not see that information.',
'flag' => 1,
'categ' => 2,
'order' => 6,
),
144 => 
array (
'id' => 421,
'link_id' => 21,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why am I listed as not connectable? (And why should I care?)',
'answer' => 'The tracker has determined that you are firewalled or NATed and cannot accept incoming connections. 
<br />
<br />
This means that other peers in the swarm will be unable to connect to you, only you to them. Even worse, if two peers are both in this state they will not be able to connect at all. This has obviously a detrimental effect on the overall speed. 
<br />
<br />
The way to solve the problem involves opening the ports used for incoming connections (the same range you defined in your client) on the firewall and/or configuring your NAT server to use a basic form of NAT for that range instead of NAPT (the actual process differs widely between different router models. Check your router documentation and/or support forum. You will also find lots of information on the subject at <a class="faqlink" href="http://portforward.com/">PortForward</a>).





',
'flag' => 1,
'categ' => 2,
'order' => 9,
),
145 => 
array (
'id' => 422,
'link_id' => 22,
'lang_id' => 6,
'type' => 'item',
'question' => 'What are the different user classes?',
'answer' => '<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="200" valign="top">&nbsp; <b class="Peasant_Name">Peasant</b></td>
<td class="embedded" width="5">&nbsp;</td>
<td class="embedded"> Demoted users. They must improve their ratio within 30 days or they will be banned. Cannot post funbox item, apply for links or upload subtitles.
</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">The default class of new members. may upload torrents between 12:00, Saturday and 23:59 Sunday of every week.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="PowerUser_Name">Power User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Get a invitation. Can upload torrents, view NFO files, view user list, ask for reseed, send invitation, access Power User and External Trackers forums, view Top 10, view other users\' torrent history (if user\'s privacy level is not set \'strong\'), delete subtitle uploaded by oneself.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="EliteUser_Name">Elite User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded"><b class="EliteUser_Name">Elite User</b> or above would never be deleted if parked.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="CrazyUser_Name">Crazy User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Get two invitations. Can be anonymous when seeding/leeching/uploading. </td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="InsaneUser_Name">Insane User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Can view general logs.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="VeteranUser_Name">Veteran User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Get three invitations. Can view other users\' history of comments and forum posts. <b class="VeteranUser_Name">Veteran User</b> or above would never be deleted whether parked or not.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="ExtremeUser_Name">Extreme User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Can update outdated external information and access Extreme User forum.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="UltimateUser_Name">Ultimate User</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Get 5 invitations.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="NexusMaster_Name">Nexus Master</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Get 10 invitations.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <img class="star" src="pic/trans.gif" alt="Star" /></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Has donated money to this tracker.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="VIP_Name">VIP</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">Same privileges as <b class="NexusMaster_Name">Nexus Master</b> and is considered an Elite Member of this tracker. Immune to automatic demotion.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="Retiree_Name">Retiree</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Former staff members.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b class="User_Name">Other</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Customized title. </td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="Uploader_Name">Uploader</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Dedicated uploader, immune to automatic demotion. Can view who anonymous ones are.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="Moderator_Name">Moderator</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded" valign="top">Can view staffbox and reportbox, manage funbox and polls, edit and delete any uploaded torrent, manage offers, manage forum posts and user comments, view confidential logs, delete any uploaded subtitle, manage code updates and chronicles at logs, view users\' invitation history, change general user account information. <b>Cannot</b> manage links,recent news or forums. <b>Cannot</b> set torrents sticky or on promotion.<b>Cannot</b> view users\' confidential information (e.g. IP address and Email address). <b>Cannot</b> delete user account.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="Administrator_Name">Administrator</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Other than changing site settings and managing donation, can do just about anything.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="SysOp_Name">SysOp</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">Dedicated site developer. Except managing donation, can do anything (including changing site settings)</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp; <b  class="StaffLeader_Name">Staff Leader</b></td>
<td class="embedded">&nbsp;</td>
<td class="embedded">The boss. Can do anything.</td>
</tr>
</table>',
'flag' => 1,
'categ' => 2,
'order' => 10,
),
146 => 
array (
'id' => 423,
'link_id' => 23,
'lang_id' => 6,
'type' => 'item',
'question' => 'How does this promotion thing work anyway?',
'answer' => '<table cellspacing=\\\\\\\\\\\\\\"3\\\\\\\\\\\\\\" cellpadding=\\\\\\\\\\\\\\"0\\\\\\\\\\\\\\">
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" width=\\\\\\\\\\\\\\"200\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"Peasant_Name\\\\\\\\\\\\\\">Peasant</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" width=\\\\\\\\\\\\\\"5\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">User would be demoted to this class under any of the following circumstances:<br />
1.Downloaded more than 50 GB and with ratio below 0.4<br />
2.Downloaded more than 100 GB and with ratio below 0.5<br />
3.Downloaded more than 200 GB and with ratio below 0.6<br />
4.Downloaded more than 400 GB and with ratio below 0.7<br />
5.Downloaded more than 800 GB and with ratio below 0.8</td>
</tr>

<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"PowerUser_Name\\\\\\\\\\\\\\">Power User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 4 weeks, have downloaded at least 50GB and have a ratio at or above 1.05. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 0.95 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"EliteUser_Name\\\\\\\\\\\\\\">Elite User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 8 weeks, have downloaded at least 120GB and have a ratio at or above 1.55. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 1.45 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"CrazyUser_Name\\\\\\\\\\\\\\">Crazy User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 15 weeks, have downloaded at least 300GB and have a ratio at or above 2.05. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 1.95 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"InsaneUser_Name\\\\\\\\\\\\\\">Insane User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 25 weeks, have downloaded at least 500GB and have a ratio at or above 2.55. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 2.45 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"VeteranUser_Name\\\\\\\\\\\\\\">Veteran User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 40 weeks, have downloaded at least 750GB and have a ratio at or above 3.05. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 2.95 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"ExtremeUser_Name\\\\\\\\\\\\\\">Extreme User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 60 weeks, have downloaded at least 1TB and have a ratio at or above 3.55. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 3.45 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"UltimateUser_Name\\\\\\\\\\\\\\">Ultimate User</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 80 weeks, have downloaded at least 1.5TB and have a ratio at or above 4.05. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 3.95 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"NexusMaster_Name\\\\\\\\\\\\\\">Nexus Master</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Must have been a member for at least 100 weeks, have downloaded at least 3TB and have a ratio at or above 4.55. The promotion is automatic when these conditions are met. <br />
Note that you will be automatically demoted from this status if your ratio drops below 4.45 at any time.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <img class=\\\\\\\\\\\\\\"star\\\\\\\\\\\\\\" src=\\\\\\\\\\\\\\"pic/trans.gif\\\\\\\\\\\\\\" alt=\\\\\\\\\\\\\\"Star\\\\\\\\\\\\\\" /></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Just donate, see <a class=\\\\\\\\\\\\\\"faqlink\\\\\\\\\\\\\\" href=\\\\\\\\\\\\\\"donate.php\\\\\\\\\\\\\\">here</a> for the details.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"VIP_Name\\\\\\\\\\\\\\">VIP</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">Assigned by mods at their discretion to users they feel contribute something special to the site. (Anyone begging for VIP status will be automatically disqualified.)</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"User_Name\\\\\\\\\\\\\\">Other</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Customized title. Exchanged at bonus system or granted by admins.</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"Uploader_Name\\\\\\\\\\\\\\">Uploader</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Appointed by Admins/SysOp/Staff Leader (see the \\\\\\\\\\\\\\\'Uploading\\\\\\\\\\\\\\\' section for conditions).</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b class=\\\\\\\\\\\\\\"Retiree_Name\\\\\\\\\\\\\\">Retiree</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">Granted by Admins/SysOp/Staff Leader</td>
</tr>
<tr>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\" valign=\\\\\\\\\\\\\\"top\\\\\\\\\\\\\\">&nbsp; <b  class=\\\\\\\\\\\\\\"Moderator_Name\\\\\\\\\\\\\\">Moderator</b></td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">&nbsp;</td>
<td class=\\\\\\\\\\\\\\"embedded\\\\\\\\\\\\\\">You don\\\\\\\\\\\\\\\'t ask us, we\\\\\\\\\\\\\\\'ll ask you!</td>
</tr>
</table>',
'flag' => 0,
'categ' => 2,
'order' => 11,
),
147 => 
array (
'id' => 425,
'link_id' => 25,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why can\'t my friend become a member?',
'answer' => 'There is a users limit (it is list at Home -&gt; Tracker Statistics -&gt; Limit). When that number is reached we stop accepting new members. Accounts inactive (i.e. not logged in for a long time) are automatically deleted, so keep trying.<br />
When are inactive user accounts deleted? See <a class="faqlink" href="rules.php">Rules</a>',
'flag' => 1,
'categ' => 2,
'order' => 13,
),
148 => 
array (
'id' => 426,
'link_id' => 26,
'lang_id' => 6,
'type' => 'item',
'question' => 'How do I add an avatar to my profile?',
'answer' => 'First, find an image that you like, and that is within the <a class="faqlink" href="rules.php">rules</a>. Then you will have to find a place to host it, such as our own <a class="faqlink" href="bitbucket-upload.php">BitBucket</a>. To lighten tracker\'s load, we recommend you upload it to other websites and copy the URL you were given when uploading it to the Avatar URL field in <a class="faqlink" href="usercp.php?action=personal">UserCP</a>.<br />
<br />
Please do not make a post just to test your avatar. If everything is all right you\'ll see it in your details page. ',
'flag' => 1,
'categ' => 2,
'order' => 14,
),
149 => 
array (
'id' => 427,
'link_id' => 27,
'lang_id' => 6,
'type' => 'item',
'question' => 'Most common reason for stats not updating',
'answer' => '<ul>
<li>The server is overloaded and unresponsive. Just try to keep the session open until the server responds again. (Flooding the server with consecutive manual updates is not recommended.)</li>
<li>You are using a faulty client. If you want to use an experimental or CVS version you do it at your own risk.</li>
</ul>',
'flag' => 1,
'categ' => 3,
'order' => 1,
),
150 => 
array (
'id' => 428,
'link_id' => 28,
'lang_id' => 6,
'type' => 'item',
'question' => 'Best practices',
'answer' => '<ul>
<li>If a torrent you are currently leeching/seeding is not listed on your detail page, just wait or force a manual update.</li>
<li>Make sure you exit your client properly, so that the tracker receives "event=completed".</li>
<li>If the tracker is down, do not stop seeding. As long as the tracker is back up before you exit the client the stats should update properly.</li>
</ul>',
'flag' => 1,
'categ' => 3,
'order' => 2,
),
151 => 
array (
'id' => 429,
'link_id' => 29,
'lang_id' => 6,
'type' => 'item',
'question' => 'May I use any bittorrent client?',
'answer' => 'No. According to tests of common bittorrent clients by <a class="faqlink" href="aboutnexus.php">NexusPHP</a>, we allowed <b>only</b> the following bittorrent clients.<br />
The test report by <a class="faqlink" href="aboutnexus.php">NexusPHP</a> is <a class="faqlink" href="http://www.nexusphp.com/wiki/%E5%AE%A2%E6%88%B7%E7%AB%AF%E6%B5%8B%E8%AF%95%E6%8A%A5%E5%91%8A">here</a>. 
<br />
<b>Windows:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2 and later versions</li>
<li><a class="faqlink" href="http://www.utorrent.com">uTorrent</a>: 1.6.1, 1.7.5, 1.7.6, 1.7.7, 1.8Beta(Build 10364), 2.0(Build 17624) and later versions</li>
<li><a class="faqlink" href="http://www.bittorrent.com">BitTorrent</a>: 6.0.1, 6.0.2, 6.0.3 and later versions</li>
<li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6 and later versions</li>
<li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0 and later versions</li>
</ul>
<b>Linux:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2 and later versions</li>
<li><a class="faqlink" href="http://deluge-torrent.org">Deluge</a>: 0.5.9.1, 1.1.6 and later versions</li>
<li><a class="faqlink" href="http://rufus.sourceforge.net">Rufus</a>: 0.6.9, 0.7.0 and later versions</li>
<li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21 and later versions</li>
<li><a class="faqlink" href="http://libtorrent.rakshasa.no">rTorrent</a>: 0.8.0(with libtorrent 0.12.0 or later) and later versions</li>
<li><a class="faqlink" href="http://www.rahul.net/dholmes/ctorrent/">Enhanced CTorrent</a>: 3.3.2 and later versions</li>
</ul>
<b>MacOS X:</b>
<ul>
<li><a class="faqlink" href="http://azureus.sourceforge.net">Azureus</a>: 2.5.0.4, 3.0.5.0, 3.0.5.2 and later versions</li>
<li><a class="faqlink" href="http://www.transmissionbt.com">Transmission</a>: 1.21 and later versions</li>
<li><a class="faqlink" href="http://sourceforge.net/projects/bitrocket/">BitRocket</a>: 0.3.3(32) and later versions</li>
</ul>
<b>Symbian (For Testing Only):</b>
<ul>
<li><a class="faqlink" href="http://amorg.aut.bme.hu/projects/symtorrent">SymTorrent</a>: 1.41 and later versions</li>
</ul>
<br />

<b>Support for https:</b>
<ul>
<li>uTorrent 1.61: cannot parse tracker https url, and marks itself as uTorrent 1.5</li>
<li>Rufus: no support for https, and development ceased for years.</li>
<li>rtorrent: needs to add SSL certification manually, see User Guide at its official site.</li>
</ul>

<br />

Please do not use any beta or testing version of bittorrent clients, e.g. uTorrent 1.8.0B. To get the best downloading experience, we highly recommend latest stable version of <a class="faqlink" href="http://www.utorrent.com/download.php">uTorrent</a> and <a class="faqlink" href="http://azureus.sourceforge.net/download.php">Azureus</a>.',
'flag' => 1,
'categ' => 5,
'order' => 3,
),
152 => 
array (
'id' => 430,
'link_id' => 30,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why is a torrent I\'m leeching/seeding listed several times in my profile?',
'answer' => 'If for some reason (e.g. PC crash, or frozen client) your client exits improperly and you restart it, it will have a new peer_id, so it will show as a new torrent. The old one will never receive a "event=completed" or "event=stopped" and will be listed until some tracker timeout. Just ignore it, it will eventually go away.',
'flag' => 1,
'categ' => 3,
'order' => 4,
),
153 => 
array (
'id' => 431,
'link_id' => 31,
'lang_id' => 6,
'type' => 'item',
'question' => 'I\'ve finished or cancelled a torrent. Why is it still listed in my profile?',
'answer' => 'Some clients, notably TorrentStorm and Nova Torrent, do not report properly to the tracker when canceling or finishing a torrent. In that case the tracker will keep waiting for some message - and thus listing the torrent as seeding or leeching - until some timeout occurs. Just ignore it, it will eventually go away.',
'flag' => 1,
'categ' => 3,
'order' => 5,
),
154 => 
array (
'id' => 433,
'link_id' => 33,
'lang_id' => 6,
'type' => 'item',
'question' => 'Multiple IPs (Can I login from different computers?)',
'answer' => 'Yes, the tracker is capable of following sessions from different IPs for the same user. You may access the site and seed/leech simultaneously from as many computers as you want with the same account.<br />
However, there is a limit for a single torrent. Per torrent 3 simultaneous connections are permitted per user, and in case of leeching only 1, which means you can leech a torrent from one location only at a time.',
'flag' => 1,
'categ' => 3,
'order' => 7,
),
155 => 
array (
'id' => 436,
'link_id' => 36,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why can\'t I upload torrents?',
'answer' => 'See <a class="faqlink" href="rules.php">Rules</a>.',
'flag' => 1,
'categ' => 4,
'order' => 1,
),
156 => 
array (
'id' => 437,
'link_id' => 37,
'lang_id' => 6,
'type' => 'item',
'question' => 'What criteria must I meet before I can join the Uploader team?',
'answer' => 'You must:
<ul>
<li>have steady access to resources.</li>
<li>upload not less than 5 torrents per week.</li>
</ul>
You must be able to provide releases that:
<ul>
<li>are not older than 7 days</li>
<li>you\'ll be able to seed, or make sure are well-seeded, for at least 24 hours.</li>
<li>Also, you should have at least 2MBit upload bandwith.</li>
</ul>
If you think you can match these criteria do not hesitate to <a class="faqlink" href="contactstaff.php">contact the staff</a>.<br />
<b>Remember!</b> Write your application carefully! Be sure to include your upload speed and what kind of stuff you\'re planning to upload.<br />
Only well written letters with serious intent will be considered.',
'flag' => 1,
'categ' => 4,
'order' => 2,
),
157 => 
array (
'id' => 438,
'link_id' => 38,
'lang_id' => 6,
'type' => 'item',
'question' => 'Can I upload your torrents to other trackers?',
'answer' => 'No. We are a closed, limited-membership community. Only registered users can use the tracker. Posting our torrents on other trackers is useless, since most people who attempt to download them will be unable to connect with us. This generates a lot of frustration and bad-will against us, and will therefore not be tolerated.<br />
<br />
Complaints from other sites\' administrative staff about our torrents being posted on their sites will result in the banning of the users responsible.
<br />
<br />
However, the files you download from us are yours to do as you please (except those marked as <b>EXCLUSIVE</b> by the uploader). You can always create another torrent, pointing to some other tracker, and upload it to the site of your choice.',
'flag' => 1,
'categ' => 4,
'order' => 3,
),
158 => 
array (
'id' => 439,
'link_id' => 39,
'lang_id' => 6,
'type' => 'item',
'question' => 'How do I use the files I\'ve downloaded?',
'answer' => 'Check out <a class="faqlink" href="formats.php">this guide</a>.',
'flag' => 1,
'categ' => 5,
'order' => 1,
),
159 => 
array (
'id' => 440,
'link_id' => 40,
'lang_id' => 6,
'type' => 'item',
'question' => 'Downloaded a movie and don\'t know what CAM/TS/TC/SCR means?',
'answer' => 'Check out <a class="faqlink" href="videoformats.php">this guide</a>.',
'flag' => 1,
'categ' => 5,
'order' => 2,
),
160 => 
array (
'id' => 441,
'link_id' => 41,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why did an active torrent suddenly disappear?',
'answer' => 'There may be three reasons for this:<br />
(<b>1</b>) The torrent may have been against the site <a class="faqlink" href="rules.php">rules</a>.
<br />
(<b>2</b>) The uploader may have deleted it because it was a bad release. A replacement will probably be uploaded to take its place.<br />
(<b>3</b>) Torrents are automatically deleted after being dead for a long time.',
'flag' => 1,
'categ' => 5,
'order' => 3,
),
161 => 
array (
'id' => 442,
'link_id' => 42,
'lang_id' => 6,
'type' => 'item',
'question' => 'How do I resume a broken download or reseed something?',
'answer' => 'Open the .torrent file. When your client asks you for a location, choose the location of the existing file(s) and it will resume/reseed the torrent.',
'flag' => 1,
'categ' => 5,
'order' => 4,
),
162 => 
array (
'id' => 443,
'link_id' => 43,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why do my downloads sometimes stall at 99%?',
'answer' => 'The more pieces you have, the harder it becomes to find peers who have pieces you are missing. That is why downloads sometimes slow down or even stall when there are just a few percent remaining. Just be patient and you will, sooner or later, get the remaining pieces.',
'flag' => 1,
'categ' => 5,
'order' => 5,
),
163 => 
array (
'id' => 444,
'link_id' => 44,
'lang_id' => 6,
'type' => 'item',
'question' => 'What are these "a piece has failed a hash check" messages? ',
'answer' => 'Bittorrent clients check the data they receive for integrity. When a piece fails this check it is automatically re-downloaded. Occasional hash fails are a common occurrence, and you shouldn\'t worry.<br />
<br />
Some clients have an (advanced) option/preference to \'kick/ban clients that send you bad data\' or similar. It should be turned on, since it makes sure that if a peer repeatedly sends you pieces that fail the hash check it will be ignored in the future.',
'flag' => 1,
'categ' => 5,
'order' => 6,
),
164 => 
array (
'id' => 445,
'link_id' => 45,
'lang_id' => 6,
'type' => 'item',
'question' => 'The torrent is supposed to be 100MB. How come I downloaded 120MB? ',
'answer' => 'See the hash fails topic. If your client receives bad data it will have to re-download it, therefore the total downloaded may be larger than the torrent size. Make sure the "kick/ban" option is turned on to minimize the extra downloads.',
'flag' => 1,
'categ' => 5,
'order' => 7,
),
165 => 
array (
'id' => 446,
'link_id' => 46,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why do I get a "Your ratio is too low! You need to wait xx h to start" error?',
'answer' => 'From the time that each <b>new</b> torrent is uploaded to the tracker, there is a period of time that some users must wait before they can download it.<br />
This delay in downloading will only affect users with a low ratio and downloaded amount above 10 GB.<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.4</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">delay of</td>
<td class="embedded" width="100">24h</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.5</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">delay of</td>
<td class="embedded" width="100">12h</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.6</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">delay of</td>
<td class="embedded" width="100">6h</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.8</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">delay of</td>
<td class="embedded" width="100">3h</td>
</tr>
</table>',
'flag' => 0,
'categ' => 5,
'order' => 8,
),
166 => 
array (
'id' => 447,
'link_id' => 47,
'lang_id' => 6,
'type' => 'item',
'question' => 'Why do I get a "Port xxxx is blacklisted" error?',
'answer' => 'Your client is reporting to the tracker that it uses one of the default bittorrent ports (6881-6889) or any other common p2p port for incoming connections.<br />
<br />
We does not allow clients to use ports commonly associated with p2p protocols. The reason for this is that it is a common practice for ISPs to throttle those ports (that is, limit the bandwidth, hence the speed). <br />
<br />
The blocked ports list include the following:<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">Direct Connect</td>
<td class="embedded" width="100">411 - 413</td>
</tr>
<tr>
<td class="embedded" width="100">BitTorrent</td>
<td class="embedded" width="100">6881 - 6889</td>
</tr>
<tr>
<td class="embedded" width="100">Kazza</td>
<td class="embedded" width="100">1214</td>
</tr>
<tr>
<td class="embedded" width="100">Gnutella</td>
<td class="embedded" width="100">6346 - 6347</td>
</tr>
<tr>
<td class="embedded" width="100">Emule</td>
<td class="embedded" width="100">4662</td>
</tr>
<tr>
<td class="embedded" width="100">WinMX</td>
<td class="embedded" width="100">6699</td>
</tr>
</table>
<br />
In order to use our tracker you must configure your client to use any port range that does not contain those ports (a range within the region 49152 through 65535 is preferable,
cf. <a class="faqlink" href="http://www.iana.org/assignments/port-numbers">IANA</a>). Notice that some clients, like Azureus 2.0.7.0 or higher, use a single port for all torrents, while most others use one port per open torrent. The size of the range you choose should take this into account (typically less than 10 ports wide. There is no benefit whatsoever in choosing a wide range, and there are possible security implications). <br />
<br />
These ports are used for connections between peers, not client to tracker. Therefore this change will not interfere with your ability to use other trackers (in fact it should <i>increase</i> your speed with torrents from any tracker, not just ours). Your client will also still be able to connect to peers that are using the standard ports. If your client does not allow custom ports to be used, you will have to switch to one that does.<br />
<br />
Do not ask us, or in the forums, which ports you should choose. The more random the choice is the harder it will be for ISPs to catch on to us and start limiting speeds on the ports we use. If we simply define another range ISPs will start throttling that range also. <br />
<br />
Finally, remember to forward the chosen ports in your router and/or open them in your
firewall, should you have them.',
    'flag' => 1,
    'categ' => 5,
    'order' => 9,
),
167 => 
array (
    'id' => 448,
    'link_id' => 48,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'What\'s this "IOError - [Errno13] Permission denied" error?',
    'answer' => 'If you just want to fix it reboot your computer, it should solve the problem. Otherwise read on.<br />
IOError means Input-Output Error, and that is a file system error, not a tracker one. It shows up when your client is for some reason unable to open the partially downloaded torrent files. The most common cause is two instances of the client to be running simultaneously: the last time the client was closed it somehow didn\'t really close but kept running in the background, and is therefore still locking the files, making it impossible for the new instance to open them.<br />
A more uncommon occurrence is a corrupted FAT. A crash may result in corruption that makes the partially downloaded files unreadable, and the error ensues. Running scandisk should solve the problem. (Note that this may happen only if you\'re running Windows 9x - which only support FAT - or NT/2000/XP with FAT formatted hard drives. NTFS is much more robust and should never permit this problem.)
',
    'flag' => 1,
    'categ' => 5,
    'order' => 10,
),
168 => 
array (
    'id' => 450,
    'link_id' => 50,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Do not immediately jump on new torrents',
'answer' => 'The download speed mostly depends on the seeder-to-leecher ratio (SLR). Poor download speed is mainly a problem with new and very popular torrents where the SLR is low.<br />
(Note: make sure you remember that you did not enjoy the low speed. Seed so that others will not endure the same.)<br />
<br />In particular, do not do it if you have a slow connection. The best speeds will be found around the half-life of a torrent, when the SLR will be at its highest. (The downside is that you will not be able to seed so much. It\'s up to you to balance the pros and cons of this.)',
    'flag' => 1,
    'categ' => 6,
    'order' => 1,
),
169 => 
array (
    'id' => 451,
    'link_id' => 51,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Limit your upload speed',
    'answer' => 'The upload speed affects the download speed in essentially two ways:
<ul>
<li>Bittorrent peers tend to favour those other peers that upload to them. This means that if A and B are leeching the same torrent and A is sending data to B at high speed then B will try to reciprocate. So due to this effect high upload speeds lead to high download speeds.</li>

<li>Due to the way TCP works, when A is downloading something from B it has to keep telling B that it received the data sent to him. (These are called acknowledgements - ACKs -, a sort of "got it!" messages). If A fails to do this then B will stop sending data and wait. If A is uploading at full speed there may be no bandwidth left for the ACKs and they will be delayed. So due to this effect excessively high upload speeds lead to low download speeds.</li>
</ul>

The full effect is a combination of the two. The upload should be kept as high as possible while allowing the ACKs to get through without delay. <b>A good thumb rule is keeping the upload at about 80% of the theoretical upload speed. </b>You will have to fine tune yours to find out what works best for you. (Remember that keeping the upload high has the additional benefit of helping with your ratio.) <br />
<br />
If you are running more than one instance of a client it is the overall upload speed that you must take into account. Some clients limit global upload speed, others do it on a per torrent basis. Know your client. The same applies if you are using your connection for anything else (e.g. browsing or ftp), always think of the overall upload speed.',
    'flag' => 1,
    'categ' => 6,
    'order' => 2,
),
170 => 
array (
    'id' => 452,
    'link_id' => 52,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Limit the number of simultaneous connections',
'answer' => 'Some operating systems (like Windows 9x) do not deal well with a large number of connections, and may even crash. Also some home routers (particularly when running NAT and/or firewall with stateful inspection services) tend to become slow or crash when having to deal with too many connections. There are no fixed values for this, you may try 60 or 100 and experiment with the value. Note that these numbers are additive, if you have two instances of a client running the numbers add up.',
    'flag' => 1,
    'categ' => 6,
    'order' => 3,
),
171 => 
array (
    'id' => 453,
    'link_id' => 53,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Limit the number of simultaneous uploads',
'answer' => 'Isn\'t this the same as above? No. Connections limit the number of peers your client is talking to and/or downloading from. Uploads limit the number of peers your client is actually uploading to. The ideal number is typically much lower than the number of connections, and highly dependent on your (physical) connection.',
    'flag' => 1,
    'categ' => 6,
    'order' => 4,
),
172 => 
array (
    'id' => 454,
    'link_id' => 54,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Just give it some time',
    'answer' => 'As explained above peers favour other peers that upload to them. When you start leeching a new torrent you have nothing to offer to other peers and they will tend to ignore you. This makes the starts slow, in particular if, by chance, the peers you are connected to include few or no seeders. The download speed should increase as soon as you have some pieces to share.',
    'flag' => 1,
    'categ' => 6,
    'order' => 5,
),
173 => 
array (
    'id' => 455,
    'link_id' => 55,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Why is my browsing so slow while leeching?',
    'answer' => 'Your download speed is always finite. If you are a peer in a fast torrent it will almost certainly saturate your download bandwidth, and your browsing will suffer. Many clients allows you to limit the download speed and try it.<br />
<br />
Browsing was used just as an example, the same would apply to gaming, IMing, etc...',
    'flag' => 1,
    'categ' => 6,
    'order' => 6,
),
174 => 
array (
    'id' => 456,
    'link_id' => 56,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'What is a proxy?',
'answer' => 'Basically a middleman. When you are browsing a site through a proxy your requests are sent to the proxy and the proxy forwards them to the site instead of you connecting directly to the site. There are several classifications (the terminology is far from standard):<br />
<br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" valign="top" width="100">&nbsp;Transparent</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">A transparent proxy is one that needs no configuration on the clients. It works by automatically redirecting all port 80 traffic to the proxy. (Sometimes used as synonymous for non-anonymous.)</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;Explicit/Voluntary</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">Clients must configure their browsers to use them.</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;Anonymous</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">The proxy sends no client identification to the server. (HTTP_X_FORWARDED_FOR header is not sent; the server does not see your IP.)</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;Highly Anonymous</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">The proxy sends no client nor proxy identification to the server. (HTTP_X_FORWARDED_FOR, HTTP_VIA and HTTP_PROXY_CONNECTION headers are not sent; the server doesn\'t see your IP and doesn\'t even know you\'re using a proxy.)</td>
</tr>
<tr>
<td class="embedded" valign="top">&nbsp;Public</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" valign="top">(Self explanatory)</td>
</tr>
</table>
<br />
A transparent proxy may or may not be anonymous, and there are several levels of anonymity.',
    'flag' => 1,
    'categ' => 7,
    'order' => 1,
),
175 => 
array (
    'id' => 457,
    'link_id' => 57,
    'lang_id' => 6,
    'type' => 'item',
'question' => 'How do I find out if I\'m behind a (transparent/anonymous) proxy?',
    'answer' => 'Try <a href="http://proxyjudge.org" class="faqlink">ProxyJudge</a>. It lists the HTTP headers that the server where it is running received from you. The relevant ones are HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR and REMOTE_ADDR.',
    'flag' => 1,
    'categ' => 7,
    'order' => 2,
),
176 => 
array (
    'id' => 475,
    'link_id' => 75,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Why am I listed as not connectable even though I\'m not NAT/Firewalled?',
    'answer' => 'The tracker is quite smart at finding your real IP, but it does need the proxy to send the HTTP header HTTP_X_FORWARDED_FOR. If your ISP\'s proxy does not then what happens is that the tracker will interpret the proxy\'s IP address as the client\'s IP address. So when you login and the tracker tries to connect to your client to see if you are NAT/firewalled it will actually try to connect to the proxy on the port your client reports to be using for incoming connections. Naturally the proxy will not be listening on that port, the connection will fail and the tracker will think you are NAT/firewalled.',
    'flag' => 1,
    'categ' => 7,
    'order' => 3,
),
177 => 
array (
    'id' => 462,
    'link_id' => 62,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Maybe my address is blacklisted?',
    'answer' => 'The site keeps a list of blocked IP addresses for banned users or attackers. This works at Apache/PHP level, and only blocks <i>logins</i> from those addresses. It should not stop you from reaching the site. In particular it does not block lower level protocols, you should be able to ping/traceroute the server even if your address is blacklisted. If you cannot then the reason for the problem lies elsewhere.<br />
<br />
If somehow your address is blocked by mistake, contact us about it.',
    'flag' => 1,
    'categ' => 8,
    'order' => 1,
),
178 => 
array (
    'id' => 463,
    'link_id' => 63,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Your ISP blocks the site\'s address',
'answer' => '(In first place, it\'s unlikely your ISP is doing so. DNS name resolution and/or network problems are the usual culprits.) 
<br />
There\'s nothing we can do. You should contact your ISP (or get a new one). Note that you can still visit the site via a proxy, follow the instructions in the relevant section. In this case it doesn\'t matter if the proxy is anonymous or not, or which port it listens to.<br />
<br />
Notice that you will always be listed as an "unconnectable" client because the tracker will be unable to check that you\'re capable of accepting incoming connections.',
    'flag' => 1,
    'categ' => 8,
    'order' => 2,
),
179 => 
array (
    'id' => 465,
    'link_id' => 65,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'You may try this',
    'answer' => 'Post in the <a class="faqlink" href="forums.php">Forums</a>, by all means. You\'ll find they are usually a friendly and helpful place, provided you follow a few basic guidelines: <ul>
<li>Make sure your problem is not really in this FAQ. There\'s no point in posting just to be sent back here. </li>
<li>Before posting read the sticky topics (the ones at the top). Many times new information that still hasn\'t been incorporated in the FAQ can be found there.</li>
<li>Help us in helping you. Do not just say "it doesn\'t work!". Provide details so that we don\'t have to guess or waste time asking. What client do you use? What\'s your OS? What\'s your network setup? What\'s the exact error message you get, if any? What are the torrents you are having problems with? The more you tell the easiest it will be for us, and the more probable your post will get a reply.</li>
<li>And needless to say: be polite. Demanding help rarely works, asking for it usually does the trick.</li></ul>',
    'flag' => 1,
    'categ' => 9,
    'order' => 1,
),
180 => 
array (
    'id' => 466,
    'link_id' => 66,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Why do I get a "Your slot limit is reached! You may at most download xx torrents at the same time" error?',
    'answer' => 'This is part of the "Slot System". The slot system is being used to limit the concurrent downloads for users that have low ratio and downloaded amount above 10 GB<br /><br />
Rules: <br />
<table cellspacing="3" cellpadding="0">
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.5</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">available slots</td>
<td class="embedded" width="40">1</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.65</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">available slots</td>
<td class="embedded" width="100">2</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.8</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">available slots</td>
<td class="embedded" width="100">3</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio below</td>
<td class="embedded" width="40">0.95</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">available slots</td>
<td class="embedded" width="100">4</td>
</tr>
<tr>
<td class="embedded" width="100">Ratio above</td>
<td class="embedded" width="40">0.95</td>
<td class="embedded" width="10">&nbsp;</td>
<td class="embedded" width="100">available slots</td>
<td class="embedded" width="100">unlimited</td>
</tr>
</table>
<br />
In all cases the seeding slots are unlimited. However if you have already filled all your available download slots and try to start seeding you will receive the same error. In this case you must free at least one download slot in order to start all your seeds and then start the download. If all your download slots are filled the system will deny any connection before validating if you want to download or seed. So first start your seeds and after that your downloads. <br />
<br /><br />
Any time, you can check your available slots in the member bar on top of the page.',
    'flag' => 0,
    'categ' => 5,
    'order' => 12,
),
181 => 
array (
    'id' => 467,
    'link_id' => 67,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'What is the passkey System? How does it work?',
    'answer' => 'The passkey system is implemented to verify if you are registered with the tracker. Every user has a personal passkey, a random key generated by the system. When a user tries to download a torrent, his personal passkey is imprinted in the tracker URL of the torrent, allowing to the tracker to identify any source connected on it. In this way, you can seed a torrent for example, at home and at your office simultaneously without any problem with the 2 different IPs.
',
    'flag' => 1,
    'categ' => 5,
    'order' => 13,
),
182 => 
array (
    'id' => 468,
    'link_id' => 68,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Why do I get an "Unknown passkey" error? ',
    'answer' => 'You will get this error, firstly if you are not registered on our tracker, or if you haven\'t downloaded the torrent to use from our webpage, when you were logged in. In this case, just register or log in and re-download the torrent.<br />
There is a chance to get this error also, at the first time you download anything as a new user, or at the first download after you reset your passkey. The reason is simply that the tracker reviews the changes in the passkeys every few minutes and not instantly. For that reason just leave the torrent running for a few minutes, and you will get eventually an OK message from the tracker.',
    'flag' => 1,
    'categ' => 5,
    'order' => 14,
),
183 => 
array (
    'id' => 469,
    'link_id' => 69,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'When do I need to reset my passkey?',
'answer' => '<ul><li>If your passkey has been leaked and other user(s) uses it to download torrents using your account. In this case, you will see torrents stated in your account that you are not leeching or seeding . </li>
<li>When your clients hangs up or your connection is terminated without pressing the stop button of your client. In this case, in your account you will see that you are still leeching/seeding the torrents even that your client has been closed. Normally these "ghost peers" will be cleaned automatically within 30 minutes, but if you want to resume your downloads and the tracker denied that due to the fact that you "already are downloading the same torrent" then you should reset your passkey and re-download the torrent, then resume it.  </li></ul>',
    'flag' => 1,
    'categ' => 5,
    'order' => 15,
),
184 => 
array (
    'id' => 470,
    'link_id' => 70,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'What is DHT? Why must I turn it off and how?',
    'answer' => 'DHT must be disabled in your client. DHT can cause your stats to be recorded incorrectly and could be seen as cheating. Anyone using this will be banned for cheating the system.
<br />
Fortunately, this tracker would parse uploaded .torrent files and automatically disable DHT. That\'s why you must re-downloaded the torrent before starting seeding.',
    'flag' => 1,
    'categ' => 5,
    'order' => 16,
),
185 => 
array (
    'id' => 471,
    'link_id' => 71,
    'lang_id' => 6,
    'type' => 'categ',
    'question' => 'How can I help translate the site language into my native language?',
    'answer' => '',
    'flag' => 1,
    'categ' => 1,
    'order' => 8,
),
186 => 
array (
    'id' => 472,
    'link_id' => 72,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'What skills do I need to do the translation?',
'answer' => 'Translate the site into another language is quite easy. You needn\'t be skilled in PHP or dynamic website design. In fact, all you need is proficient understanding of English (the default site language) and the language you plan to translate into. Maybe some basic knowledge in HTML would help.<br /><br />
Moreover, we give a detailed tutorial on how to do the translation <a href="#73" class="faqlink"><b>HERE</b></a>. And our coders would be more than pleased to answer the questions you may encounter.<br /><br />
Translate the whole site into another language would take estimated 10 hours. And extra time is needed to maintain the translation when site code is updated.<br /><br />
So, if you think you could help, feel free to <a class="faqlink" href="contactstaff.php"><b>CONTACT US</b></a>. Needless to say, you would be rewarded.',
    'flag' => 1,
    'categ' => 71,
    'order' => 1,
),
187 => 
array (
    'id' => 473,
    'link_id' => 73,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'The translation tutorial',
    'answer' => '<ul>
<li>How does multi-language work?<br />
Currently we use language files to store all the static words that a user can see on web pages. <br />
Every php code file has a corresponding language file for a certain language. And we named the language file \'lang_\' plus the filename of the php code file. i.e. the language file of the php code file \'details.php\' would be \'lang_details.php\'. <br />
We has some mechanism in php codes to read the exact language files of user\'s preferred language, and you shouldn\'t worry about that.<br /></li>
<li>What\'s in language files?<br />
In a language file is an array of strings. These strings contain all the static words that a user can see on web pages. When we need to say some words on a php code file, we call for a string in the array. And the output of the php code file, that is what users see on the web pages, would show the value of the string.<br />
Sounds dizzying? Well, you need not care about all these. All you gotta do is translate the values of the strings in the language files into another language. <b>Let\'s see an example</b>:<br /><br />
The following is the content of the English language file \'lang_users.php\', which works for the php code file \'users.php\'. <br /><br />
<img src="pic/langfileeng.png" alt="langfileeng" /><br />
If you wanna translate it into Simplified Chinese, all you need is edit the file into this:<br />
<img src="pic/langfilechs.png" alt="langfilechs" /><br />
See, in every line, the left part, that is before <i>=&gt;</i>, is the name of a string, which you shouldn\'t touch. All you need to is translate the right part, after <i>=&gt;</i>, which is the value of the string, into another language.<br />
Sometimes you need to look at the corresponding web pages to get the context meaning of some words.<br /></li>
<li>Sounds easy? See what do you need to do.<br />
If you feel like to help us, <a class="faqlink" href="aboutnexus.php#contact"><b>CONTACT US</b></a> and we will send you a pack of the English language files (or any other available language if you prefer). Received it, you can start translating the value of strings (which is in English), into another language. It should take you several hours to do the whole work. After this, send back the translated language files to us.<br />
If no bugs or hacking codes are found in testing, we would put the new language into work.<br />
Sometimes the language files need to be updated, typically adding new strings, when site codes are updated. If you feel like it, you can help maintain the language files.<br /></li>
<li><font color="red"><b>IMPORTANT</b></font><br />
The text of language files must be encoded in UTF-8. When saving files, be sure to set the character encoding to UTF-8. Otherwise mojibake may happen.</li></ul>',
    'flag' => 1,
    'categ' => 71,
    'order' => 2,
),
188 => 
array (
    'id' => 474,
    'link_id' => 74,
    'lang_id' => 6,
    'type' => 'item',
    'question' => 'Why does my client notify me of low disk space even if there is plenty left?',
    'answer' => 'Most possible reason is that the file system of your disk partitions is FAT32, which has a maximum file size limit of 4 GB. If your operation system is Windows, consider converting file system to NTFS. Check <a class="faqlink" href="http://technet.microsoft.com/en-us/library/bb456984.aspx">here</a> for details.
',
    'flag' => 1,
    'categ' => 5,
    'order' => 17,
),
));
        
        
    }
}