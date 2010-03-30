=== Plugin Name ===  
Contributors: ychen  
Donate link: http://chenyundong.com/  
Tags: plugin, ucenter, integration  
Requires at least: 2.9  
Tested up to: 2.9  
Stable tag: 0.1  

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

此插件会帮助wordpress和ucenter支持的平台一起工作。使用它，你可以轻松的把wordpress和ucenter集成。
在安装之后，wordpress会这样行为：
>1. 当用户登陆wordpress：
 1. 如果用户在ucenter不存在， 会总动注册该用户。
 2. 如果用户存在，当覆盖选项打开的时候，用户会登陆成功并且ucenter中的密码会覆盖wordpress中的密码。否则登陆失败。
2. 当删除wordpress用户时，ucenter中的相应用户也会被删除。
3. 当用户登陆其他的应用时，wordpress会自动登陆。
4. 当用户登出wordpress时，其他应用也会被自动登出。
5. 当用户登出其他应用时，用户也会自动登出wordpress。

== Installation ==  

1. Upload ucenter-integration to the `/wp-content/plugins/` directory.  
2. Activate the plugin through the 'Plugins' menu in WordPress.  
3. Login dashboard and make suitable setting.  
  
  
1. 上传ucenter-integration插件到`/wp-content/plugins/`目录。  
2. 通过插件菜单激活插件。  
3. 登陆控制面板并且设置适合的参数。   

== Frequently Asked Questions ==  

= What should I do when enconter Access denied?  

enter ucenter integration plugin dir, remove config.php and login dashboard to reset setting of ucenter integration.  

= 当我遇到Access denied的时候应该做什么？  

进入ucenter集成插件目录，删除config.php, 登陆管理重新配置插件。  

= If problem still exists, what should I do?  

enter plugin dir, remove ucenter-integration and reinstanll this plugin.  

= 如果问题依然存在，我应该做什么？  

进入插件目录，删除ucenter-integration目录并且重新安装该插件。  

== Screenshots ==  

1. Integrate wordpress with ucenter, which will make wordpress work with ucenter supported platform.  

1. 把wordpress集成到ucenter中，这使得wordpress可以和所有ucenter支持的平台一起工作。  
  
== Changelog ==  

= 0.1 =   
* First version  
> User can synlogin all apps that has been integrated into ucenter.  
> When user login other apps that has been integrated into ucenter, user will auto login wordpress.  
> When user does not exists in ucenter, user will be auto registered in ucenter when user login wordpress.  
  
  
* 第一版  
> 用户可以同步登陆到所有ucenter集成的应用中。  
> 当用户登陆到其他ucenter集成的应用的时候会自动登陆到wordpress。  
> 当用户不存在于ucenter的时候， 用户会在登陆时在ucenter中自动注册。  

== Upgrade Notice ==  

= 0.1 =  
First release. No need to upgrade.  
  
第一版。不需升级  

