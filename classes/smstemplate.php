<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file smstemplate.php
 * @brief 短信发送模板
 * @author Jason
 * @date 2014/8/11 9:51:51
 * @version 2.9
 */

 /**
 * @class smsTemplate
 * @brief 短信发送模板
 */
class smsTemplate
{
	/**
	 * @brief 订单发货的信息模板
	 * @param array $data 替换的数据
	 */
	public static function sendGoods($data = null)
	{
		$templateString = "您好{user_name}，订单号：{order_no}，已由{sendor}发货，物流公司：{delivery_company}，物流单号：{delivery_no}";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 手机找回密码模板
	 * @param array $data 替换的数据
	 */
	public static function findPassword($data = null)
	{
		$templateString = "您的密码重置功能的手机校验码：{mobile_code}，请勿向陌生人透露";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 手机短信校验码
	 * @param array $data 替换的数据
	 */
	public static function checkCode($data = null)
	{
		$templateString = "您的手机校验码：{mobile_code}，请勿向陌生人透露";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 自提点确认短信
	 * @param array $data 替换的数据
	 */
	public static function takeself($data = null)
	{
		$templateString = "您的订单号：{orderNo}，地址：{address}，请尽快领取";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 商户注册提示管理员
	 * @param array $data 替换的数据
	 */
	public static function sellerReg($data = null)
	{
		$templateString = "{true_name}，申请加盟到平台，请尽快登录后台进行处理";
		return strtr($templateString,$data);
	}

	/**
	 * @brief 商户处理结果
	 * @param array $data 替换的数据
	 */
	public static function sellerCheck($data = null)
	{
		$templateString = "您的加盟商状态已经被修改为：{result}状态，请登录您的商户后台查看具体的详情";
		return strtr($templateString,$data);
	}
}