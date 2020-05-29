### 利用 Docker-Compose 搭建 LNMP 开发环境  

#### 软件清单
- PHP7.2
- Nginx
- MySQL5.6
- Redis

#### 目录结构

```
Docker-LNMP
|----docker                             Docker目录
|--------config                         配置文件目录
|------------proxy                      nginx配置文件目录
|--------files                          DockerFile文件目录
|------------cgi                        php-fpm DockerFile文件目录
|----------------Dockerfile             php-fpm DockerFile文件
|----------------docker-entrypoint.sh   php-fpm 启动脚本
|------------proxy                      nginx DockerFile文件目录
|----------------Dockerfile             nginx DockerFile文件
|----------------docker-entrypoint.sh   nginx 启动脚本
|--------log                            日志文件目录
|------------cgi                        php-fpm日志文件目录
|------------proxy                      nginx日志文件目录
|----www                                应用根目录
|--------index.php                      PHP例程
|----README.md                          说明文件
|----docker-compose.yml        docker compose 配置文件
```

#### 安装docker和docker-compose

```shell
# 安装docker和docker-compose
yum -y install epel-release 
yum -y install docker docker-compose

# 启动docker服务
service docker start

# 配置阿里云docker镜像加速器(非阿里云可忽略)
mkdir -p /etc/docker
vim /etc/docker/daemon.json
# 新增下面内容
{
    "registry-mirrors": ["https://8auvmfwy.mirror.aliyuncs.com"]
}

# 重新加载配置、重启docker
systemctl daemon-reload 
systemctl restart docker 
```

#### 创建systemd模板 `/etc/systemd/system/docker-compose@.service`

```textmate
[Unit]
Description=%i service with docker compose
Requires=docker.service
After=docker.service

[Service]
Restart=always

WorkingDirectory=/etc/docker/compose/%i

# Remove old containers, images and volumes
ExecStartPre=/usr/local/bin/docker-compose down -v
ExecStartPre=/usr/local/bin/docker-compose rm -fv
ExecStartPre=-/bin/bash -c 'docker volume ls -qf "name=%i_" | xargs docker volume rm'
ExecStartPre=-/bin/bash -c 'docker network ls -qf "name=%i_" | xargs docker network rm'
ExecStartPre=-/bin/bash -c 'docker ps -aqf "name=%i_*" | xargs docker rm'

# Compose up
ExecStart=/usr/bin/docker-compose up

# Compose down, remove containers and volumes
ExecStop=/usr/bin/docker-compose down -v

[Install]
WantedBy=multi-user.target
```

#### 启动lnmp的systemd
```shell
[root@localhost]# mkdir /etc/docker/compose/lnmp
[root@localhost]# cp docker-compose.yml /etc/docker/compose/lnmp/
[root@localhost]# systemctl start docker-compose\@lnmp.service
```

#### 创建docker cleanup

创建 timer `vi /etc/systemd/system/docker-cleanup.timer`, 内容如下:

```textmate
[Unit]
Description=Docker cleanup timer

[Timer]
OnUnitInactiveSec=12h

[Install]
WantedBy=timers.target
```

创建 cleanup systemd ` vi /etc/systemd/system/docker-cleanup.service`, 内容如下:

```textmate
[Unit]
Description=Docker cleanup
Requires=docker.service
After=docker.service

[Service]
Type=oneshot
WorkingDirectory=/tmp
User=root
Group=root
ExecStart=/usr/bin/docker system prune -f

[Install]
WantedBy=multi-user.target
```

启动 cleanup systemd `systemctl enable docker-cleanup.timer` 

### 添加网站

```shell
#/docker/config/proxy/conf.d 目录下新建一个配置文件 acfunc-docker.conf
[root@localhost]# vi docker/config/proxy/conf.d/acfunc-docker.conf
``` 

acfunc-docker.conf 内容如下:

```
server {

    listen 80;

    server_name example.acfunc.com;
    root /data/www/acfunc;
    index index.php index.html index.htm;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ ^/assets/.*\.php$ {
        deny all;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass cgi:9000;
        try_files $uri =404;
    }

    location ~* /\. {
        deny all;
    }

}
```

```shell
# 回到宿主服务器并重启nginx
[root@localhost]# docker restart proxy
```

### 安装php swoole扩展

```shell
# 进入docker容器内
[root@localhost]# docker exec -it cgi bash

[root@container]# yum -y install centos-release-scl
[root@container]# yum -y install devtoolset-7
[root@container]# scl enable devtoolset-7 bash

# 下载swoole4.5.1并安装
[root@container]# wget https://github.com/swoole/swoole-src/archive/v4.5.1.tar.gz &&\
	tar -zxvf v4.5.1.tar.gz &&\
	cd swoole-src-4.5.1 &&\
	phpize &&\
	./configure &&\
	make && make install &&\
	sed -i '$a \\n[swoole]\nextension=swoole.so' /etc/php.ini &&\
[root@container]# cd ../ && rm -rf v4.5.1.tar.gz swoole-src-4.2.1

# 退出PHP容器
[root@container]# exit

# 回到宿主服务器，重启php
[root@localhost# docker restart cgi
```