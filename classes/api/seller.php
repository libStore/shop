<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file seller.php
 * @brief 商户API
 * @author Jason
 * @date 2014/10/12 13:59:44
 * @version 2.7
 */
class APISeller
{
	//商户信息
	public function getSellerInfo($id)
	{
		$query = new IModel('seller');
		$info  = $query->getObj("id=".$id);
		return $info;
	}
}