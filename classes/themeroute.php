<?php
/**
 * @copyright (c) 2014 appoil
 * @file themeroute.php
 * @brief 主题皮肤选择路由类
 * @author Jason
 * @date 2014/7/15 18:50:48
 * @version 2.6
 *
 * config.php 中的theme和skin多种写法，只用theme举例说明
 * 1, 'theme' => 'default' #所有客户端平台都用default主题
 * 2, 'theme' => array('pc' => 'default','mobile' => 'mobile') #pc端用default主题；mobile端用mobile主题
 */
class themeroute extends IInterceptorBase
{
	const SCENE_SYSDEFAULT = 'sysdefault';
	const SCENE_SYSSELLER  = 'sysseller';
	const SCENE_SITE       = 'site';

	//后台管理
	private static $sysTheme       = 'sysdefault';
	private static $sysSkin        = 'default';

	//卖家管理
	private static $sysSellerTheme = 'sysseller';
	private static $sysSellerSkin  = 'default';

	//后台管理的控制器
	private static $syscontroller = array(
		'crowd','pic','block','brand','comment','goods','market','member','message','order','system','systemadmin','tools'
	);

	//卖家管理的控制器
	private static $sellercontroller = array(
		'seller','systemseller'
	);

	/**
	 * @brief theme和skin进行选择
	 */
	public static function onCreateController()
	{
		$controller = func_num_args() > 0 ? func_get_arg(0) : Web::$app->controller;

		//判断是否为后台管理控制器
		if(in_array($controller->getId(),self::$syscontroller))
		{
			defined("WEB_SCENE") ? "" : define("WEB_SCENE", self::SCENE_SYSDEFAULT);
			$controller->theme = self::$sysTheme;
			$controller->skin  = self::$sysSkin;
		}
		//判断是否为卖家管理控制器
		elseif(in_array($controller->getId(),self::$sellercontroller))
		{
			defined("WEB_SCENE") ? "" : define("WEB_SCENE", self::SCENE_SYSSELLER);
			$controller->theme = self::$sysSellerTheme;
			$controller->skin  = self::$sysSellerSkin;
		}
		else
		{
			defined("WEB_SCENE") ? "" : define("WEB_SCENE", self::SCENE_SITE);
			/**
			 * 对于theme和skin的判断流程
			 * 1,直接从URL中获取是否已经设定了方案__theme,__skin
			 * 2,获取cookie中的方案名称
			 * 3,读取config配置中的默认方案
			 */
			$urlTheme = IReq::get('__theme');
			$urlSkin  = IReq::get('__skin');

			if($urlTheme && $urlSkin && preg_match('|^\w+$|',$urlTheme) && preg_match('|^\w+$|',$urlSkin))
			{
				ISafe::set('__theme',$controller->theme = $urlTheme);
				ISafe::set('__skin',$controller->skin  = $urlSkin);
			}
			elseif(ISafe::get('__theme') && ISafe::get('__skin'))
			{
				$controller->theme = ISafe::get('__theme');
				$controller->skin  = ISafe::get('__skin');
			}
			else
			{
				if (isset(Web::$app->config['theme']))
				{
					//根据不同的客户端进行智能选择
					if (is_array(Web::$app->config['theme']))
					{
						$client = IClient::getDevice();
						$controller->theme = isset(Web::$app->config['theme'][$client]) ? Web::$app->config['theme'][$client] : current(Web::$app->config['theme']);
					}
					else
					{
						$controller->theme = Web::$app->config['theme'];
					}
				}

				if (isset(Web::$app->config['skin']))
				{
					//根据不同的客户端进行智能选择
					if (is_array(Web::$app->config['skin']))
					{
						$client = IClient::getDevice();
						$controller->skin = isset(Web::$app->config['skin'][$client]) ? Web::$app->config['skin'][$client] : current(Web::$app->config['skin']);
					}
					else
					{
						$controller->skin = Web::$app->config['skin'];
					}
				}
			}
		}

		//修正runtime配置
		Web::$app->runtimePath = Web::$app->getRuntimePath() . $controller->theme . '/';
		Web::$app->webRunPath = Web::$app->getWebRunPath() . $controller->theme . '/';
	}
}