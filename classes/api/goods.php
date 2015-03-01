<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file goods.php
 * @author Jason
 * @date 2014-9-30 13:49:22
 * @version 0.6
 */
class APIGoods
{
	//商品列表
	public function getGoodsList($gid)
	{
		$fields    = ' id , name , goods_no , sell_price , market_price, cost_price , store_nums , img , weight ';
		$dbObj     = IDBFactory::getDB();
		$tableName = Web::$app->config['DB']['tablePre'] . 'goods';
		return $dbObj->doSql('SELECT '.$fields.' FROM '.$tableName.' WHERE id in ('.$gid.') AND is_del = 0 ');
	}
	//商品详情
	public function getGoodsInfo($id){
		$query = new IModel("goods");
		$info  = $query->getObj("id=".$id);
		return $info;
	}

}