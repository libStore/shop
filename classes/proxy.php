<?php
/**
 * @copyright (c) 2014 appoil
 * @file Proxy.php
 * @brief 代理处理
 * @author Jason
 * @date 2014/8/3 16:46:20
 * @version 1.0.0
 */
class Proxy
{
	//升级URL
	const UPDATE_URL = 'http://product.appoil.com/index.php?';

	/**
	 * 与远程服务器发送数据
	 * @param string $query 查询字符串
	 * @return array
	 */
	private static function send($query = '')
	{
		$url = self::UPDATE_URL . $query;
		if(($return = file_get_contents($url)) && ($return = JSON::decode($return)))
		{
			return $return;
		}
	}

	/**
	 * 获取本地版本信息
	 * @return String
	 */
	public static function getLocalVersion()
	{
		return include(Web::$app->getBasePath() . 'api/version.php');
	}

	/**
	 * 获取远程版本信息
	 * @return String
	 */
	public static function getRemoteVersion()
	{
		$return = self::send('_c=system&_a=version');
		return isset($return['version']) ? $return['version'] : null;
	}

	/**
	 * 获取版权信息,存储到缓存中进行比对
	 * @return boolean
	 */
	public static function getAuthorize()
	{
		$appoilAuthorize = ISafe::get('appoilAuthorize');
		if($appoilAuthorize === null)
		{
			$return = self::send('_c=system&_a=authorize&host='.IUrl::getHost());
			$appoilAuthorize = isset($return['success']) && $return['success'] == 1 ? true : false;
			ISafe::set('appoilAuthorize',$appoilAuthorize);
		}
		return $appoilAuthorize;
	}
}