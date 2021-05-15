<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\User;
use App\Repositories\ExamRepository;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Just for test';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $rep = new ExamRepository();
//        $r = $rep->assignToUser(1, 1);
//        $r = $rep->addProgress(1, 1, [
//            1 => 25*1024*1024*1024,
//            2 => 55*3600,
//            3 => 10*1024*1024*1024,
//            4 => 1252
//        ]);
//        dd($r);
//        $rep->assignCronjob();
//        $r = $rep->cronjobCheckout();
//        $disk = Storage::disk('google_dirve');
//        $r = $disk->put('/', base_path('composer.json'));
//        $r = DB::table('users')->where('id', 1)->update(['modcomment' => DB::raw("concat_ws(',', 'ddddd', modcomment)")]);

        $text = '[quote]转自PTer，感谢原制作者发布。[/quote]
[img]https://img9.doubanio.com/view/photo/l_ratio_poster/public/p2515853287.jpg[/img]
[img]https://pterclub.com/pic/PTerWEB.png[/img]
◎译　　名　To us, From us / 再见18班
◎片　　名　再见十八班
◎年　　代　2018
◎产　　地　中国大陆
◎类　　别　剧情
◎语　　言　汉语普通话
◎上映日期　2018-03-07(中国大陆)
◎IMDb评分  6.9/10 from 30 users
◎IMDb链接  https://www.imdb.com/title/tt7861446
◎豆瓣评分　6.7/10 from 7214 users
◎豆瓣链接　https://movie.douban.com/subject/30159456/
◎片　　长　102分钟
◎主　　演　柯焱曦 Yanxi Ke
　　　　　　熊婧文 Jingwen Xiong
　　　　　　秦海 Hai Qin


◎标　　签　青春 | 校园 | 感动 | 成长 | 高中 | 回忆 | 老师 | 教师

◎简　　介

　　梧桐中学高二十八班是远近闻名的问题班级，后进生、艺术生、体育生、不良少年聚集在此，以宋宸（柯焱曦 饰）为首的男生帮派和以秦淼淼（熊婧文 饰）为首的女生帮派互相不服，班级混乱无序。一天，高二十八班收到了一封信，这封信自称是由一年后的他们集体寄来。信上说，即将调来的班主任谭 睿明（秦海 饰）会改变十八班，成为对他们最重要的人。然而在一年后的未来，谭老师却永远地离开了他们。为了实现一段没有遗憾的青春，扭转历史，少年们决定按照信上所说，挑战一个个不可能实现的任务...';

        $r = format_description($text);
        dd($r);
    }

}
