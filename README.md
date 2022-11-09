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

# Usage

Enter a message
```
overflow n
```
To set the overflow message limits of 'n'. 'n' must be larger than the previous amount and less than the maximum a user is allowed to set (configurable in the config/config.json file)

But if you are a system user, you have no constraints, and can also enter:
```
overflow unlimited
```


# Future development

* Blur older photos in the oldest 60% or so of messages to save storage space
* An 'archive' table option
