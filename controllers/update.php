<?php
/**
 * @brief 升级更新控制器
 */
class Update extends IController
{
	/**
	 * @brief appoil15011000 版本升级更新
	 */
	public function appoil15011000()
	{
		$sql = array(
			"delete from `{pre}freight_company`;",

			"INSERT INTO `{pre}freight_company` VALUES (NULL,'EMS','邮政特快专递','http://www.ems.com.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'STO','申通快递','http://www.sto.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'HHTT','天天快递','http://www.ttkd.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'YTO','圆通速递','http://www.yto.net.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'SF','顺丰速运','http://www.sf-express.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'YD','韵达快递','http://www.yundaex.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'ZTO','中通速递','http://www.zto.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'LB','龙邦物流','http://www.lbex.com.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'ZJS','宅急送','http://www.zjs.com.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'UAPEX','全一快递','http://www.apex100.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'HTKY','汇通速递','http://www.htky365.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'MHKD','民航快递','http://www.cae.com.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'ZTKY','中铁快运','http://www.cre.cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'FEDEX','FedEx联邦快递','http://www.fedex.com/cn',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'DHL','DHL','http://www.cn.dhl.com',0,0);",
			"INSERT INTO `{pre}freight_company` VALUES (NULL,'DBL','德邦物流','http://www.deppon.com',0,0);",

			"ALTER TABLE `{pre}payment` ADD `client_type` tinyint(1) NOT NULL default '1' COMMENT '1:PC端 2:移动端 3:通用';",
			"UPDATE `{pre}payment` SET `client_type` = 3 WHERE `class_name` = 'balance';",
			"UPDATE `{pre}payment` SET `client_type` = 3 WHERE `class_name` = 'offline';",
			"INSERT INTO `{pre}payment` VALUES (NULL, '支付宝移动支付', 1, 'wap_alipay', '手机支付方便用户手机快速下单。 <a href=\"http://www.alipay.com/\" target=\"_blank\">立即申请</a>', '/payments/logos/pay_wap_alipay.png', 0, 99, NULL, 0.00, 1, NULL,2);",


			"ALTER TABLE `{pre}refundment_doc` ADD  `goods_id` int(11) unsigned NOT NULL COMMENT '要退款的商品';",
			"ALTER TABLE `{pre}refundment_doc` ADD  `product_id` int(11) unsigned NOT NULL default '0' COMMENT '要退款的货品';",
			"ALTER TABLE `{pre}refundment_doc` ADD  `seller_id` int(11) unsigned NOT NULL default '0' COMMENT '商户ID';",

			"ALTER TABLE `{pre}comment` ADD `recontents` text COMMENT '回复评论内容';",
			"ALTER TABLE `{pre}comment` ADD `recomment_time` date NOT NULL COMMENT '回复评论时间';",
		);

		foreach($sql as $key => $val)
		{
			$val = str_replace('{pre}', Web::$app->config['DB']['tablePre'], $val);
			IDBFactory::getDB()->query($val);
		}

		die('升级成功！');
	}
}