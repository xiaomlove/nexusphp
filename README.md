中文 | [English](/README-EN.md)

完整的 PT 建站解决方案。基于 NexusPHP + Laravel + Filament。

欢迎参与国际化工作，点击 [这里](https://github.com/xiaomlove/nexusphp/discussions/193) 了解详情

## 功能特性
- 发种
- 特别区  
- 求种
- 字幕
- 考核
- H&R
- 认领
- 审核  
- 签到
- 补签卡  
- 勋章
- 道具 
- 自定义标签
- 第三方全文搜索
- 盒子规则  
- 论坛 
- 申诉  
- 多语言
- 自动备份
- 插件支持  
- 管理后台  
- Json API
- ....

#### 以下功能由插件提供
- 帖子点赞
- 帖子奖励
- 置顶促销
- 自定义菜单
- 幸运大转盘
- 自定义角色权限
- 分区 H&R
- TGBot

## 系统要求
- PHP: 8.0|8.1|8.2，必须扩展：bcmath, ctype, curl, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml, mysqli, gd, redis, pcntl, sockets, posix, gmp, zend opcache
- Mysql: 5.7 最新版或以上版本
- Redis：2.6.12 或以上版本
- 其他：supervisor, rsync

## 快速开始
安装 docker。  
其中 DOMAIN 是你要使用的域名，先做好解析。 没有域名使用 IP 亦可。   
端口按需要指定，如果本地 80 端口已经使用，请更换，保证端口对外开放。  
第 2 步创建 .env 选择正确的时区 TIMEZONE，其他默认即可。
```
docker pull xiaomlove/nexusphp:latest
docker run --name my-nexusphp -e DOMAIN=xxx.com -p 80:80 xiaomlove/nexusphp:latest
```
**生产环境建议参考文档实机安装。**

## AD-服务器推荐
|服务商| 推广地址 |优惠码|
|---|---|---|
|[七七云](https://www.vps77.com/aff.php?aff=167&gid=1)   |https://www.vps77.com/aff.php?aff=167&gid=1|xiaomlove|

## 更多信息
博客：[https://nexusphp.org](http://nexusphp.org/)  
文档：[https://doc.nexusphp.org](http://doc.nexusphp.org/)  
Telegram: [https://t.me/nexusphp](https://t.me/nexusphp)  

## Project supported by JetBrains
Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.  
[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/xiaomlove/nexusphp)
