<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file other.php
 * @brief 其他api方法
 * @author Jason
 * @date 2014/11/4 7:33:34
 * @version 2.8
 */
class APIOther
{
	//获取促销规则
	public function getProrule()
	{
		$proRuleObj = new ProRule(999999999);
		$proRuleObj->isGiftOnce = false;
		$proRuleObj->isCashOnce = false;
		return $proRuleObj->getInfo();
	}

	//获取支付方式
	public function getPaymentList()
	{
		$user_id = ISafe::get('user_id');
		$where = 'status = 0';

		if(!$user_id)
		{
			$where .= " and class_name != 'balance'";
		}

		switch(IClient::getDevice())
		{
			//移动支付
			case IClient::MOBILE:
			{
				$where .= ' and client_type in(2,3) ';
			}
			break;

			//pc支付
			case IClient::PC:
			{
				$where .= ' and client_type in(1,3) ';
			}
			break;
		}
		$paymentDB = new IModel('payment');
		return $paymentDB->query($where);
	}

	//线上充值的支付方式
	public function getPaymentListByOnline()
	{
		$where = " type = 1 and status = 0 and class_name not in ('balance','offline') ";
		switch(IClient::getDevice())
		{
			//移动支付
			case IClient::MOBILE:
			{
				$where .= ' and client_type in(2,3) ';
			}
			break;

			//pc支付
			case IClient::PC:
			{
				$where .= ' and client_type in(1,3) ';
			}
			break;
		}

		$paymentDB = new IModel('payment');
		return $paymentDB->query($where);
	}
}