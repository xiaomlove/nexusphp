English | [中文](/)

Complete PT website building solution. Based on NexusPHP + Laravel + FilamentPHP.

[![legacy](https://img.shields.io/endpoint?url=https://raw.githubusercontent.com/maximuml/nexus-test1/legacy-loc-data/badge.json)](https://github.com/maximuml/nexus-test1/blob/legacy-loc-data/history.csv)

Welcome to participate in internationalization work, click [here](https://github.com/xiaomlove/nexusphp/discussions/193) for more information

> **Contributors: read [`CONTRIBUTING.md`](CONTRIBUTING.md) first.** This
> repo is in Phase 0 — legacy freeze: no new files may be added to
> `public/*.php`, `include/**/*.php`, or `classes/**/*.php`. All new
> code goes into the Laravel layer. The full migration plan lives in
> [`docs/legacy-strategy.md`](docs/legacy-strategy.md). The badge above
> shows the current legacy LOC count — click it to view the full CSV
> history on the `legacy-loc-data` branch.

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
- Props  
- Custom tags 
- Third-party full-text search
- SeedBox rule  
- Forum
- Complain  
- Multi-language
- Automatic backup
- Plugin support  
- Backend management system
- Json API
- ....
 
#### The following functions are provided by the plugin
- Post like
- Post reward
- Sticky promotion
- Custom menu
- Lucky draw
- Custom role permission
- Section H&R
- TGBot
## System Requirements
- PHP: 8.2|8.3|8.4|8.5, must have extensions: bcmath, ctype, curl, fileinfo, json, mbstring, openssl, pdo_mysql, tokenizer, xml, mysqli, gd, redis, pcntl, sockets, posix, gmp, zend opcache, zip, intl, pdo_sqlite, sqlite3
- Mysql: 5.7 latest version or above
- Redis：4.0.0 or above
- Others: supervisor, rsync

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
Telegram: [https://t.me/nexusphp_dev](https://t.me/nexusphp_dev)    
