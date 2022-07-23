English | [中文](/)

Complete PT website building solution. Based on NexusPHP + Laravel + Filament.

## Functional Features

- Upload torrent
- Special section  
- Request torrent
- Subtitle
- Exam
- H&R
- Claim  
- Approval  
- Attendance
- Retroactive attendance card  
- Medal
- Custom tags 
- SeedBox rule  
- Forum
- Complain  
- Multi-language
- Automatic backup
- Plugin support  
- Backend management system
- Json API
- ....

## System Requirements
- PHP: 8.0, must have extensions: bcmath, ctype, curl, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml, mysqli, gd, redis, pcntl, sockets, posix, gmp
- Mysql: 5.7 latest version or above
- Redis：2.0.0 or above

## Quick Start
Install docker.  
Where DOMAIN is the domain name you want to use, first do a good resolution. If you don't have a domain name, you can use IP.   
If the local port 80 is already used, please change it and make sure the port is open to the public.  
Step 2 Create .env Select the correct time zone TIMEZONE, other defaults are fine.  
```
docker pull xiaomlove/nexusphp:latest
docker run --name my-nexusphp -e DOMAIN=xxx.com -p 80:80 xiaomlove/nexusphp:latest
```
**Production environments are recommended to refer to the documentation for live installation.**

## More information
Blog：[https://nexusphp.org](https://nexusphp.org/)  
Documentation：[https://doc.nexusphp.org](https://doc.nexusphp.org/en/)  
Telegram: [https://t.me/nexusphp](https://t.me/nexusphp)  

## Project supported by JetBrains
Many thanks to Jetbrains for kindly providing a license for me to work on this and other open-source projects.  
[![](https://resources.jetbrains.com/storage/products/company/brand/logos/jb_beam.svg)](https://www.jetbrains.com/?from=https://github.com/xiaomlove/nexusphp)
