<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class RulesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('rules')->delete();
        
        \DB::table('rules')->insert(array (
            0 => 
            array (
                'id' => 1,
                'lang_id' => 25,
                'title' => '总则 - <font class=striking>不遵守这些将导致帐号被封！</font>',
                'text' => '[*]请不要做管理员明文禁止的事情。
[*]不允许发送垃圾信息。
[*]账号保留规则：
1.[b]Veteran User[/b]及以上等级用户会永远保留；
2.[b]Elite User[/b]及以上等级用户封存账号（在[url=usercp.php?action=personal]控制面板[/url]）后不会被删除帐号；
3.封存账号的用户连续400天不登录将被删除帐号；
4.未封存账号的用户连续150天不登录将被删除帐号；
5.没有流量的用户（即上传/下载数据都为0）连续100天不登录将被删除帐号。
[*]一切作弊的帐号会被封，请勿心存侥幸。
[*]注册多个[site]账号的用户将被禁止。
[*]不要把本站的种子文件上传到其他Tracker！(具体请看[url=faq.php#38][b]常见问题[/b][/url])
[*]第一次在论坛或服务器中的捣乱行为会受到警告，第二次您将永远无缘[site] 。',
            ),
            1 => 
            array (
                'id' => 2,
                'lang_id' => 25,
                'title' => '下载规则 - <font class=striking>违规将会失去下载权限！</font>',
                'text' => '[*]过低的分享率会导致严重的后果，包括禁止帐号。详见[url=faq.php#22]常见问题[/url]。
[*]种子促销规则：
[*]随机促销（种子上传后系统自动随机设为促销）：
[*]10%的概率成为“[color=#7c7ff6][b]50%下载[/b][/color]”；
[*]5%的概率成为“[color=#f0cc00][b]免费[/b][/color]”；
[*]5%的概率成为“[color=#aaaaaa][b]2x上传[/b][/color]”；
[*]3%的概率成为“[color=#7ad6ea][b]50%下载&2x上传[/b][/color]”；
[*]1%的概率成为“[color=#99cc66][b]免费&2x上传[/b][/color]”。
[*]文件总体积大于20GB的种子将自动成为“[color=#f0cc00][b]免费[/b][/color]”。
[*]Blu-ray Disk, HD DVD原盘将成为“[color=#f0cc00][b]免费[/b][/color]”。
[*]电视剧等每季的第一集将成为“[color=#f0cc00][b]免费[/b][/color]”。
[*]关注度高的种子将被设置为促销（由管理员定夺）。
[*]促销时限：
[*]除了“[color=#aaaaaa][b]2x上传[/b][/color]”以外，其余类型的促销限时7天（自种子发布时起）；
[*]“[color=#aaaaaa][b]2x上传[/b][/color]”无时限。
[*]所有种子在发布1个月后将自动永久成为“[color=#aaaaaa][b]2x上传[/b][/color]”。            
[*]我们也将不定期开启全站[color=#f0cc00][b]免费[/b][/color]，届时尽情狂欢吧~:mml:  :mml:  :mml:
[*]你只能使用允许的BT客户端下载本站资源。详见[url=faq.php#29]常见问题[/url]。',
            ),
            2 => 
            array (
                'id' => 4,
                'lang_id' => 25,
                'title' => '论坛总则 - <font class=striking>请遵循以下的守则，违规会受到警告！</font>',
                'text' => '[*]禁止攻击、挑动他人的言辞。
[*]禁止恶意灌水或发布垃圾信息。请勿在论坛非Water Jar版块发布无意义主题或回复（如纯表情）等。
[*]不要为了获取魔力值而大肆灌水。
[*]禁止在标题或正文使用脏话。
[*]不要讨论禁忌、政治敏感或当地法律禁止的话题。
[*]禁止任何基于种族、国家、民族、肤色、宗教、性别、年龄、性取向、身体或精神障碍的歧视性言辞。违规将导致账号永久性禁用。
[*]禁止挖坟（所有挖坟帖都要被删掉）。
[*]禁止重复发帖。
[*]请确保问题发布在相对应的板块。
[*]365天无新回复的主题将被系统自动锁定.
',
            ),
            3 => 
            array (
                'id' => 6,
                'lang_id' => 25,
                'title' => '头像使用规定 - <font class=striking>请尽量遵守以下规则</font>',
                'text' => '[*]允许的格式为.gif， .jpg， 和.png。
[*]图片大小不能超过150KB，为了统一，系统会调整头像宽度到150像素大小（浏览器会把图片调整成合适的大小，小图片将被拉伸，而过大的图片只会浪费带宽和CPU) 。
[*]请不要使用可能引起别人反感的图片，包括色情、宗教、血腥的动物/人类、宣扬某种意识形态的图片。如果你不确定某张图片是否合适，请站短管理员。',
        ),
        4 => 
        array (
            'id' => 3,
            'lang_id' => 25,
            'title' => '上传规则 - <font class=striking> 谨记: 违规的种子将不经提醒而直接删除 </font>',
            'text' => '请遵守规则。如果你对规则有任何不清楚或不理解的地方，请[url=contactstaff.php]咨询管理组[/url]。[b]管理组保留裁决的权力。[/b]

[b]上传总则[/b]
[*]上传者必须对上传的文件拥有合法的传播权。
[*]上传者必须保证上传速度与做种时间。如果在其他人完成前撤种或做种时间不足24小时，或者故意低速上传，上传者将会被警告甚至取消上传权限。
[*]对于自己发布的种子，发布者将获得双倍的上传量。
[*]如果你有一些违规但却有价值的资源，请将详细情况[url=contactstaff.php]告知管理组[/url]，我们可能破例允许其发布。

[b]上传者资格[/b]
[*]任何人都能发布资源。
[*]不过，有些用户需要先在[url=offers.php]候选区[/url]提交候选。详见常见问题中的[url=faq.php#22]相关说明[/url]。
[*]对于游戏类资源，只有[color=#DC143C][b]上传员[/b][/color]及以上等级的用户，或者是管理组特别指定的用户，才能自由上传。其他用户必须先在[url=offers.php]候选区[/url]提交候选。

[b]允许的资源和文件：[/b]
[*]高清（HD）视频，包括
[*]完整高清媒介，如蓝光（Blu-ray）原碟、HD DVD原碟等，或remux，
[*]HDTV流媒体，
[*]来源于上述媒介的高清重编码（至少为720p标准），
[*]其他高清视频，如高清DV；
[*]标清（SD）视频，只能是
[*]来源于高清媒介的标清重编码（至少为480p标准）；
[*]DVDR/DVDISO，
[*]DVDRip、CNDVDRip；
[*]无损音轨（及相应cue表单），如FLAC、Monkey\'s Audio等；
[*]5.1声道或以上标准的电影音轨、音乐音轨（DTS、DTSCD镜像等），评论音轨；
[*]PC游戏（必须为原版光盘镜像）；
[*]7日内发布的高清预告片；
[*]与高清相关的软件和文档。

[b]不允许的资源和文件：[/b]
[*]总体积小于100MB的资源；
[*]标清视频upscale或部分upscale而成的视频文件；
[*]属于标清级别但质量较差的视频文件，包括CAM、TC、TS、SCR、DVDSCR、R5、R5.Line、HalfCD等；
[*]RealVideo编码的视频（通常封装于RMVB或RM）、flv文件；
[*]单独的样片（样片请和正片一起上传）；
[*]未达到5.1声道标准的有损音频文件，如常见的有损MP3、有损WMA等；
[*]无正确cue表单的多轨音频文件；
[*]硬盘版、高压版的游戏资源，非官方制作的游戏镜像，第三方mod，小游戏合集，单独的游戏破解或补丁；
[*]RAR等压缩文件；
[*]重复（dupe）的资源（判定规则见下文）；
[*]涉及禁忌或敏感内容（如色情、敏感政治话题等）的资源；
[*]损坏的文件，指在读取或回放过程中出现错误的文件；
[*]垃圾文件，如病毒、木马、网站链接、广告文档、种子中包含的种子文件等，或无关文件。

[b]重复（dupe）判定规则：质量重于数量[/b]
[*]视频资源按来源媒介确定优先级，主要为：Blu-ray/HD DVD > HDTV > DVD > TV。同一视频高优先级版本将使低优先级版本被判定为重复。
[*]同一视频的高清版本将使标清版本被判定为重复。
[*]对于动漫类视频资源，HDTV版本和DVD版本有相同的优先级，这是一个特例。
[*]来源于相同媒介，相同分辨率水平的高清视频重编码
[*]参考“[url=forums.php?action=viewtopic&forumid=6&topicid=1520]Scene & Internal, from Group to Quality-Degree. ONLY FOR HD-resources[/url]”按发布组确定优先级；
[*]高优先级发布组发布的版本将使低优先级或相同优先级发布组发布的其他版本被判定为重复；
[*]但是，总会保留一个当前最佳画质的来源经重编码而成的DVD5大小（即4.38 GB左右）的版本；
[*]基于无损截图对比，高质量版本将使低质量版本被视为重复。
[*]来自其他区域，包含不同配音和/或字幕的blu-ray/HD DVD原盘版本不被视为重复版本。
[*]每个无损音轨资源原则上只保留一个版本，其余不同格式的版本将被视为重复。分轨FLAC格式有最高的优先级。
[*]对于站内已有的资源，
[*]如果新版本没有旧版本中已确认的错误/画质问题，或新版本的来源有更好的质量，新版本允许发布且旧版本将被视为重复；
[*]如果旧版本已经连续断种45日以上或已经发布18个月以上，发布新版本将不受重复判定规则约束。
[*]新版本发布后，旧的、重复的版本将被保留，直至断种。

[b]资源打包规则（试行）[/b]
原则上只允许以下资源打包：
[*]按套装售卖的高清电影合集（如[i]The Ultimate Matrix Collection Blu-ray Box[/i]）；
[*]整季的电视剧/综艺节目/动漫；
[*]同一专题的纪录片；
[*]7日内的高清预告片；
[*]同一艺术家的MV
[*]标清MV只允许按DVD打包，且不允许单曲MV单独发布；
[*]分辨率相同的高清MV；
[*]同一艺术家的音乐
[*]5张或5张以上专辑方可打包发布；
[*]两年内发售的专辑可以单独发布；
[*]打包时应剔除站内已有的资源，或者将它们都包括进来；
[*]分卷发售的动漫剧集、角色歌、广播剧等；
[*]发布组打包发布的资源。
打包发布的视频资源必须来源于相同类型的媒介（如蓝光原碟），有相同的分辨率水平（如720p），编码格式一致（如x264），但预告片例外。对于电影合集，发布组也必须统一。打包发布的音频资源必须编码格式一致（如全部为分轨FLAC）。打包发布后，将视情况删除相应单独的种子。
如果你对资源打包有任何不明确的地方，请[url=contactstaff.php]咨询管理组[/url]。管理组保留资源打包相关问题的解释权和处理权。

[b]例外[/b]
[*]允许发布来源于TV或是DSR的体育类的标清视频。
[*]允许发布小于100MB的高清相关软件和文档。
[*]允许发布小于100MB的单曲专辑。
[*]允许发布2.0声道或以上标准的国语/粤语音轨。
[*]允许在发布的资源中附带字幕、游戏破解与补丁、字体、包装等的扫描图。上述几种文件必须统一打包或统一不打包。
[*]允许在发布音轨时附带附赠DVD的相关文件。

[b]种子信息[/b]
所有种子都应该有描述性的标题，必要的介绍以及其他信息。以下是一份简明的规范，完整的、详尽的规范请参阅“[url=forums.php?action=viewtopic&topicid=3438&page=0#56711]种子信息填写规范与指导[/url]”。
[*]标题
[*]电影：[i][中文名] 名称 [年份] [剪辑版本] [发布说明] 分辨率 来源 [音频/]视频编码-发布组名称[/i]
例：[i]蝙蝠侠:黑暗骑士 The Dark Knight 2008 PROPER 720p BluRay x264-SiNNERS[/i]
[*]电视剧：[i][中文名] 名称 [年份] S**E** [发布说明] 分辨率 来源 [音频/]视频编码-发布组名称[/i]
例：[i]越狱 Prison Break S04E01 PROPER 720p HDTV x264-CTU[/i]
[*]音轨：[i][中文艺术家名 - 中文专辑名] 艺术家名 - 专辑名 [年份] [版本] [发布说明] 音频编码[-发布组名称][/i]
例：[i]恩雅 - 冬季降临 Enya - And Winter Came 2008 FLAC[/i]
[*]游戏：[i][中文名] 名称 [年份] [版本] [发布说明][-发布组名称][/i]
例：[i]红色警戒3:起义时刻 Command And Conquer Red Alert 3 Uprising-RELOADED[/i]
[*]副标题
[*]不要包含广告或求种/续种请求。
[*]外部信息
[*]电影和电视剧必须包含外部信息链接（如IMDb连接）地址（如果存在的话）。
[*]简介
[*]NFO图请写入NFO文件，而不是粘贴到简介里。
[*]电影、电视剧、动漫：
[*]必须包含海报、横幅或BD/HDDVD/DVD封面（如果存在的话）； 
[*]请尽可能包含画面截图或其缩略图和链接；
[*]请尽可能包含文件的详细情况，包括格式、时长、编码、码率、分辨率、语言、字幕等；
[*]请尽可能包含演职员名单以及剧情概要。
[*]体育节目：
[*]请勿在文字介绍或截图/文件名/文件大小/时长中泄漏比赛结果。
[*]音乐：
[*]必须包含专辑封面和曲目列表（如果存在的话）；
[*]PC游戏：
[*]必须包含海报或BD/HDDVD/DVD封面（如果存在的话）；
[*]请尽可能包含画面截图或其缩略图和链接。
[*]杂项
[*]请正确选择资源的类型和质量信息。
[*]注意事项
[*]管理员会根据规范对种子信息进行编辑。
[*]请勿改变或去除管理员对种子信息作出的修改（但上传者可以修正一些错误）。
[*]种子信息不符合规范的种子可能会被删除，视种子信息的规范程度而定。
[*]如果资源的原始发布信息基本符合规范，请尽量使用原始发布信息。
',
        ),
        5 => 
        array (
            'id' => 8,
            'lang_id' => 25,
            'title' => '管理守则 - <font class=striking>请慎用你的权限！</font>',
            'text' => '[*]最重要的一条：慎用你手中的权限！
[*]对于违规行为不要怕说“不”！
[*]不要公开和其他管理员冲突，一切通过私下沟通解决。
[*]不要太绝情，给违规者一个改过的机会。
[*]不要试图“打预防针”，等到人们犯错了再去纠正。
[*]尝试去改正一个不适当的帖而不是简单的关闭它。
[*]多尝试移动帖子到适合的版面而不是简单地锁帖。
[*]当处理版聊帖的时候要宽容适度。
[*]锁帖的时候请给予简单的操作理由。
[*]在屏蔽某个用户前请先站短通知他/她, 如果有所积极回应可以考虑再给2周观察期。
[*]不要禁用一个注册尚未满4周的帐户。
[*]永远记得以理服人。',
        ),
        6 => 
        array (
            'id' => 12,
            'lang_id' => 28,
            'title' => '下載規則 - <font class=striking>違規將會失去下載權限！</font> ',
            'text' => '[*]過低的分享率會導致嚴重的后果，包括禁止帳號。詳見[url=faq.php#22]常見問題[/url]。
[*]種子促銷規則：
[*]隨機促銷（種子上傳後系統自動隨機設為促銷）：
[*]10%的概率成為“[color=#7c7ff6][b]50%下載[/b][/color]”；
[*]5%的概率成為“[color=#f0cc00][b]免費[/b][/color]”；
[*]5%的概率成為“[color=#aaaaaa][b]2x上傳[/b][/color]”；
[*]3%的概率成為“[color=#7ad6ea][b]50%下載&2x上傳[/b][/color]”；
[*]1%的概率成為“[color=#99cc66][b]免費&2x上傳[/b][/color]”。
[*]檔總體積大於20GB的種子將自動成為“[color=#f0cc00][b]免費[/b][/color]”。
[*]Blu-ray Disk, HD DVD原盤將成為“[color=#f0cc00][b]免費[/b][/color]”。
[*]電視劇等每季的第一集將成為“[color=#f0cc00][b]免費[/b][/color]”。
[*]關注度高的種子將被設置為促銷（由管理員定奪）。
[*]促銷時限：
[*]除了“[color=#aaaaaa][b]2x上傳[/b][/color]”以外，其餘類型的促銷限時7天（自種子發佈時起）；
[*]“[color=#aaaaaa][b]2x上傳[/b][/color]”無時限。
[*]所有種子在發佈1個月後將自動永久成為“[color=#aaaaaa][b]2x上傳[/b][/color]”。            
[*]我們也將不定期開啟全站[color=#f0cc00][b]免費[/b][/color]，屆時盡情狂歡吧~:mml:  :mml:  :mml:
[*]你只能使用允許的BT客戶端下載本站資源。詳見[url=faq.php#29]常見問題[/url]。',
        ),
        7 => 
        array (
            'id' => 14,
            'lang_id' => 28,
            'title' => '論壇總則 - <font class=striking>請遵循以下的守則，違規會受到警告！</font> ',
            'text' => '[*]禁止攻擊、挑動他人的言辭。
[*]禁止惡意灌水或發布垃圾信息。請勿在論壇非Water Jar版塊發布無意義主題或回復（如純表情）等。
[*]不要為了獲取魔力值而大肆灌水。
[*]禁止在標題或正文使用臟話。
[*]不要討論禁忌、政治敏感或當地法律禁止的話題。
[*]禁止任何基于種族、國家、民族、膚色、宗教、性別、年齡、性取向、身體或精神障礙的歧視性言辭。違規將導致賬號永久性禁用。
[*]禁止挖墳（所有挖墳帖都要被刪掉）。
[*]禁止重復發帖。
[*]請確保問題發布在相對應的板塊。
[*]365天無新回復的主題將被系統自動鎖定。',
        ),
        8 => 
        array (
            'id' => 5,
            'lang_id' => 25,
            'title' => '评论总则 - <font class=striking>永远尊重上传者！</font>',
            'text' => '[*]无论如何，请尊重上传者！
[*]所有论坛发帖的规则同样适用于评论。
[*]如果你没有下载的意向，请不要随便发表否定性的评论。',
        ),
        9 => 
        array (
            'id' => 16,
            'lang_id' => 28,
            'title' => '頭像使用規定 - <font class=striking>請盡量遵守以下規則</font> ',
            'text' => '[*]允許的格式為.gif， .jpg， 和.png。
[*]圖片大小不能超過150KB，為了統一，系統會調整頭像寬度到150像素大小（瀏覽器會把圖片調整成合適的大小，小圖片將被拉伸，而過大的圖片只會浪費帶寬和CPU) 。
[*]請不要使用可能引起別人反感的圖片，包括色情、宗教、血腥的動物/人類、宣揚某種意識形態的圖片。如果你不確定某張圖片是否合適，請站短管理員。
',
    ),
    10 => 
    array (
        'id' => 7,
        'lang_id' => 25,
        'title' => '趣味盒规则 - <font class=striking>在娱乐中赚分</font>',
        'text' => '[*]任何用户都可在趣味盒中投放笑话、趣图、搞笑视频、Flash等有趣的内容，除了色情、禁忌、政治敏感和当地法律禁止的内容。
[*]正常情况下，一条趣味内容在发布24小时后过期。然而，如果获得的投票数超过20且其中“有趣”的比例低于25%，趣味内容将提前过期。
[*]新的趣味内容[b]只有[/b]在旧的内容过期后才能提交。
[*]若趣味内容被多数用户投票认为有趣，其发布者将得到以下奖励：
[*]票数超过25，其中认为“有趣”比例超过50%，发布者得到5个魔力值。
[*]票数超过50，其中认为“有趣”比例超过50%，发布者得到另外的5个魔力值。
[*]票数超过100，其中认为“有趣”比例超过50%，发布者得到另外的5个魔力值。
[*]票数超过200，其中认为“有趣”比例超过50%，发布者得到另外的5个魔力值。
[*]票数超过25，其中认为“有趣”比例超过75%，发布者得到10个魔力值。
[*]票数超过50，其中认为“有趣”比例超过75%，发布者得到另外的10个魔力值。
[*]票数超过100，其中认为“有趣”比例超过75%，发布者得到另外的10个魔力值。
[*]票数超过200，其中认为“有趣”比例超过75%，发布者得到另外的10个魔力值。',
    ),
    11 => 
    array (
        'id' => 11,
        'lang_id' => 28,
        'title' => '總則 - <font class=striking>不遵守這些將導致帳號被封！</font> ',
        'text' => '[*]請不要做管理員明文禁止的事情。
[*]不允許發送垃圾信息。
[*]賬號保留規則：
1.[b]Veteran User[/b]及以上等級用戶會永遠保留；
2.[b]Elite User[/b]及以上等級用戶封存賬號（在[url=usercp.php?action=personal]控制面板[/url]）后不會被刪除帳號；
3.封存賬號的用戶連續400天不登錄將被刪除帳號；
4.未封存賬號的用戶連續150天不登錄將被刪除帳號；
5.沒有流量的用戶（即上傳/下載數據都為0）連續100天不登錄將被刪除帳號。
[*]一切作弊的帳號會被封，請勿心存僥幸。
[*]注冊多個[site]賬號的用戶將匾被禁止。
[*]不要把本站的種子文件上傳到其他Tracker！(具體請看 [url=faq.php#38][b]常見問題[/b][/url])
[*]第一次在論壇或服務器中的搗亂行為會受到警告，第二次您將永遠無緣[site] 。',
    ),
    12 => 
    array (
        'id' => 13,
        'lang_id' => 28,
        'title' => '上傳規則 - <font class=striking> 謹記: 違規的種子將不經提醒而直接刪除 </font> ',
        'text' => '請遵守規則。如果你對規則有任何不清楚或不理解的地方，請[url=contactstaff.php]諮詢管理組[/url]。[b]管理組保留裁決的權力。[/b]

[b]上傳總則[/b]
[*]上傳者必須對上傳的檔擁有合法的傳播權。
[*]上傳者必須保證上傳速度與做種時間。如果在其他人完成前撤種或做種時間不足24小時，或者故意低速上傳，上傳者將會被警告甚至取消上傳許可權。
[*]對於自己發佈的種子，發佈者將獲得雙倍的上傳量。
[*]如果你有一些違規但卻有價值的資源，請將詳細情況[url=contactstaff.php]告知管理組[/url]，我們可能破例允許其發佈。

[b]上傳者資格[/b]
[*]任何人都能發佈資源。
[*]不過，有些使用者需要先在[url=offers.php]候選區[/url]提交候選。詳見常見問題中的[url=faq.php#22]相關說明[/url]。
[*]對於遊戲類資源，只有[color=#DC143C][b]上傳員[/b][/color]及以上等級的使用者，或者是管理組特別指定的用戶，才能自由上傳。其他用戶必須先在[url=offers.php]候選區[/url]提交候選。

[b]允許的資源和檔：[/b]
[*]高清（HD）視頻，包括
[*]完整高清媒介，如藍光（Blu-ray）原碟、HD DVD原碟等，或remux，
[*]HDTV流媒體，
[*]來源於上述媒介的高清重編碼（至少為720p標準），
[*]其他高清視頻，如高清DV；
[*]標清（SD）視頻，只能是
[*]來源於高清媒介的標清重編碼（至少為480p標準）；
[*]DVDR/DVDISO，
[*]DVDRip、CNDVDRip；
[*]無損音軌（及相應cue表單），如FLAC、Monkey\'s Audio等；
[*]5.1聲道或以上標準的電影音軌、音樂音軌（DTS、DTSCD鏡像等），評論音軌；
[*]PC遊戲（必須為原版光碟鏡像）；
[*]7日內發佈的高清預告片；
[*]與高清相關的軟體和文檔。

[b]不允許的資源和檔：[/b]
[*]總體積小於100MB的資源；
[*]標清視頻upscale或部分upscale而成的視頻檔；
[*]屬於標清級別但品質較差的視頻檔，包括CAM、TC、TS、SCR、DVDSCR、R5、R5.Line、HalfCD等；
[*]RealVideo編碼的視頻（通常封裝於RMVB或RM）、flv檔；
[*]單獨的樣片（樣片請和正片一起上傳）；
[*]未達到5.1聲道標準的有損音訊檔，如常見的有損MP3、有損WMA等；
[*]無正確cue表單的多軌音訊檔；
[*]硬碟版、高壓版的遊戲資源，非官方製作的遊戲鏡像，協力廠商mod，小遊戲合集，單獨的遊戲破解或補丁；
[*]RAR等壓縮檔；
[*]重複（dupe）的資源（判定規則見下文）；
[*]涉及禁忌或敏感內容（如色情、敏感政治話題等）的資源；
[*]損壞的檔，指在讀取或重播過程中出現錯誤的檔；
[*]垃圾檔，如病毒、木馬、網站連結、廣告文檔、種子中包含的種子檔等，或無關檔。

[b]重複（dupe）判定規則：品質重於數量[/b]
[*]視頻資源按來源媒介確定優先順序，主要為：Blu-ray/HD DVD > HDTV > DVD > TV。同一視頻高優先順序版本將使低優先順序版本被判定為重複。
[*]同一視頻的高清版本將使標清版本被判定為重複。
[*]對於動漫類視頻資源，HDTV版本和DVD版本有相同的優先順序，這是一個特例。
[*]來源於相同媒介，相同解析度水準的高清視頻重編碼
[*]參考“[url=forums.php?action=viewtopic&forumid=6&topicid=1520]Scene & Internal, from Group to Quality-Degree. ONLY FOR HD-resources[/url]”按發佈組確定優先順序；
[*]高優先順序發佈組發佈的版本將使低優先順序或相同優先順序發佈組發佈的其他版本被判定為重複；
[*]但是，總會保留一個當前最佳畫質的來源經重編碼而成的DVD5大小（即4.38 GB左右）的版本；
[*]基於無損截圖對比，高品質版本將使低品質版本被視為重複。
[*]來自其他區域，包含不同配音和/或字幕的blu-ray/HD DVD原盤版本不被視為重複版本。
[*]每個無損音軌資源原則上只保留一個版本，其餘不同格式的版本將被視為重複。分軌FLAC格式有最高的優先順序。
[*]對於站內已有的資源，
[*]如果新版本沒有舊版本中已確認的錯誤/畫質問題，或新版本的來源有更好的品質，新版本允許發佈且舊版本將被視為重複；
[*]如果舊版本已經連續斷種45日以上或已經發佈18個月以上，發佈新版本將不受重複判定規則約束。
[*]新版本發佈後，舊的、重複的版本將被保留，直至斷種。

[b]資源打包規則（試行）[/b]
原則上只允許以下資源打包：
[*]按套裝售賣的高清電影合集（如[i]The Ultimate Matrix Collection Blu-ray Box[/i]）；
[*]整季的電視劇/綜藝節目/動漫；
[*]同一專題的紀錄片；
[*]7日內的高清預告片；
[*]同一藝術家的MV
[*]標清MV只允許按DVD打包，且不允許單曲MV單獨發佈；
[*]解析度相同的高清MV；
[*]同一藝術家的音樂
[*]5張或5張以上專輯方可打包發佈；
[*]兩年內發售的專輯可以單獨發佈；
[*]打包時應剔除站內已有的資源，或者將它們都包括進來；
[*]分卷發售的動漫劇集、角色歌、廣播劇等；
[*]發佈組打包發佈的資源。
打包發佈的視頻資源必須來源於相同類型的媒介（如藍光原碟），有相同的解析度水準（如720p），編碼格式一致（如x264），但預告片例外。對於電影合集，發佈組也必須統一。打包發佈的音訊資源必須編碼格式一致（如全部為分軌FLAC）。打包發佈後，將視情況刪除相應單獨的種子。
如果你對資源打包有任何不明確的地方，請[url=contactstaff.php]諮詢管理組[/url]。管理組保留資源打包相關問題的解釋權和處理權。

[b]例外[/b]
[*]允許發佈來源於TV或是DSR的體育類的標清視頻。
[*]允許發佈小於100MB的高清相關軟體和文檔。
[*]允許發佈小於100MB的單曲專輯。
[*]允許發佈2.0聲道或以上標準的國語/粵語音軌。
[*]允許在發佈的資源中附帶字幕、遊戲破解與補丁、字體、包裝等的掃描圖。上述幾種檔必須統一打包或統一不打包。
[*]允許在發佈音軌時附帶附贈DVD的相關檔。

[b]種子資訊[/b]
所有種子都應該有描述性的標題，必要的介紹以及其他資訊。以下是一份簡明的規範，完整的、詳盡的規範請參閱“[url=forums.php?action=viewtopic&topicid=3438&page=0#56711]種子資訊填寫規範與指導[/url]”。
[*]標題
[*]電影：[i][中文名] 名稱 [年份] [剪輯版本] [發佈說明] 解析度 來源 [音訊/]視頻編碼-發佈組名稱[/i]
例：[i]蝙蝠俠:黑暗騎士 The Dark Knight 2008 PROPER 720p BluRay x264-SiNNERS[/i]
[*]電視劇：[i][中文名] 名稱 [年份] S**E** [發佈說明] 解析度 來源 [音訊/]視頻編碼-發佈組名稱[/i]
例：[i]越獄 Prison Break S04E01 PROPER 720p HDTV x264-CTU[/i]
[*]音軌：[i][中文藝術家名 - 中文專輯名] 藝術家名 - 專輯名 [年份] [版本] [發佈說明] 音訊編碼[-發佈組名稱][/i]
例：[i]恩雅 - 冬季降臨 Enya - And Winter Came 2008 FLAC[/i]
[*]遊戲：[i][中文名] 名稱 [年份] [版本] [發佈說明][-發佈組名稱][/i]
例：[i]紅色警戒3:起義時刻 Command And Conquer Red Alert 3 Uprising-RELOADED[/i]
[*]副標題
[*]不要包含廣告或求種/續種請求。
[*]外部資訊
[*]電影和電視劇必須包含外部資訊連結（如IMDb連接）位址（如果存在的話）。
[*]簡介
[*]NFO圖請寫入NFO檔，而不是粘貼到簡介裡。
[*]電影、電視劇、動漫：
[*]必須包含海報、橫幅或BD/HDDVD/DVD封面（如果存在的話）； 
[*]請盡可能包含畫面截圖或其縮略圖和連結；
[*]請盡可能包含檔的詳細情況，包括格式、時長、編碼、碼率、解析度、語言、字幕等；
[*]請盡可能包含演職員名單以及劇情概要。
[*]體育節目：
[*]請勿在文字介紹或截圖/檔案名/檔大小/時長中洩漏比賽結果。
[*]音樂：
[*]必須包含專輯封面和曲目列表（如果存在的話）；
[*]PC遊戲：
[*]必須包含海報或BD/HDDVD/DVD封面（如果存在的話）；
[*]請盡可能包含畫面截圖或其縮略圖和連結。
[*]雜項
[*]請正確選擇資源的類型和品質資訊。
[*]注意事項
[*]管理員會根據規範對種子資訊進行編輯。
[*]請勿改變或去除管理員對種子資訊作出的修改（但上傳者可以修正一些錯誤）。
[*]種子資訊不符合規範的種子可能會被刪除，視種子資訊的規範程度而定。
[*]如果資源的原始發佈資訊基本符合規範，請儘量使用原始發佈資訊。
',
    ),
    13 => 
    array (
        'id' => 18,
        'lang_id' => 28,
        'title' => '管理守則 - <font class=striking>請慎用你的權限！</font> ',
        'text' => '[*]最重要的一條：慎用你手中的權限！
[*]對于違規行為不要怕說“不”！
[*]不要公開和其他管理員沖突，一切通過私下溝通解決。
[*]不要太絕情，給違規者一個改過的機會。
[*]不要試圖“打預防針”，等到人們犯錯了再去糾正。
[*]嘗試去改正一個不適當的帖而不是簡單的關閉它。
[*]多嘗試移動帖子到適合的版面而不是簡單地鎖帖。
[*]當處理版聊帖的時候要寬容適度。
[*]鎖帖的時候請給予簡單的操作理由。
[*]在屏蔽某個用戶前請先站短通知他/她, 如果有所積極回應可以考慮再給2周觀察期。
[*]不要禁用一個注冊尚未滿4周的帳戶。
[*]永遠記得以理服人。
',
    ),
    14 => 
    array (
        'id' => 17,
        'lang_id' => 28,
        'title' => '趣味盒規則 - <font class=striking>在娛樂中賺分</font> ',
        'text' => '[*]任何用戶都可在趣味盒中投放笑話、趣圖、搞笑視頻、Flash等有趣的內容，除了色情、禁忌、政治敏感和當地法律禁止的內容。
[*]正常情況下，一條趣味內容在發布24小時后過期。然而，如果獲得的投票數超過20且其中“有趣”的比例低于25%，趣味內容將提前過期。
[*]新的趣味內容[b]只有[/b]在舊的內容過期后才能提交。
[*]若趣味內容被多數用戶投票認為有趣，其發布者將得到以下獎勵：
[*]票數超過25，其中認為“有趣”比例超過50%，發布者得到5個魔力值。
[*]票數超過50，其中認為“有趣”比例超過50%，發布者得到另外的5個魔力值。
[*]票數超過100，其中認為“有趣”比例超過50%，發布者得到另外的5個魔力值。
[*]票數超過200，其中認為“有趣”比例超過50%，發布者得到另外的5個魔力值。
[*]票數超過25，其中認為“有趣”比例超過75%，發布者得到10個魔力值。
[*]票數超過50，其中認為“有趣”比例超過75%，發布者得到另外的10個魔力值。
[*]票數超過100，其中認為“有趣”比例超過75%，發布者得到另外的10個魔力值。
[*]票數超過200，其中認為“有趣”比例超過75%，發布者得到另外的10個魔力值。',
    ),
    15 => 
    array (
        'id' => 21,
        'lang_id' => 6,
        'title' => 'General rules - <font class=striking>Breaking these rules can and will get you banned!</font>',
        'text' => '[*]Do not do things we forbid.
[*]Do not spam.
[*]Cherish your user account. Inactive accounts would be deleted based on the following rules:
1.[b]Veteran User[/b] or above would never be deleted.
2.[b]Elite User[/b] or above would never be deleted if packed (at [url=usercp.php?action=personal]User CP[/url]).
3.Packed accounts would be deleted if users have not logged in for more than 400 days in a row.
4.Unpacked accounts would be deleted if users have not logged in for more than 150 days in a row.
5.Accounts with both uploaded and downloaded amount being 0 would be deleted if users have not logged in for more than 100 days in a row.
[*]User found cheating would be deleted. Don\'t take chances!
[*]Possession of multiple [site] accounts will result in a ban!
[*]Do not upload our torrents to other trackers! (See the [url=faq.php#38]FAQ[/url] for details.)
[*]Disruptive behavior in the forums or on the server will result in a warning. You will only get [b]one[/b] warning! After that it\'s bye bye Kansas!',
    ),
    16 => 
    array (
        'id' => 15,
        'lang_id' => 28,
        'title' => '評論總則 - <font class=striking>永遠尊重上傳者！</font> ',
        'text' => '[*]無論如何，請尊重上傳者！
[*]所有論壇發帖的規則同樣適用于評論。
[*]如果你沒有下載的意向，請不要隨便發表否定性的評論。',
    ),
    17 => 
    array (
        'id' => 25,
        'lang_id' => 6,
        'title' => 'Commenting Guidelines - <font class=striking>Always respect uploaders no matter what!</font>',
        'text' => '[*]Always respect uploaders no matter what!
[*]All rules of forum posting apply to commenting, too.
[*]Do not post negative comments about torrents that you don\'t plan to download.',
    ),
    18 => 
    array (
        'id' => 27,
        'lang_id' => 6,
        'title' => 'Funbox Rules - <font class=striking>Get bonus with fun!</font>',
    'text' => '[*]Users can submit anything funny (e.g. stories, pictures, flash, video) except things that is pornographic, taboo, political sensitive or forbidden by local laws.
[*]Normally a newly-submitted funbox item would be outdated after 24 hours. However, if there are 20 or more votes on a funbox item, among which votes for \'funny\' is less than 25%, the funbox item would be outdated ahead of its due time.
[*]New funbox item can be submitted [b]only[/b] when the old one is outdated.
[*]User, whose funbox item is voted as [b]funny[/b], would be rewarded based on the following rules:
[*]More than 25 votes, among which votes for [i]funny[/i] exceed 50%. User gets 5 bonus.
[*]More than 50 votes, among which votes for [i]funny[/i] exceed 50%. User gets another 5 bonus.
[*]More than 100 votes, among which votes for [i]funny[/i] exceed 50%. User gets another 5 bonus.
[*]More than 200 votes, among which votes for [i]funny[/i] exceed 50%. User gets another 5 bonus.
[*]More than 25 votes, among which votes for [i]funny[/i] exceed 75%. User gets 10 bonus.
[*]More than 50 votes, among which votes for [i]funny[/i] exceed 75%. User gets another 10 bonus.
[*]More than 100 votes, among which votes for [i]funny[/i] exceed 75%. User gets another 10 bonus.
[*]More than 200 votes, among which votes for [i]funny[/i] exceed 75%. User gets another 10 bonus.',
    ),
    19 => 
    array (
        'id' => 22,
        'lang_id' => 6,
        'title' => 'Downloading rules - <font class=striking>By not following these rules you will lose download privileges!</font>',
        'text' => '[*]Low ratios may result in severe consequences, including banning accounts. See [url=faq.php#22]FAQ[/url].
[*]Rules for torrent promotion:
[*]Random promotion (torrents promoted randomly by system upon uploading):
[*]10% chance becoming [color=#7c7ff6][b]50% Leech[/b][/color],
[*]5% chance becoming [color=#f0cc00][b]Free Leech[/b][/color],
[*]5% chance becoming [color=#aaaaaa][b]2X up[/b][/color],
[*]3% chance becoming [color=#7ad6ea][b]50% Leech and 2X up[/b][/color],
[*]1% chance becoming [color=#99cc66][b]Free Leech and 2X up[/b][/color].
[*]Torrents larger than 20GB will automatically be [color=#f0cc00][b]Free Leech[/b][/color].
[*]Raw Blu-ray, HD DVD Discs will be [color=#f0cc00][b]Free Leech[/b][/color].
[*]First episode of every season of TV Series, etc. will be [color=#f0cc00][b]Free Leech[/b][/color].
[*]Highly popular torrents will be on promotion (decided by admins).
[*]Promotion timeout:
[*]Except [color=#aaaaaa][b]2X up[/b][/color], all the other types of promotion will be due after 7 days (counted from the time when the torrent is uploaded).
[*][color=#aaaaaa][b]2X up[/b][/color] will never become due.
[*]ALL the torrents will be [color=#aaaaaa][b]2X up[/b][/color] forever when they are on the site for over 1 month.
[*]On special occasions, we would set the whole site [color=#f0cc00][b]Free Leech[/b][/color]. Grab as much as you can. :mml:  :mml:  :mml:
[*]You may [b]only[/b] use allowed bittorrent clients at [site]. See [url=faq.php#29]FAQ[/url].',
    ),
    20 => 
    array (
        'id' => 24,
        'lang_id' => 6,
        'title' => 'General Forum Guidelines - <font class=stiking>Please follow these guidelines or else you might end up with a warning!</font>',
        'text' => '[*]No aggressive behavior or flaming in the forums.
[*]No trashing of any topics (i.e. SPAM). Do not submit meaningless topics or posts (e.g. smiley only) in any forum except Water Jar.
[*]Do not flood any forum in order to get bonus.
[*]No foul language on title or text.
[*]Do not discuss topics that are taboo, political sensitive or forbidden by local laws.
[*]No language of discrimination based on race, national or ethnic origin, color, religion, gender, age, sexual preference or mental or physical disability. Violating this rule would result in permanent ban.
[*]No bumping... (All bumped threads will be deleted.)
[*]No double posting. 
[*]Please ensure all questions are posted in the correct section!
[*]Topics without new reply in 365 days would be locked automatically by system.',
    ),
    21 => 
    array (
        'id' => 26,
        'lang_id' => 6,
        'title' => 'Avatar Guidelines - <font class=striking>Please try to follow these guidelines</font>',
        'text' => '[*]The allowed formats are .gif, .jpg and .png. 
[*]Be considerate. Resize your images to a width of 150 px and a size of no more than 150 KB. (Browsers will rescale them anyway: smaller images will be expanded and will not look good; larger images will just waste bandwidth and CPU cycles.)
[*]Do not use potentially offensive material involving porn, religious material, animal / human cruelty or ideologically charged images. Staff members have wide discretion on what is acceptable. If in doubt PM one. ',
    ),
    22 => 
    array (
        'id' => 23,
        'lang_id' => 6,
        'title' => 'Uploading rules - <font class=striking>Torrents violating these rules may be deleted without notice</font>',
        'text' => 'Please respect the rules, and if you have any questions about something unclear or not understandable, please [url=contactstaff.php]consult the staff[/url]. Staff reserves the rights to adjudicate.

[b]GENERAL[/b]
[*]You must have legal rights to the file you upload.
[*]Make sure your torrents are well-seeded. If you fail to seed for at least 24 hours or till someone else completes, or purposely keep a low uploading speed, you can be warned and your privilege to upload can be removed.
[*]You would get 2 times as much of uploading credit for torrents uploaded by yourself.
[*]If you have something interesting that somehow violates these rules, [url=contactstaff.php]ask the staff[/url] with a detailed description and we might make an exception.

[b]PRIVILEGE[/b]
[*]Everyone can upload.
[*]However, some must go through the [url=offers.php]Offer section[/url]. See [url=faq.php#22]FAQ[/url] for details.
[*]ONLY users in the class [color=#DC143C][b]Uploader[/b][/color] or above, or users specified by staff can freely upload games. Others should go through the [url=offers.php]Offer section[/url].

[b]ALLOWED CONTENTS[/b]
[*]High Definition (HD) videos, including
[*]complete HD media, e.g. Blu-ray disc, HD DVD disc, etc. or remux,
[*]captured HDTV streams,
[*]encodes from above listed sources in HD resolution (at least 720p),
[*]other HD videos such as HD DV.
[*]Standard Definition (SD) videos, only
[*]SD encodes from HD media (at least 480p),
[*]DVDR/DVDISO,
[*]DVDRip, CNDVDRip.
[*]Lossless audio tracks (and corresponding cue sheets), e.g. FLAC, Monkey\'s Audio, etc.
[*]5.1-channel (or higher) movie dubs and music tracks (DTS, DTS CD Image, etc.), and commentary tracks.
[*]PC games (must be original images).
[*]HD trailers released within 7 days.
[*]HD-related software and documents.

[b]NOT ALLOWED CONTENTS[/b]
[*]Contents less than 100 MB in total.
[*]Upscaled/partially upscaled in Standard Definition mastered/produced content.
[*]Videos in SD resolution but with low quality, including CAM, TC, TS, SCR, DVDSCR, R5, R5.Line, HalfCD, etc.
[*]RealVideo encoded videos (usually contained in RMVB or RM), flv files.
[*]Individual samples (to be included in the "Main torrent").
[*]Lossy audios that are not 5.1-channel (or higher), e.g. common lossy MP3\'s, lossy WMAs, etc.
[*]Multi-track audio files without proper cue sheets.
[*]Installation-free or highly compressed games, unofficial game images, third-party mods, collection of tiny games, individual game cracks or patches.
[*]RAR, etc. archived files.
[*]Dupe releases. (see beneath for dupe rules.)
[*]Taboo or sensitive contents (such as porn or politically sensitive topics).
[*]Damaged files, i.e. files that are erroneous upon reading or playback.
[*]Spam files, such as viruses, trojans, website links, advertisements, torrents in torrent, etc., or irrelevant files.

[b]DUPE RULES: QUALITY OVER QUANTITY[/b]
[*]Video releases are prioritized according to their source media, and mainly: Blu-ray/HD DVD > HDTV > DVD > TV. High prioritized versions will dupe other versions with low priorities of the same video.
[*]HD releases will dupe SD releases of the same video.
[*]For animes, HDTV versions are equal in priority to DVD versions. This is an exception.
[*]Encodes from the same type of media and in the same resolution 
[*]They are prioritized based on "[url=forums.php?action=viewtopic&forumid=6&topicid=1520]Scene & Internal, from Group to Quality-Degree. ONLY FOR HD-resources[/url]".
[*]Releases from preferred groups will dupe releases from groups with the same or lower priority.
[*]However, one DVD5 sized (i.e. approx. 4.38 GB) release from the best available source will always be allowed.
[*]Based on lossless screenshots comparison, releases with higher quality will dupe those with low quality.
[*]Blu-ray Disk/HD DVD Original Copy releases from another region containing different dubbing and/or subtitle aren\'t considered to be dupe.
[*]Only one copy of the same lossless audio contents will be preserved, and copies of other formats will be duped. FLAC (in separate tracks) is most preferred.
[*]For contents already on the site
[*]If new release doesn\'t contain the confirmed errors/glitches/problems of the old release or is based on a better source, then it\'s allowed to be uploaded and the old release is duped.
[*]If the old release is dead for 45 days or longer, or exists for 18 months or longer, then the new release is free from the dupe rules.
[*]After uploading the new release, old releases won\'t be removed until they\'re dead of inactivity.

[b]PACKING RULES (ON TRIAL)[/b]
ONLY the following contents are allowed to be packed in principle:
[*]HD movie collections sold as box set (e.g. [i]The Ultimate Matrix Collection Blu-ray Box[/i]).
[*]Complete season(s) of TV Series/TV shows/animes.
[*]Documentaries on the same specific subject matter.
[*]HD trailers released within 7 days.
[*]MVs of the same artist
[*]SD MVs are allowed to be packed according to DVD discs only, and no upload of individual songs is allowed.
[*]HD MVs in the same resolution.
[*]Music of the same artist
[*]Only 5 or more albums can be packed.
[*]Albums released within 2 years can be individually uploaded.
[*]Generally, contents that are already on the site should be removed from the pack upon uploading, otherwise include them all together in the pack.
[*]Animes, character songs, dramas, etc. that are released in separate volumes.
[*]Contents packed by formal groups.
Packed video contents must be from media of the same type (e.g. Blu-ray discs), in the same resolution standard (e.g. 720p), and encoded in the same video codec (e.g. x264). However, trailer are exceptions. Moreover, a movie collection should be released from the same group. Packed audio contents must be encoded in the same audio codec (e.g. all in FLAC). Corresponding individual torrents can be removed upon packing, depending on actual situation.
If you are not clear of anything about packing, please [url=contactstaff.php]consult the staff[/url]. Staff reserve all the rights to interpret and deal with packing-related issues.

[b]EXCEPTIONS[/b]
[*]ALLOWED: SD videos from TV/DSR in the category "Sports".
[*]ALLOWED: contents less than 100 MB but related to software and documents.
[*]ALLOWED: single albums that are less than 100 MB.
[*]ALLOWED: 2.0-channel (or higher) Mandarin/Cantonese dubs.
[*]ALLOWED: attached subtitles, game cracks and patches, fonts, scans (of packages, etc.). These files must be all either archived or unarchived.
[*]ALLOWED: when uploading CD releases, attaching contents from the DVD given with the CD.

[b]TORRENT INFORMATION[/b]
All torrents shall have descriptive titles, necessary descriptions and other information. Following is a brief regulation. Please refer to "[url=forums.php?action=viewtopic&topicid=3438&page=0#56711]Standard and Guidance of Torrent Information[/url]" (in Chinese) for complete and detailed instructions.
[*]Title
[*]Movies: [i]Name [Year] [Cut] [Release Info] Resolution Source [Audio/]Video Codec-Tag[/i]
e.g. [i]The Dark Knight 2008 PROPER 720p BluRay x264-SiNNERS[/i]
[*]TV Series/Mini-serie: [i]Name [Year] S**E** [Release Info] Resolution Source [Audio/]Video Codec-Tag[/i]
e.g. [i]Prison Break S04E01 PROPER 720p HDTV x264-CTU[/i]
[*]Musics: [i]Artist - Album [Year] [Version] [Release Info] Audio Codec[-Tag][/i]
e.g. [i]Enya - And Winter Came 2008 FLAC[/i]
[*]Games: [i]Name [Year] [Version] [Release Info][-Tag][/i]
e.g. [i]Command And Conquer Red Alert 3 Uprising-RELOADED[/i]
[*]Small description
[*]No advertisements or asking for a reseed/requests.
[*]External Info
[*]URL of external info for Movies and TV Series is required (if available).
[*]Description
[*]Do not use the description for your NFO-artwork! Limit those artistic expressions to the NFO only.
[*]For Movies, TV Series/Mini-series and animes:
[*]Poster, banner or BD/HDDVD/DVD cover is required (If available).
[*]Adding screenshots or thumbnails and links to the screenshots is encouraged.
[*]Adding detailed file information regarding format, runtime, codec, bitrate, resolution, language, subtitle, etc. is encouraged.
[*]Adding a list of staff and cast and plot outline is encouraged.
[*]For Sports:
[*]Don\'t spoil the results trough text/screenshots/filenames/obvious filesize/detailed runtime.
[*]For Music:
[*]The CD cover and the track list are required (if available).
[*]For PC Games:
[*]Poster, banner or BD/HDDVD/DVD cover is required (If available).
[*]Adding screenshots or thumbnails and links to the screenshots is encouraged.
[*]Misc
[*]Please correctly specify the category and quality info.
[*]NOTES
[*]Moderators will edit the torrent info according to the standard.
[*]Do NOT remove or alter changes done by the staff (but some mistakes can be fixed by the uploader).
[*]Torrents without required information can be deleted, depending on how they meet the standard.
[*]The original torrent information can be used if it basically meets the standard.
',
    ),
    23 => 
    array (
        'id' => 28,
        'lang_id' => 6,
        'title' => 'Moderating Rules - <font class=striking>Use your better judgement!</font>',
        'text' => '[*]The most important rule: Use your better judgment!
[*]Don\'t be afraid to say [b]NO[/b]!
[*]Don\'t defy another staff member in public, instead send a PM or through IM.
[*]Be tolerant! Give the user(s) a chance to reform.
[*]Don\'t act prematurely, let the users make their mistakes and THEN correct them.
[*]Try correcting any "off topics" rather then closing a thread.
[*]Move topics rather than locking them.
[*]Be tolerant when moderating the chat section (give them some slack).
[*]If you lock a topic, give a brief explanation as to why you\'re locking it.
[*]Before you disable a user account, send him/her a PM and if they reply, put them on a 2 week trial.
[*]Don\'t disable a user account until he or she has been a member for at least 4 weeks.
[*]Convince people by reasoning rather than authority.',
    ),
    24 => 
    array (
        'id' => 54,
        'lang_id' => 25,
        'title' => '管理组成员退休待遇',
        'text' => '满足以下条件可获得的退休待遇: 

[code]
[b]对于 [color=#DC143C]上传员 (Uploaders)[/color]: [/b]

成为 [color=#1cc6d5][b]养老族 (Retiree) [/b]: [/color]
升职一年以上; 上传过200个以上的种子资源 (特殊情况如原碟发布, 0day更新等可以由管理组投票表决; 须被认定为作出过重大及持久的贡献).

成为 [color=#009F00][b]VIP[/b]: [/color]
升职6个月以上; 上传过100个以上的种子资源 (特殊情况如原碟发布, 0day更新等可以由管理组投票表决).

其他:
成为 [color=#F88C00][b]Extreme User[/b][/color] (如果你的条件满足 [color=#F88C00][b]Extreme User[/b][/color] 及以上, 则成为 [color=#38ACEC][b]Nexus Master[/b][/color]) .
[/code]

[code]
[b]对于 [color=#6495ED]管理员 (Moderators)[/color]: [/b]

成为 [color=#1cc6d5][b]养老族 (Retiree)[/b]: [/color]
升职一年以上; 参加过至少2次站务组正式会议; 参与过 规则/答疑 的修订工作.

成为 [color=#009F00][b]VIP[/b]: [/color]
若不满足成为 [color=#1cc6d5][b]养老族 (Retiree)[/b][/color] 的条件, 你可以[b]无条件[/b]成为 [color=#009F00][b]VIP[/b][/color] .
[/code]

[code]
[b]对于 [color=#4b0082]总管理员 (Administrators)[/color] 及 以上等级: [/b]

可以[b]直接[/b]成为 [color=#1cc6d5][b]养老族 (Retiree)[/b][/color] .
[/code]',
    ),
    25 => 
    array (
        'id' => 55,
        'lang_id' => 28,
        'title' => '管理組成員退休待遇',
        'text' => '滿足以下條件可獲得的退休待遇: 
[code]
[b]對於 [color=#DC143C]上傳員 (Uploaders)[/color]: [/b]
成為 [color=#1cc6d5][b]養老族 (Retiree) [/b]: [/color]
升職一年以上; 上傳過200個以上的種子資源 (特殊情況如原碟發佈, 0day更新等可以由管理組投票表決; 須被認定為作出過重大及持久的貢獻).
成為 [color=#009F00][b]VIP[/b]: [/color]
升職6個月以上; 上傳過100個以上的種子資源 (特殊情況如原碟發佈, 0day更新等可以由管理組投票表決).
其他:
成為 [color=#F88C00][b]Extreme User[/b][/color] (如果你的條件滿足 [color=#F88C00][b]Extreme User[/b][/color] 及以上, 則成為 [color=#38ACEC][b]Nexus Master[/b][/color]) .
[/code]
[code]
[b]對於 [color=#6495ED]管理員 (Moderators)[/color]: [/b]
成為 [color=#1cc6d5][b]養老族 (Retiree)[/b]: [/color]
升職一年以上; 參加過至少2次站務組正式會議; 參與過 規則/答疑 的修訂工作.
成為 [color=#009F00][b]VIP[/b]: [/color]
若不滿足成為 [color=#1cc6d5][b]養老族 (Retiree)[/b][/color] 的條件, 你可以[b]無條件[/b]成為 [color=#009F00][b]VIP[/b][/color] .
[/code]
[code]
[b]對於 [color=#4b0082]總管理員 (Administrators)[/color] 及 以上等級: [/b]
可以[b]直接[/b]成為 [color=#1cc6d5][b]養老族 (Retiree)[/b][/color] .
[/code]',
    ),
    26 => 
    array (
        'id' => 50,
        'lang_id' => 6,
        'title' => 'Rules for Subtitles - <font class=striking>Subtitles violating these rules will be deleted</font>',
    'text' => '(This text is translated from the Chinese version. In case of discrepancy, the original version in Chinese shall prevail.)

[b]GENERAL PRINCIPLE:[/b]
[*]All subtitles uploaded must conform to the rules (i.e. proper or qualified). Unqualified subtitles will be deleted.
[*]Allowed file formats are srt/ssa/ass/cue/zip/rar.
[*]If you\'re uploading Vobsub (idx+sub) subtitles or subtitles of other types, or a collection (e.g. subtitles for a season pack of some TV series), please zip/rar them before uploading.
[*]Cue sheet of audio tracks is allowed as well. If there are several cue sheets, please pack them all.
[*]Uploading lrc lyrics or other non-subtitle/non-cue files is not permitted. Irrelevant files if uploaded will be directly deleted.

[b]QUALIFYING SUBTITLE/CUE FILES: improper subtitle/cue files will be directly deleted.[/b]
In any of the following cases, a subtitle/cue file will be judged as improper:
[*]Fail to match the corresponding torrent.
[*]Fail to be in sync with the corresponding video/audio file.
[*]Packed Improperly.
[*]Contain irrelevant or spam stuff.
[*]Encoded incorrectly.
[*]Wrong cue file.
[*]Wrong language mark.
[*]The title is indefinite or contains redundant info/characters.
[*]Duplicate.
[*]Reported by several users and confirmed with other problems.
[b]The staff group reserves rights to judge and deal with improper subtitles.[/b]
Please refer to [url=http://www.nexushd.org/forums.php?action=viewtopic&forumid=13&topicid=2848][i]this thread[/i][/url] in the forum for detailed regulations on qualifying subtitle/cue files, other notes and suggestions on uploading subtitles, and subtitle naming and entitling guidance.

[b]IMPLEMENTING REGULATIONS OF REWARDS AND PENALTIES [/b]
[*]Reporting against improper subtitles and the uploaders who purposely upload improper subtitles is always welcomed. To report an improper subtitle, please click on the [i]REPORT[/i] button of the corresponding subtitle in the subtitle section. To report a user, please click on the [i]REPORT[/i] button at the bottom of the user details page.
[*]The reporter will be rewarded 50 karma points (delivered in three days) for each case after confirmation.
[*]Improper subtitles will be deleted and the corresponding uploader will be fined 100 karma points in each case.
[*]Users who recklessly uploading improper subtitles for karma points or other purposes, or users who maliciously report, will be fined karma points or warned depending on the seriousness of the case.
',
    ),
    27 => 
    array (
        'id' => 49,
        'lang_id' => 25,
        'title' => '字幕区规则 - <font class=striking>违规字幕将被删除</font>',
        'text' => '[b]总则：[/b]
[*]所有上传的字幕必须符合规则（即合格的）。不合格的字幕将被删除。
[*]允许上传的文件格式为srt/ssa/ass/cue/zip/rar。
[*]如果你打算上传的字幕是Vobsub格式（idx+sub）或其它格式，或者是合集（如电视剧整季的字幕），请将它们打包为zip/rar后再上传。
[*]字幕区开放音轨对应cue表单文件的上传。如有多个cue，请将它们打包起来。
[*]不允许lrc歌词或其它非字幕/cue文件的上传。上传的无关文件将被直接删除。

[b]不合格字幕/cue文件判定：被判定为不合格的字幕/cue文件将被直接删除。[/b]
出现以下情况之一的字幕/cue文件将被判定为不合格：
[*]与相应种子不匹配。
[*]与相应的视频/音频文件不同步。
[*]打包错误。
[*]包含无关文件或垃圾信息。
[*]编码错误。
[*]cue文件错误。
[*]语种标识错误。
[*]标题命名不明确或包含冗余信息或字符。
[*]被判定为重复。
[*]接到多个用户举报并被证实有其它问题的。
[b]管理组保留裁定和处理不合格字幕的权力。[/b]
不合格字幕/cue文件判定细则、字幕上传的其它注意事项以及命名指引请参阅论坛的[url=http://www.nexushd.org/forums.php?action=viewtopic&forumid=13&topicid=2848]这个帖子[/url]。

[b]字幕奖惩：[/b]
[*]欢迎举报不合格的字幕和恶意发布不合格字幕的用户。举报不合格字幕请在字幕区点击相应字幕的“举报”按钮。举报用户请点击相应用户详细信息页面底部的“举报”按钮。
[*]对每一例不合格字幕的举报，确认后将奖励举报者50点魔力值（三天内发放）。
[*]被确定为不合格的字幕将被删除，而在每一例中，相应的字幕上传者将被扣除100点魔力值。
[*]对为赚取积分等目的恶意上传不合格字幕的用户，或是恶意举报的用户，将视情节轻重扣除额外的魔力值甚至给予警告。
',
    ),
    28 => 
    array (
        'id' => 53,
        'lang_id' => 6,
        'title' => 'Staff\'s retirement benefits',
    'text' => 'You can get retirement benefits when meeting these condition(s) below:

[code]
[b]for [color=#DC143C]Uploaders[/color]: [/b]

To join [color=#1cc6d5][b]Retiree[/b]: [/color]
Been promoted for more than 1 year; have posted 200 or more torrents (special cases can be decided via vote among staffs, like Source-Disc posters, scene-uploaders; should be considered as having made rare and enduring contribution).

To join [color=#009F00][b]VIP[/b]: [/color]
Been promoted for more than 6 months; have posted 100 or more torrents (special cases can be decided via vote among staffs, like Source-Disc posters, scene-uploaders).

Others:
Demoted to [color=#F88C00][b]Extreme User[/b][/color] (if your profile meets the corresponding condition of classes [color=#F88C00][b]Extreme User[/b][/color] and above, then promoted to [color=#38ACEC][b]Nexus Master[/b][/color]).
[/code]

[code]
[b]for [color=#6495ED]Moderators[/color]: [/b]

To join [color=#1cc6d5][b]Retiree[/b]: [/color]
Been promoted for more than 1 year; Have participated at least 2 Staff [b]Official[/b] Meetings; Have participated in Rules/FAQ modifying.

To join [color=#009F00][b]VIP[/b]: [/color]
If you don\'t meet the condition of joining [color=#1cc6d5][b]Retiree[/b][/color], you can join [color=#009F00][b]VIP[/b][/color] [b]unconditionally[/b].
[/code]

[code]
[b]for [color=#4b0082]Administrators[/color] and above: [/b]

You can join [color=#1cc6d5][b]Retiree[/b][/color] [b]unconditionally[/b].
[/code]',
    ),
    29 => 
    array (
        'id' => 51,
        'lang_id' => 28,
        'title' => '字幕區規則 - <font class=striking>違規字幕將被刪除</font>',
        'text' => '[b]總則：[/b]
[*]所有上傳的字幕必須符合規則（即合格的）。不合格的字幕將被刪除。
[*]允許上傳的檔案格式為srt/ssa/ass/cue/zip/rar。
[*]如果你打算上傳的字幕是Vobsub格式（idx+sub）或其它格式，或者是合集（如電視劇整季的字幕），請將它們打包為zip/rar後再上傳。
[*]字幕區開放音軌對應cue表單文件的上傳。如有多個cue，請將它們打包起來。
[*]不允許lrc歌詞或其它非字幕/cue文件的上傳。上傳的無關檔將被直接刪除。

[b]不合格字幕/cue文件判定：被判定為不合格的字幕/cue檔將被直接刪除。[/b]
出現以下情況之一的字幕/cue檔將被判定為不合格：
[*]與相應種子不匹配。
[*]與相應的視頻/音訊檔不同步。
[*]打包錯誤。
[*]包含無關檔或垃圾資訊。
[*]編碼錯誤。
[*]cue檔錯誤。
[*]語種標識錯誤。
[*]標題命名不明確或包含冗餘資訊或字元。
[*]被判定為重複。
[*]接到多個用戶舉報並被證實有其它問題的。
[b]管理組保留裁定和處理不合格字幕的權力。[/b]
不合格字幕/cue檔判定細則、字幕上傳的其它注意事項以及命名指引請參閱論壇的[url=http://www.nexushd.org/forums.php?action=viewtopic&forumid=13&topicid=2848]這個帖子[/url]。

[b]字幕獎懲：[/b]
[*]歡迎舉報不合格的字幕和惡意發佈不合格字幕的用戶。舉報不合格字幕請在字幕區點擊相應字幕的“舉報”按鈕。舉報使用者請點擊相應使用者詳細資訊頁面底部的“舉報”按鈕。
[*]對每一例不合格字幕的舉報，確認後將獎勵舉報者50點魔力值（三天內發放）。
[*]被確定為不合格的字幕將被刪除，而在每一例中，相應的字幕上傳者將被扣除100點魔力值。
[*]對為賺取積分等目的惡意上傳不合格字幕的用戶，或是惡意舉報的用戶，將視情節輕重扣除額外的魔力值甚至給予警告。
',
    ),
));
        
        
    }
}