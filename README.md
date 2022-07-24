中文 | [English](/README-EN.md)

完整的 PT 建站解决方案。基于 NexusPHP + Laravel + Filament。

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
- 自定义标签
- 盒子规则  
- 论坛 
- 申诉  
- 多语言
- 自动备份
- 插件支持  
- 管理后台  
- Json API
- ....

## 系统要求
- PHP: 8.0，必须扩展：bcmath, ctype, curl, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml, mysqli, gd, redis, pcntl, sockets, posix, gmp
- Mysql: 5.7 最新版或以上版本
- Redis：2.0.0 或以上版本

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
## 更多信息
博客：[https://nexusphp.org](http://nexusphp.org/)  
文档：[https://doc.nexusphp.org](http://doc.nexusphp.org/)  
QQ群: [764452568](https://jq.qq.com/?_wv=1027&k=IbltZcIx)  
Telegram: [https://t.me/nexusphp](https://t.me/nexusphp)  
B站: [xiaomlove](https://space.bilibili.com/1319303059)  

## Project supported by JetBrains
Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.  
[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/xiaomlove/nexusphp)
