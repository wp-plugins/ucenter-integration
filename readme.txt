=== Plugin Name ===
Contributors: ychen
Donate link: http://chenyundong.com/
Tags: plugin, ucenter, integration
Requires at least: 2.9
Tested up to: 3.2.1
Stable tag: 0.3.5

Integrate wordpress with ucenter, which will make wordpress work with ucenter supported platform.

== Description ==

This plugin help wordpress work with ucenter supported platform. Using it, you can easily integrate wordpress with ucenter.
After installation, wordpress will act like this:
>1. When user login wordpress:
 1. If user does not exist in ucenter, auto register user in ucenter.
 2. If user exist in ucenter, if override option is enabled, user will login successfully and password in wordpress will be overrided by that in ucenter. otherwise, login failed.
2. When delete user in wordpress, user in ucenter will be deleted.
3. When user login other apps, user will also auto login wordpress.
4. When user logout wordpress, user will also auto logout other apss.
5. When user logout other apps, user will also auto logout wordpress.

BTW, After enabling msg, avatar, credit, friends component, you can send msg, change avatar, exchange credits and add friends in wordpress.
That will be very cool for wordpress and discuz fans.

Plugin has been tested in 3.2.1 without multi site.  
Upgrade UC client to 1.6.0  
Donate: http://chenyundong.com/?p=368

== Installation ==

1. Upload ucenter-integration to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Login dashboard and make suitable setting.

== Frequently Asked Questions ==

= What should I do when enconter Access denied?

enter ucenter integration plugin dir, remove config.php and login dashboard to reset setting of ucenter integration.

= If problem still exists, what should I do?

enter plugin dir, remove ucenter-integration and reinstanll this plugin.

== Screenshots ==

1. setting
2. message component
3. friend component
4. credit component
5. avatar component

== Changelog ==

= 0.3.5 =
> upgrade uc client to 1.6.0  
> fix MailBox no table bug

= 0.3.4 =
> fix error discard when update user  
> fix some typos  
> remove core hack part  
> support chinese login name  

= 0.3.3 =
> fix sending chars before sending header bug  
> fix activated_plugin hook

= 0.3.2 =
> fix sending chars before sending header bug  
> fix permission deny bug

= 0.3.1 =
> fix ucenter user can not login wordpress bug

= 0.3 =
> fix bugs

= 0.2 =
> Fix some bugs  
> add mail box  
> add customize icon  
> add friend  
> add credit

= 0.1 =
> First version  
> User can synlogin all apps that has been integrated into ucenter  
> When user login other apps that has been integrated into ucenter, user will auto login wordpress  
> When user does not exists in ucenter, user will be auto registered in ucenter when user login wordpress

== Upgrade Notice ==

= 0.1 =
> First release. No need to upgrade
