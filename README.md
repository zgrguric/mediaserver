# MediaServer

Slim Framework Media Server for images and video.  
This image and video server does not have external provider dependencies and can be run in fully closed ecosystem. If you value your privacy this might be ideal solution for you. 

# Server requirements

* PHP >= 7.1.3
* Fileinfo Extension
* Imagick PHP extension (>=6.5.7) or GD Library (>=2.0)
* ext-mongodb https://www.php.net/manual/en/mongodb.installation.pecl.php
* FreeType Font support
* REDIS Extension
* CUrl

Apache:
* mod_alias
* mod_rewrite

Upload max size (50MB) - php.ini
* post_max_size = 50M
* upload_max_filesize = 50M
* memory_limit = 512M

# File permissions

`chown -R root:daemon ../`

`find storage/ -type d -exec chmod 770 {} \;`

`find storage/ -type f -exec chmod 760 {} \;`

# Queue and Supervisor

Location of supervisor config file is /etc/supervisord.conf, at bottom of file it Include .ini, change it to .conf.

`/etc/supervisord.d/mediaserver-worker.conf`

```
[program:mediaserver-worker]
process_name=%(program_name)s_%(process_num)02d
command=/opt/php/bin/php /var/www/htdocs/mediaserver/artisan queue:run --queue=default
autostart=true
autorestart=true
user=daemon
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/supervisord.log
```

To enable worker run:

`supervisorctl reread`

`supervisorctl update`

`supervisorctl start mediaserver-worker:*`

# Unit Testing

Windows:  
`.\vendor\bin\phpunit tests --testdox`

Linux:  
`sudo -u daemon ./vendor/bin/phpunit tests --testdox`

