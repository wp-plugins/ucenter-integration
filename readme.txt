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

�˲�������wordpress��ucenter֧�ֵ�ƽ̨һ������ʹ��������������ɵİ�wordpress��ucenter���ɡ�
�ڰ�װ֮��wordpress��������Ϊ��
>1. ���û���½wordpress��
 1. ����û���ucenter�����ڣ� ���ܶ�ע����û���
 2. ����û����ڣ�������ѡ��򿪵�ʱ���û����½�ɹ�����ucenter�е�����Ḳ��wordpress�е����롣�����½ʧ�ܡ�
2. ��ɾ��wordpress�û�ʱ��ucenter�е���Ӧ�û�Ҳ�ᱻɾ����
3. ���û���½������Ӧ��ʱ��wordpress���Զ���½��
4. ���û��ǳ�wordpressʱ������Ӧ��Ҳ�ᱻ�Զ��ǳ���
5. ���û��ǳ�����Ӧ��ʱ���û�Ҳ���Զ��ǳ�wordpress��

== Installation ==  

1. Upload ucenter-integration to the `/wp-content/plugins/` directory.  
2. Activate the plugin through the 'Plugins' menu in WordPress.  
3. Login dashboard and make suitable setting.  
  
  
1. �ϴ�ucenter-integration�����`/wp-content/plugins/`Ŀ¼��  
2. ͨ������˵���������  
3. ��½������岢�������ʺϵĲ�����   

== Frequently Asked Questions ==  

= What should I do when enconter Access denied?  

enter ucenter integration plugin dir, remove config.php and login dashboard to reset setting of ucenter integration.  

= ��������Access denied��ʱ��Ӧ����ʲô��  

����ucenter���ɲ��Ŀ¼��ɾ��config.php, ��½�����������ò����  

= If problem still exists, what should I do?  

enter plugin dir, remove ucenter-integration and reinstanll this plugin.  

= ���������Ȼ���ڣ���Ӧ����ʲô��  

������Ŀ¼��ɾ��ucenter-integrationĿ¼�������°�װ�ò����  

== Screenshots ==  

1. Integrate wordpress with ucenter, which will make wordpress work with ucenter supported platform.  

1. ��wordpress���ɵ�ucenter�У���ʹ��wordpress���Ժ�����ucenter֧�ֵ�ƽ̨һ������  
  
== Changelog ==  

= 0.1 =   
* First version  
> User can synlogin all apps that has been integrated into ucenter.  
> When user login other apps that has been integrated into ucenter, user will auto login wordpress.  
> When user does not exists in ucenter, user will be auto registered in ucenter when user login wordpress.  
  
  
* ��һ��  
> �û�����ͬ����½������ucenter���ɵ�Ӧ���С�  
> ���û���½������ucenter���ɵ�Ӧ�õ�ʱ����Զ���½��wordpress��  
> ���û���������ucenter��ʱ�� �û����ڵ�½ʱ��ucenter���Զ�ע�ᡣ  

== Upgrade Notice ==  

= 0.1 =  
First release. No need to upgrade.  
  
��һ�档��������  

