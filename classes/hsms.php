<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file hsms.php
 * @brief 短信发送接口
 * @author Jason
 * @date 2014/8/11 9:51:51
 * @version 2.6
 */

 /**
 * @class Hsms
 * @brief 短信发送接口
 */
class Hsms
{
	/**
	 * @brief 获取config用户配置
	 * @return array
	 */
	private static function getConfig()
	{
		$siteConfigObj = new Config("site_config");

		return array(
			'userid'   => $siteConfigObj->sms_userid,
			'username' => $siteConfigObj->sms_username,
			'userpwd'  => $siteConfigObj->sms_pwd,
		);
	}

	/**
	 * @brief 发送短信
	 * @param string $mobile
	 * @param string $content
	 * @return
	 */
	public static function send($mobile,$content)
	{
		$config = self::getConfig();

		$post_data = array(
			'userid'   => $config['userid'],
			'account'  => $config['username'],
			'password' => $config['userpwd'],
			'content'  => $content,
			'mobile'   => $mobile,
			'sendtime' => '',
		);

		$url    = 'http://www.duanxin10086.com/sms.aspx?action=send';
		$string = '';
		foreach ($post_data as $k => $v)
		{
		   $string .="$k=".urlencode($v).'&';
		}

		$post_string = substr($string,0,-1);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //如果需要将结果直接返回到变量里，那加上这句。
		$result = curl_exec($ch);
		return self::response($result);
	}

	/**
	 * @brief 解析结果
	 * @param $result 发送结果
	 * @return string success or fail
	 */
	private static function response($result)
	{
		if(strpos($result,'<returnstatus>Success</returnstatus>'))
		{
			return 'success';
		}
		else
		{
			return 'fail';
		}
	}
}