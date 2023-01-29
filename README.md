<img src="https://atomjump.com/images/logo80.png">

__WARNING: this project has now moved to https://src.atomjump.com/atomjump/overflow.git__

# overflow

Acts like an 'overflow' on a sink: when there are too many messages in an AtomJump Messaging forum, the old messages are trimmed.

# Requirements

AtomJump Messaging Server >= 1.0.0


# Installation


Unzip or git clone into the folder: your-messaging-server/plugins/overflow

```
 cd your-messaging-server/plugins/
 git clone https://src.atomjump.com/atomjump/overflow.git
 cd overflow/config
 cp configORIGINAL.json config.json
 nano config.json
```

Set the relevant variables, and save.

To create the database tables:
```
sudo php install.php
```

Add the string "overflow" into your-server-path/config/config.json plugins array to enable the plugin. e.g. 
```
     "plugins": [
         "overflow"
      ]
```

Add an hourly (or some other timeframe) CRON entry for trim-messages.php e.g.

```
0 * * * *   /usr/bin/php /your_server_path/api/plugins/overflow/trim-messages.php
```

Note: if this is a heavily trafficked server you may be best to use a 'nice' command on this CRON, so that it runs as a low-priority e.g.

```
0 * * * *	/usr/bin/nice -n 10 /usr/bin/php -q /your_server_path/api/plugins/overflow/trim-messages.php
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
To set the overflow message limits of 'n' for that forum. 'n' must be larger than the previous amount and less than the maximum a user is allowed to set (configurable in the config/config.json file)

But if you are a system user, you have no constraints, and can also enter:
```
overflow unlimited
```

Or, to switch the blurring of older images on or off for that forum
```
blur [on|off]
```


# Config Options Guide

* urlPathToDeleteScript

The URL should relative to the root of your website (not a local folder), e.g. if you website was https://mydomain.com/messaging-server/plugins/overflow/remove-image.php, you would enter a value of "/messaging-server/plugins/overflow/remove-image.php" here.


* fullyDelete

When set to 'false', the older messages will only be deactivated, and not deleted on the database, and the images will remain. This will not save disk space, but is useful if you wish to keep a full record and still include e.g. blurring.

* blurImages

This option will blur older images, by deleting the hi-res version of the image, but keeping the low-res version of the image. Storage for the low-res version of an image is around 1/6th of the hi-res version.

* layerTitleDbOverride

This option is useful when you have a "scaleUp" option in your messaging server, pointing at different databases.  Entering a name here will use the database entry that is entered in the "scaleUp" "labelRegExp" field. E.g. "api2" would work in 'layerTitleDbOverride' if the 'labelRegExp' for that database was "^api2".

* triggerOverLimit

If a clipping of old messages was triggered on every message, there would be too many database requests. This waits until e.g. 10 messages have been entered over the current forum limit before a clipping is triggered.

* publicForumLimit / privateForumLimit

In this context 'public' forums have no user-entered password to gain entry, vs 'private' forums, which have a password to gain entry. This is the default limit of messages for each different type of forum.  The number can be changed on a per-forum basis by entering command messages.

* publicMaxUserSetLimit / privateMaxUserSetLimit

In this context 'public' forums have no user-entered password to gain entry, vs 'private' forums, which have a password to gain entry. This is the maximum number of messages that a general user can themselves increase the limit to (note: they cannot decrease the limit, because that could result in other people's messages disappearing without their agreement). A system admin user can increase past this limit.


# Future development

* An 'archive' table option

* A message for users highlighting the clipped messages (and potentially how many)

* Multiple languages
