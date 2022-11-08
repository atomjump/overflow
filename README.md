<img src="https://atomjump.com/images/logo80.png">

# overflow

Acts like an 'overflow' on a sink: when there are too many messages in an AtomJump Messaging forum, the old messages are trimmed.

# Requirements

AtomJump Messaging Server >= 0.8.0


# Installation


```
sudo php install.php
```

Include "overflow" in your config.json plugins array.

Add an hourly (or some other timeframe) CRON entry for trim-messages.php e.g.

```
	0 * * * *       /usr/bin/php /your_server_path/api/plugins/overflow/trim-messages.php
```

Your AtomJump Messaging server's main .htaccess file:

After 
```
RewriteRule image-exists - [L,PT]
```
this should be added to your AtomJump Messaging server's main .htaccess file:
```
#Get out of here early - we know we don't need further processing
RewriteRule remove-image - [L,PT]
```


# Future development

* Warning about message limit being hit
* Have facility for sysadmin or general users to change the message limit.
