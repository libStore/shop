<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file Order_Class.php
 * @brief 订单中相关的
 * @author Jason
 * @date 2014-02-24
 * @version 0.6
 */
class Order_Class
{
	/**
	 * @brief 产生订单ID
	 * @return string 订单ID
	 */
	public static function createOrderNum()
	{
		return date('YmdHis').rand(100000,999999);
	}

	/**
	 * 添加评论商品的机会
	 * @param $order_id 订单ID
	 */
	public static function addGoodsCommentChange($order_id)
	{
		//获取订单对象
		$orderDB  = new IModel('order');
		$orderRow = $orderDB->getObj('id = '.$order_id);

		//获取此订单中的商品种类
		$orderGoodsDB        = new IQuery('order_goods');
		$orderGoodsDB->where = 'order_id = '.$order_id;
		$orderGoodsDB->group = 'goods_id';
		$orderList           = $orderGoodsDB->find();

		//可以允许进行商品评论
		$commentDB = new IModel('comment');

		//对每类商品进行评论开启
		foreach($orderList as $val)
		{
			$attr = array(
				'goods_id' => $val['goods_id'],
				'order_no' => $orderRow['order_no'],
				'user_id'  => $orderRow['user_id'],
				'time'     => date('Y-m-d H:i:s')
			);
			$commentDB->setData($attr);
			$commentDB->add();
		}
	}

	/**
	 * 支付成功后修改订单状态
	 * @param $orderNo  string 订单编号
	 * @param $admin_id int    管理员ID
	 * @param $note     string 收款的备注
	 * @return false or int order_id
	 */
	public static function updateOrderStatus($orderNo,$admin_id = '',$note = '')
	{
		//获取订单信息
		$orderObj  = new IModel('order');
		$orderRow  = $orderObj->getObj('order_no = "'.$orderNo.'"');

		if(empty($orderRow))
		{
			return false;
		}

		if($orderRow['pay_status'] == 1)
		{
			return $orderRow['id'];
		}
		else if($orderRow['pay_status'] == 0)
		{
			$dataArray = array(
				'status'     => ($orderRow['status'] == 5) ? 5 : 2,
				'pay_time'   => ITime::getDateTime(),
				'pay_status' => 1,
			);

			$orderObj->setData($dataArray);
			$is_success = $orderObj->update('order_no = "'.$orderNo.'"');
			if($is_success == '')
			{
				return false;
			}

			//删除订单中使用的道具
			$ticket_id = trim($orderRow['prop']);
			if($ticket_id != '')
			{
				$propObj  = new IModel('prop');
				$propData = array('is_userd' => 1);
				$propObj->setData($propData);
				$propObj->update('id = '.$ticket_id);
			}

			if(intval($orderRow['user_id']) != 0)
			{
				$user_id = $orderRow['user_id'];

				//获取用户信息
				$memberObj  = new IModel('member');
				$memberRow  = $memberObj->getObj('user_id = '.$user_id,'prop,group_id');

				//(1)删除订单中使用的道具
				if($ticket_id != '')
				{
					$finnalTicket = str_replace(','.$ticket_id.',',',',','.trim($memberRow['prop'],',').',');
					$memberData   = array('prop' => $finnalTicket);
					$memberObj->setData($memberData);
					$memberObj->update('user_id = '.$user_id);
				}

				if($memberRow)
				{
					//(2)进行促销活动奖励
			    	$proObj = new ProRule($orderRow['real_amount']);
			    	$proObj->setUserGroup($memberRow['group_id']);
			    	$proObj->setAward($user_id);

			    	//(3)增加经验值
			    	$memberData = array(
			    		'exp'   => 'exp + '.$orderRow['exp'],
			    	);
					$memberObj->setData($memberData);
					$memberObj->update('user_id = '.$user_id,'exp');

					//(4)增加积分
					$pointConfig = array(
						'user_id' => $user_id,
						'point'   => $orderRow['point'],
						'log'     => '成功购买了订单号：'.$orderRow['order_no'].'中的商品,奖励积分'.$orderRow['point'],
					);
					$pointObj = new Point();
					$pointObj->update($pointConfig);
				}
			}

			//插入收款单
			$collectionDocObj = new IModel('collection_doc');
			$collectionData   = array(
				'order_id'   => $orderRow['id'],
				'user_id'    => $orderRow['user_id'],
				'amount'     => $orderRow['order_amount'],
				'time'       => ITime::getDateTime(),
				'payment_id' => $orderRow['pay_type'],
				'pay_status' => 1,
				'if_del'     => 0,
				'note'       => $note,
				'admin_id'   => $admin_id ? $admin_id : 0
			);

			$collectionDocObj->setData($collectionData);
			$collectionDocObj->add();

			/*同步数据*/
			//同步团购的数据
			if($orderRow['type'] == 1)
			{
				$regimentUserObj = new IModel('regiment_user_relation');
				$regimentUserObj->setData(array('is_over' => 1));
				$regimentUserObj->update("order_no = '".$orderRow['order_no']."'");
			}

			//更改购买商品的库存数量
			self::updateStore($orderRow['id'],'reduce');

			return $orderRow['id'];
		}
		else
		{
			return false;
		}
	}

	/**
	 * 订单商品数量更新操作[公共]
	 * @param $order_id 订单ID
	 * @param $type 增加或者减少 add 或者 reduce
	 */
	public static function updateStore($order_id,$type = 'add')
	{
		$newStoreNums  = 0;
		$updateGoodsId = array();
		$orderGoodsObj = new IModel('order_goods');
		$goodsObj      = new IModel('goods');
		$productObj    = new IModel('products');
		$goodsList     = $orderGoodsObj->query('order_id = '.$order_id,'goods_id,product_id,goods_nums');

		foreach($goodsList as $key => $val)
		{
			//货品库存更新
			if($val['product_id'] != 0)
			{
				$productsRow = $productObj->getObj('id = '.$val['product_id'],'store_nums');
				$localStoreNums = $productsRow['store_nums'];

				//同步更新所属商品的库存量
				if(in_array($val['goods_id'],$updateGoodsId) == false)
				{
					$updateGoodsId[] = $val['goods_id'];
				}

				$newStoreNums = ($type == 'add') ? $localStoreNums + $val['goods_nums'] : $localStoreNums - $val['goods_nums'];
				$newStoreNums = $newStoreNums > 0 ? $newStoreNums : 0;

				$productObj->setData(array('store_nums' => $newStoreNums));
				$productObj->update('id = '.$val['product_id'],'store_nums');
			}
			//商品库存更新
			else
			{
				$goodsRow = $goodsObj->getObj('id = '.$val['goods_id'],'store_nums');
				$localStoreNums = $goodsRow['store_nums'];

				$newStoreNums = ($type == 'add') ? $localStoreNums + $val['goods_nums'] : $localStoreNums - $val['goods_nums'];
				$newStoreNums = $newStoreNums > 0 ? $newStoreNums : 0;

				$goodsObj->setData(array('store_nums' => $newStoreNums));
				$goodsObj->update('id = '.$val['goods_id'],'store_nums');
			}

			//更新销售量sale字段，库存减少销售量增加，两者成反比
			$saleData = ($type == 'add') ? -$val['goods_nums'] : $val['goods_nums'];
			$goodsObj->setData(array('sale' => 'sale + '.$saleData));
			$goodsObj->update('id = '.$val['goods_id'],'sale');
		}

		//更新统计goods的库存
		if($updateGoodsId)
		{
			foreach($updateGoodsId as $val)
			{
				$totalRow = $productObj->getObj('goods_id = '.$val,'SUM(store_nums) as store');
				$goodsObj->setData(array('store_nums' => $totalRow['store']));
				$goodsObj->update('id = '.$val);
			}
		}
	}

	/**
	 * @brief 获取订单扩展数据资料
	 * @param $order_id int 订单的id
	 * @param $user_id int 用户id
	 * @return array()
	 */
	public function getOrderShow($order_id,$user_id = 0)
	{
		$where = 'id = '.$order_id;
		if($user_id !== 0)
		{
			$where .= ' and user_id = '.$user_id;
		}

		$data = array();

		//获得对象
		$tb_order = new IModel('order');
 		$data = $tb_order->getObj($where);
 		if($data)
 		{
	 		$data['order_id'] = $order_id;

	 		//获取配送方式
	 		$tb_delivery = new IModel('delivery');
	 		$delivery_info = $tb_delivery->getObj('id='.$data['distribution']);
	 		if($delivery_info)
	 		{
	 			$data['delivery'] = $delivery_info['name'];

	 			//自提点读取
	 			if($data['takeself'])
	 			{
	 				$data['takeself'] = self::getTakeselfInfo($data['takeself']);
	 			}
	 		}

	        //物流单号
	    	$tb_delivery_doc = new IQuery('delivery_doc as dd');
	    	$tb_delivery_doc->join   = 'left join freight_company as fc on dd.freight_id = fc.id';
	    	$tb_delivery_doc->fields = 'dd.delivery_code,fc.freight_name';
	    	$tb_delivery_doc->where  = 'order_id = '.$order_id;
	    	$delivery_info = $tb_delivery_doc->find();
	    	if($delivery_info)
	    	{
	    		$temp = array('freight_name' => array(),'delivery_code' => array());
	    		foreach($delivery_info as $key => $val)
	    		{
	    			$temp['freight_name'][]  = $val['freight_name'];
	    			$temp['delivery_code'][] = $val['delivery_code'];
	    		}
    			$data['freight']['freight_name']  = join(",",$temp['freight_name']);
    			$data['freight']['delivery_code'] = join(",",$temp['delivery_code']);
	    	}

	 		//获取支付方式
	 		$tb_payment = new IModel('payment');
	 		$payment_info = $tb_payment->getObj('id='.$data['pay_type']);
	 		if($payment_info)
	 		{
	 			$data['payment'] = $payment_info['name'];
	 			$data['paynote'] = $payment_info['note'];
	 		}

	 		//获取商品总重量和总金额
	 		$tb_order_goods = new IModel('order_goods');
	 		$order_goods_info = $tb_order_goods->query('order_id='.$order_id);
	 		$data['goods_amount'] = 0;
	 		$data['goods_weight'] = 0;

	 		if($order_goods_info)
	 		{
	 			foreach ($order_goods_info as $value)
	 			{
	 				$data['goods_amount'] += $value['real_price']   * $value['goods_nums'];
	 				$data['goods_weight'] += $value['goods_weight'] * $value['goods_nums'];
	 			}
	 		}

	 		//获取用户信息
	 		$query = new IQuery('user as u');
	 		$query->join = ' left join member as m on u.id=m.user_id ';
	 		$query->fields = 'u.username,u.email,m.mobile,m.contact_addr,m.true_name';
	 		$query->where = 'u.id='.$data['user_id'];
	 		$user_info = $query->find();
	 		if($user_info)
	 		{
	 			$user_info = current($user_info);
	 			$data['username']     = $user_info['username'];
	 			$data['email']        = $user_info['email'];
	 			$data['u_mobile']     = $user_info['mobile'];
	 			$data['contact_addr'] = $user_info['contact_addr'];
	 			$data['true_name']    = $user_info['true_name'];
	 		}
 		}
 		return $data;
	}

	/**
	 * 获取自提点基本信息
	 * @param $id int 自提点id
	 */
	public static function getTakeselfInfo($id)
	{
		$takeselfObj = new IModel('takeself');
		$takeselfRow = $takeselfObj->getObj('id = '.$id);

		$temp = area::name($takeselfRow['province'],$takeselfRow['city'],$takeselfRow['area']);
		$takeselfRow['province_str'] = $temp[$takeselfRow['province']];
		$takeselfRow['city_str']     = $temp[$takeselfRow['city']];
		$takeselfRow['area_str']     = $temp[$takeselfRow['area']];
		return $takeselfRow;
	}

	/**
	 * 获取订单基本信息
	 * @param $orderIdString string 订单ID序列
	 */
	public function getOrderInfo($orderIdString)
	{
		$orderObj    = new IModel('order');
		$areaIdArray = array();
		$orderList   = $orderObj->query('id in ('.$orderIdString.')');

		foreach($orderList as $key => $val)
		{
			$temp = area::name($val['province'],$val['city'],$val['area']);
			$orderList[$key]['province_str'] = $temp[$val['province']];
			$orderList[$key]['city_str']     = $temp[$val['city']];
			$orderList[$key]['area_str']     = $temp[$val['area']];
		}

		return $orderList;
	}

	/**
	 * @brief 把订单商品同步到order_goods表中
	 * @param $order_id 订单ID
	 * @param $goodsInfo 商品和货品信息（购物车数据结构,countSum 最终生成的格式）
	 */
	public function insertOrderGoods($order_id,$goodsResult = array())
	{
		$orderGoodsObj = new IModel('order_goods');

		//清理旧的关联数据
		$orderGoodsObj->del('order_id = '.$order_id);

		$goodsArray = array(
			'order_id' => $order_id
		);

		if(isset($goodsResult['goodsList']))
		{
			foreach($goodsResult['goodsList'] as $key => $val)
			{
				//拼接商品名称和规格数据
				$specArray = array('name' => $val['name'],'goodsno' => $val['goods_no'],'value' => '');

				if(isset($val['spec_array']))
				{
					$spec = block::show_spec($val['spec_array']);
					foreach($spec as $skey => $svalue)
					{
						$specArray['value'] .= $skey.':'.$svalue.',';
					}
					$specArray['value'] = IFilter::addSlash(trim($specArray['value'],','));
				}

				$goodsArray['product_id']  = $val['product_id'];
				$goodsArray['goods_id']    = $val['goods_id'];
				$goodsArray['img']         = $val['img'];
				$goodsArray['goods_price'] = $val['sell_price'];
				$goodsArray['real_price']  = $val['sell_price'] - $val['reduce'];
				$goodsArray['goods_nums']  = $val['count'];
				$goodsArray['goods_weight']= $val['weight'];
				$goodsArray['goods_array'] = JSON::encode($specArray);
				$orderGoodsObj->setData($goodsArray);
				$orderGoodsObj->add();
			}
		}
	}
	/**
	 * 获取订单状态
	 * @param $orderRow array('status' => '订单状态','pay_type' => '支付方式ID','distribution_status' => '配送状态','pay_status' => '支付状态')
	 * @return int 订单状态值 0:未知; 1:未付款等待发货(货到付款); 2:等待付款(线上支付); 3:已发货(未付款+已付款); 4:已付款等待发货; 5:已取消; 6:已完成(已付款,已收货); 7:已退款; 8:部分发货(不需要付款); 9:部分退款(未发货+部分发货); 10:部分退款(已发货);
	 */
	public static function getOrderStatus($orderRow)
	{
		//1,刚生成订单,未付款
		if($orderRow['status'] == 1)
		{
			//选择货到付款
			if($orderRow['pay_type'] == 0)
			{
				if($orderRow['distribution_status'] == 0)
				{
					return 1;
				}
				else if($orderRow['distribution_status'] == 1)
				{
					return 3;
				}
				else if($orderRow['distribution_status'] == 2)
				{
					return 8;
				}
			}
			//选择在线支付
			else
			{
				return 2;
			}
		}
		//2,已经付款
		else if($orderRow['status'] == 2)
		{
			if($orderRow['distribution_status'] == 0)
			{
				return 4;
			}
			else if($orderRow['distribution_status'] == 1)
			{
				return 3;
			}
			else if($orderRow['distribution_status'] == 2)
			{
				return 8;
			}
		}
		//3,取消或者作废订单
		else if($orderRow['status'] == 3 || $orderRow['status'] == 4)
		{
			return 5;
		}
		//4,完成订单
		else if($orderRow['status'] == 5)
		{
			return 6;
		}
		//5,退款
		else if($orderRow['status'] == 6)
		{
			return 7;
		}
		//6,部分退款
		else if($orderRow['status'] == 7)
		{
			//发货
			if($orderRow['distribution_status'] == 1)
			{
				return 10;
			}
			//未发货
			else
			{
				return 9;
			}
		}
		return 0;
	}

	//获取订单支付状态
	public static function getOrderPayStatusText($orderRow)
	{
		if($orderRow['pay_status'] == 0)
		{
			return '未付款';
		}
		else if($orderRow['pay_status'] == 1)
		{
			return '已付款';
		}
		return '未知';
	}

	//获取订单类型
	public static function getOrderTypeText($orderRow)
	{
		switch($orderRow['type'])
		{
			case "1":
			{
				return '团购订单';
			}
			break;

			case "2":
			{
				return '抢购订单';
			}
			break;

			default:
			{
				return '普通订单';
			}
		}
	}

	//获取订单配送状态
	public static function getOrderDistributionStatusText($orderRow)
	{
		if($orderRow['status'] == 5)
		{
			return '已收货';
		}
		else if($orderRow['distribution_status'] == 1)
		{
			return '已发货';
		}
		else if($orderRow['distribution_status'] == 0)
		{
			return '未发货';
		}
		else if($orderRow['distribution_status'] == 2)
		{
			return '部分发货';
		}
	}

	/**
	 * 获取订单状态问题说明
	 * @param $statusCode int 订单的状态码
	 * @return string 订单状态说明
	 */
	public static function orderStatusText($statusCode)
	{
		$result = array(
			0 => '未知',
			1 => '等待发货',
			2 => '等待付款',
			3 => '已发货',
			4 => '等待发货',
			5 => '已取消',
			6 => '已完成',
			7 => '已退款',
			8 => '部分发货',
			9 => '部分发货',
			10=> '部分退款',
		);
		return isset($result[$statusCode]) ? $result[$statusCode] : '';
	}

	/**
	 * @breif 订单的流向
	 * @param $orderRow array 订单数据
	 * @return array('时间' => '事件')
	 */
	public static function orderStep($orderRow)
	{
		$result = array();

		//1,创建订单
		$result[$orderRow['create_time']] = '订单创建';

		//2,订单支付
		if($orderRow['pay_status'] > 0)
		{
			$result[$orderRow['pay_time']] = '订单付款  '.$orderRow['order_amount'];
		}

		//3,订单配送
        if($orderRow['distribution_status'] > 0)
        {
        	$result[$orderRow['send_time']] = '订单发货完成';
    	}

		//4,订单完成
        if($orderRow['status'] == 5)
        {
        	$result[$orderRow['completion_time']] = '订单完成';
        }
        ksort($result);
        return $result;
	}

	/**
	 * @brief 商品发货接口
	 * @param string $order_id 订单id
	 * @param array $order_goods_relation 订单与商品关联id
	 * @param int $sendor_id 操作者id
	 * @param string $sendor 操作者所属 admin,seller
	 */
	public static function sendDeliveryGoods($order_id,$order_goods_relation,$sendor_id,$sendor = 'admin')
	{
		$order_no = IFilter::act(IReq::get('order_no'));

	 	$paramArray = array(
	 		'order_id'      => $order_id,
	 		'user_id'       => IFilter::act(IReq::get('user_id'),'int'),
	 		'name'          => IFilter::act(IReq::get('name')),
	 		'postcode'      => IFilter::act(IReq::get('postcode'),'int'),
	 		'telphone'      => IFilter::act(IReq::get('telphone')),
	 		'province'      => IFilter::act(IReq::get('province'),'int'),
	 		'city'          => IFilter::act(IReq::get('city'),'int'),
	 		'area'          => IFilter::act(IReq::get('area'),'int'),
	 		'address'       => IFilter::act(IReq::get('address')),
	 		'mobile'        => IFilter::act(IReq::get('mobile')),
	 		'freight'       => IFilter::act(IReq::get('freight'),'float'),
	 		'delivery_code' => IFilter::act(IReq::get('delivery_code')),
	 		'delivery_type' => IFilter::act(IReq::get('delivery_type')),
	 		'note'          => IFilter::act(IReq::get('note'),'text'),
	 		'time'          => ITime::getDateTime(),
	 		'freight_id'    => IFilter::act(IReq::get('freight_id'),'int'),
	 	);

	 	switch($sendor)
	 	{
	 		case "admin":
	 		{
	 			$paramArray['admin_id'] = $sendor_id;

	 			$adminDB = new IModel('admin');
	 			$sendorData = $adminDB->getObj('id = '.$sendor_id);
	 			$sendorName = $sendorData['admin_name'];
	 			$sendorSort = '管理员';
	 		}
	 		break;

	 		case "seller":
	 		{
	 			$paramArray['seller_id'] = $sendor_id;

	 			$sellerDB = new IModel('seller');
	 			$sendorData = $sellerDB->getObj('id = '.$sendor_id);
	 			$sendorName = $sendorData['true_name'];
	 			$sendorSort = '加盟商户';
	 		}
	 		break;
	 	}

	 	//获得delivery_doc表的对象
	 	$tb_delivery_doc = new IModel('delivery_doc');
	 	$tb_delivery_doc->setData($paramArray);
	 	$deliveryId = $tb_delivery_doc->add();

		//更新发货状态
	 	$orderGoodsDB = new IModel('order_goods');
	 	$orderGoodsRow = $orderGoodsDB->getObj('is_send = 0 and order_id = '.$order_id,'count(*) as num');
		$sendStatus = 2;//部分发货
	 	if(count($order_goods_relation) >= $orderGoodsRow['num'])
	 	{
	 		$sendStatus = 1;//全部发货
	 	}
	 	foreach($order_goods_relation as $key => $val)
	 	{
	 		$orderGoodsDB->setData(array(
	 			"is_send"     => 1,
	 			"delivery_id" => $deliveryId,
	 		));
	 		$orderGoodsDB->update(" id = {$val} ");
	 	}

	 	//更新发货状态
	 	$tb_order = new IModel('order');
	 	$tb_order->setData(array
	 	(
	 		'distribution_status' => $sendStatus,
	 		'send_time'           => ITime::getDateTime(),
	 	));
	 	$tb_order->update('id='.$order_id);

	 	//生成订单日志
    	$tb_order_log = new IModel('order_log');
    	$tb_order_log->setData(array(
    		'order_id' => $order_id,
    		'user'     => $sendorName,
    		'action'   => '发货',
    		'result'   => '成功',
    		'note'     => '订单【'.$order_no.'】由【'.$sendorSort.'】'.$sendorName.'发货',
    		'addtime'  => date('Y-m-d H:i:s')
    	));
    	$sendResult = $tb_order_log->add();

		//获取货运公司
    	$freightDB  = new IModel('freight_company');
    	$freightRow = $freightDB->getObj('id = '.$paramArray['freight_id']);

    	//发送短信
    	$replaceData = array(
    		'{user_name}'        => $paramArray['name'],
    		'{order_no}'         => $order_no,
    		'{sendor}'           => '['.$sendorSort.']'.$sendorName,
    		'{delivery_company}' => $freightRow['freight_name'],
    		'{delivery_no}'      => $paramArray['delivery_code'],
    	);
    	$mobileMsg = smsTemplate::sendGoods($replaceData);
    	Hsms::send($paramArray['mobile'],$mobileMsg);

    	//同步发货接口，如支付宝担保交易等
    	if($sendResult && $sendStatus == 1)
    	{
    		sendgoods::run($order_id);
    	}
	}

	/**
	 * @biref 是否可以发货操作
	 * @param array $orderRow 订单对象
	 */
	public static function isGoDelivery($orderRow)
	{
		/* 1,已经完全发货
		 * 2,非货到付款，并且没有支付*/
		if($orderRow['distribution_status'] == 1 || ($orderRow['pay_type'] != 0 && $orderRow['pay_status'] == 0))
		{
			return false;
		}
		return true;
	}

	/**
	 * @brief 获取商品发送状态
	 */
	public static function goodsSendStatus($is_send)
	{
		$data = array(0 => '未发货',1 => '已发货',2 => '已退货');
		return isset($data[$is_send]) ? $data[$is_send] : '';
	}

	//获取订单商品信息
	public static function getOrderGoods($order_id)
	{
		$orderGoodsObj        = new IQuery('order_goods');
		$orderGoodsObj->where = "order_id = ".$order_id;
		$orderGoodsObj->fields = 'id,goods_array';
		$orderGoodsList = $orderGoodsObj->find();
		$goodList = array();
		foreach($orderGoodsList as $good)
		{
			$goodList[] = json_decode($good['goods_array']);
		}
		return $goodList;
	}

	/**
	 * @brief 返回检索条件相关信息
	 * @param int $search 条件数组
	 * @return array 查询条件（$join,$where）数据组
	 */
	public static function getSearchCondition($search)
	{
		$join  = "left join delivery as d on o.distribution = d.id left join payment as p on o.pay_type = p.id left join user as u on u.id = o.user_id";
		$where = "if_del = 0";
		//查询检索过滤
		if($search)
		{
			if(isset($search['name']) && isset($search['keywords']) && $search['name'] && $search['keywords'])
			{
				switch($search['name'])
				{
					case "seller_name":
					{
						$sellerObj = new IModel('seller');
						$sellerRow = $sellerObj->getObj('true_name = "'.$search['keywords'].'"');
						$orderId = array(0);
						if($sellerRow)
						{
							$orderGoodsObj        = new IQuery('order_goods as og');
							$orderGoodsObj->join  = "left join goods as go on og.goods_id = go.id";
							$orderGoodsObj->where = "go.seller_id = ".$sellerRow['id'];
							$orderGoodsObj->distinct= "og.order_id";
							$orderGoodsList = $orderGoodsObj->find();
							foreach($orderGoodsList as $key => $val)
							{
								$orderId[] = $val['order_id'];
							}
							array_shift($orderId);
						}
						$where .= " and o.id in (".join(',',$orderId).")";
					}
					break;

					default:
					{
						$where .= " and o.".$search['name']." = '".$search['keywords']."'";
					}
					break;
				}
			}

			foreach($search as $key => $val)
			{
				if(!in_array($key,array('keywords','name')) && $val!='')
				{
					$where .= " and o.".$key." = ".$val;
				}
			}
		}
		$results = array($join,$where);
		unset($join,$where);
		return $results;
	}

	/**
	 * @brief 是否允许退款申请
	 * @param array $orderRow 订单表的数据结构
	 * @return boolean true or false
	 */
	public static function isRefundmentApply($orderRow)
	{
		//已经付款
		if($orderRow['pay_status'] == 1 && $orderRow['status'] != 6)
		{
			return true;
		}
		return false;
	}

	/**
	 * @brief 退款状态
	 * @param int $pay_status 退款单状态数值
	 * @return string 状态描述
	 */
	public static function refundmentText($pay_status)
	{
		$result = array('0' => '申请退款', '1' => '退款失败', '2' => '退款成功');
		return isset($result[$pay_status]) ? $result[$pay_status] : '';
	}

	/**
	 * @brief 还原重置订单所使用的道具
	 * @param int $order 订单ID
	 */
	public static function resetOrderProp($order_id)
	{
		$orderDB   = new IModel('order');
		$orderList = $orderDB->query('id in ( '.$order_id.' ) and pay_status = 0 and prop is not null');
		foreach($orderList as $key => $orderRow)
		{
			if(isset($orderRow['prop']) && $orderRow['prop'])
			{
				$propDB = new IModel('prop');
				$propDB->setData(array('is_close' => 0));
				$propDB->update('id = '.$orderRow['prop']);
			}
		}
	}

	/**
	 * @brief 商户对退款申请的处理权限
	 * @param int $refundId 退款单ID
	 * @param int $seller_id 商户ID
	 * @return int 退款权限状态, 0:无权查看；1:只读；2：可读可写
	 */
	public static function isSellerRefund($refundId,$seller_id)
	{
		$refundDB = new IModel('refundment_doc');
		$refundRow= $refundDB->getObj('id = '.$refundId.' and seller_id = '.$seller_id);
		if($refundRow)
		{
			$orderGoodsDB = new IModel('order_goods');
			$orderGoodsRow= $orderGoodsDB->getObj('order_id = '.$refundRow['order_id'].' and goods_id = '.$refundRow['goods_id'].' and product_id = '.$refundRow['product_id']);
			if($orderGoodsRow['is_checkout'] == 1)
			{
				return 1;
			}
			else
			{
				return 2;
			}
		}
		return 0;
	}

	/**
	 * @brief 订单退款操作
	 * @param int $refundId 退款单ID
	 * @param int $authorId 操作人ID
	 * @param string $type admin:管理员;seller:商户
	 * @return
	 */
	public static function refund($refundId,$authorId,$type = 'admin')
	{
		$orderGoodsDB= new IModel('order_goods');
		$refundDB    = new IModel('refundment_doc');

		//更新退款表
		$updateData = array(
			'pay_status'   => 2,
			'dispose_time' => ITime::getDateTime(),
		);
		$refundDB->setData($updateData);
		$refundDB->update('id = '.$refundId);

		//获取goods_id和product_id用于给用户减积分，经验
		$refundsRow = $refundDB->getObj('id = '.$refundId);
		$order_id   = $refundsRow['order_id'];
		$order_no   = $refundsRow['order_no'];
		$amount     = $refundsRow['amount'];
		$user_id    = $refundsRow['user_id'];

		$orderGoodsRow = $orderGoodsDB->getObj('order_id = '.$order_id.' and goods_id = '.$refundsRow['goods_id'].' and product_id = '.$refundsRow['product_id']);
		$order_goods_id = $orderGoodsRow['id'];

		//更新退款状态
		$orderGoodsDB->setData(array('is_send' => 2));
		$orderGoodsDB->update('id = '.$order_goods_id);

		//更新order表状态
		$isSendData = $orderGoodsDB->getObj('order_id = '.$order_id.' and is_send != 2');
		$orderStatus = 6;//全部退款
		if($isSendData)
		{
			$orderStatus = 7;//部分退款
		}
		$tb_order = new IModel('order');
		$tb_order->setData(array('status' => $orderStatus));
		$tb_order->update('id='.$order_id);

		if($orderStatus == 6)
		{
			Order_class::resetOrderProp($order_id);
		}

		//生成订单日志
		$authorName = $type == 'admin' ? ISafe::get('admin_name') : ISafe::get('seller_name');
		$tb_order_log = new IModel('order_log');
		$tb_order_log->setData(array(
			'order_id' => $order_id,
			'user'     => $authorName,
			'action'   => '退款',
			'result'   => '成功',
			'note'     => '订单【'.$order_no.'】退款，退款金额：￥'.$amount,
			'addtime'  => ITime::getDateTime(),
		));
		$tb_order_log->add();

		/**
		 * 进行用户的余额增加操作,积分，经验的减少操作,
		 * 1,当全部退款时候,减少订单中记录的积分和经验;
		 * 2,当部分退款时候,查询商品表中积分和经验
		 */
		if($orderStatus == 6)
		{
			$orderRow = $tb_order->getObj('id = '.$order_id);
		}
		else
		{
			$goodsDB = new IModel('goods');
			$goodsRow= $goodsDB->getObj('id = '.$orderGoodsRow['goods_id']);
			$orderRow = array(
				'exp'      => $goodsRow['exp'],
				'point'    => $goodsRow['point'],
				'order_no' => $order_no,
			);
		}

		$obj = new IModel('member');
		$memberObj = $obj->getObj('user_id = '.$user_id,'balance,exp,point');

		$exp     = $memberObj['exp'] - $orderRow['exp'];
		$balance = $memberObj['balance'] + $amount;

		$obj->setData(array(
			'balance' => $balance,
			'exp'     => $exp   <= 0 ? 0 : $exp,
		));
		$isSuccess = $obj->update('user_id = '.$user_id);

		//积分记录日志
		$pointConfig = array(
			'user_id' => $user_id,
			'point'   => '-'.$orderRow['point'],
			'log'     => '退款订单号：'.$orderRow['order_no'].'中的商品,减掉积分 -'.$orderRow['point'],
		);
		$pointObj = new Point();
		$pointObj->update($pointConfig);

		if($isSuccess)
		{
			//用户余额进行的操作记入account_log表
			$log = new AccountLog();
			$config = array(
				'user_id'  => $user_id,
				'event'    => 'drawback', //withdraw:提现,pay:余额支付,recharge:充值,drawback:退款到余额
				'num'      => $amount, //整形或者浮点，正为增加，负为减少
				'order_no' => $order_no // drawback类型的log需要这个值
			);

			if($type == 'admin')
			{
				$config['admin_id'] = $authorId;
			}
			else if($type == 'seller')
			{
				$config['seller_id'] = $authorId;
			}

			$re = $log->write($config);
			return true;
		}
		return false;
	}
}