<?php
/**
 * @copyright Copyright(c) 2014 appoil.com
 * @file Simple.php
 * @brief
 * @author Jason
 * @date 2014-03-22
 * @version 0.6
 * @note
 */
/**
 * @brief Simple
 * @class Simple
 * @note
 */
class Simple extends IController
{
    public $layout='site_mini';

	function init()
	{
		CheckRights::checkUserRights();
	}

	function login()
	{
		//如果已经登录，就跳到ucenter页面
		if( ISafe::get('user_id') != null  )
		{
			$this->redirect("/ucenter/index");
		}
		else
		{
			$this->redirect('login');
		}
	}

	//退出登录
    function logout()
    {
    	ISafe::clearAll();
    	$this->redirect('login');
    }

    //用户注册
    function reg_act()
    {
    	$email      = IFilter::act(IReq::get('email','post'));
    	$username   = IFilter::act(IReq::get('username','post'));
    	$password   = IFilter::act(IReq::get('password','post'));
    	$repassword = IFilter::act(IReq::get('repassword','post'));
    	$captcha    = IFilter::act(IReq::get('captcha','post'));
    	$callback   = IFilter::act(IReq::get('callback'),'text');
    	$message    = '';

    	//获取注册配置参数
		$siteConfig = new Config('site_config');
		$reg_option = $siteConfig->reg_option;

		/*注册信息校验*/
		if($reg_option == 2)
		{
			$message = '当前网站禁止新用户注册';
		}
    	else if(IValidate::email($email) == false)
    	{
    		$message = '邮箱格式不正确';
    	}
    	else if(!Util::is_username($username))
    	{
    		$message = '用户名必须是由2-20个字符，可以为字数，数字下划线和中文';
    	}
    	else if(!preg_match('|\S{6,32}|',$password))
    	{
    		$message = '密码必须是字母，数字，下划线组成的6-32个字符';
    	}
    	else if($password != $repassword)
    	{
    		$message = '2次密码输入不一致';
    	}
    	else if($captcha != ISafe::get('captcha'))
    	{
    		$message = '验证码输入不正确';
    	}
    	else
    	{
    		$userObj = new IModel('user');
    		$where   = 'email = "'.$email.'" or username = "'.$email.'" or username = "'.$username.'"';
    		$userRow = $userObj->getObj($where);

    		if($userRow)
    		{
    			if($email == $userRow['email'])
    			{
    				$message = '此邮箱已经被注册过，请重新更换';
    			}
    			else
    			{
    				$message = "此用户名已经被注册过，请重新更换";
    			}
    		}
    		else
    		{
	    		//user表
	    		$userArray = array(
	    			'username' => $username,
	    			'password' => md5($password),
	    			'email'    => $email,
	    		);
	    		$userObj->setData($userArray);
	    		$user_id = $userObj->add();

	    		if($user_id)
	    		{
					//member表
		    		$memberArray = array(
		    			'user_id' => $user_id,
		    			'time'    => ITime::getDateTime(),
		    			'status'  => $reg_option == 1 ? 3 : 1,
		    		);

		    		$memberObj = new IModel('member');
		    		$memberObj->setData($memberArray);
		    		$memberObj->add();

		    		//邮箱激活帐号
		    		if($reg_option == 1)
		    		{
		    			$this->send_check_mail();
		    		}
		    		else
		    		{
			    		//用户私密数据
			    		ISafe::set('username',$username);
			    		ISafe::set('user_id',$user_id);
			    		ISafe::set('user_pwd',$userArray['password']);

						//自定义跳转页面
						$callback = $callback ? urlencode($callback) : '';
						$this->redirect('/site/success?message='.urlencode("注册成功！").'&callback='.$callback);
		    		}
	    		}
	    		else
	    		{
	    			$message = '对不起，注册失败';
	    		}
	    	}
    	}

		//出错信息展示
    	if($message)
    	{
    		$this->email    = $email;
    		$this->username = $username;

    		$this->redirect('reg',false);
    		Util::showMessage($message);
    	}
    }

    //用户登录
    function login_act()
    {
    	$login_info = IFilter::act(IReq::get('login_info','post'));
    	$password   = IReq::get('password','post');
    	$remember   = IFilter::act(IReq::get('remember','post'));
    	$autoLogin  = IFilter::act(IReq::get('autoLogin','post'));
    	$callback   = IFilter::act(IReq::get('callback'),'text');
		$message    = '';
		$password   = md5($password);

    	if($login_info == '')
    	{
    		$message = '请填写用户名或者邮箱';
    	}
		else if(!preg_match('|\S{6,32}|',$password))
    	{
    		$message = '密码格式不正确,请输入6-32个字符';
    	}
    	else
    	{
    		if($userRow = CheckRights::isValidUser($login_info,$password))
    		{
				$this->loginAfter($userRow);

				//记住帐号
				if($remember == 1)
				{
					ICookie::set('loginName',$login_info);
				}

				//自动登录
				if($autoLogin == 1)
				{
					ICookie::set('autoLogin',$autoLogin);
				}

				//自定义跳转页面
				if($callback && !strpos($callback,'reg') && !strpos($callback,'login'))
				{
					$this->redirect($callback);
				}
				else
				{
					$this->redirect('/ucenter/index');
				}
    		}
    		else
    		{
    			//邮箱未验证
    			$userDB = new IModel('user as u,member as m');
    			$userRow= $userDB->getObj(" (u.email = '{$login_info}' or u.username = '{$login_info}') and password = '{$password}' ");
				if($userRow)
				{
					$siteConfig = new Config('site_config');
					if($userRow['status'] == 3 && $siteConfig->reg_option == 1)
					{
						$message = "您的邮箱还未验证，请点击下面的链接发送您的邮箱验证邮件！";
						$this->redirect('/site/success?message='.urlencode($message).'&email='.$userRow['email']);
					}
				}
				else
				{
					$message = '用户名和密码不匹配';
				}
    		}
    	}

    	//错误信息
    	if($message)
    	{
    		$this->message = $message;
    		$_GET['callback'] = $callback;
    		$this->redirect('login',false);
    	}
    }

	//登录后的处理
    function loginAfter($userRow)
    {
		//用户私密数据
		ISafe::set('user_id',$userRow['id']);
		ISafe::set('username',$userRow['username']);
		ISafe::set('head_ico',$userRow['head_ico']);
		ISafe::set('user_pwd',$userRow['password']);
		ISafe::set('last_login',$userRow['last_login']);

		//更新最后一次登录时间
		$memberObj = new IModel('member');
		$dataArray = array(
			'last_login' => ITime::getDateTime(),
		);
		$memberObj->setData($dataArray);
		$where     = 'user_id = '.$userRow["id"];
		$memberObj->update($where);
		$memberRow = $memberObj->getObj($where,'exp');

		//根据经验值分会员组
		$groupObj = new IModel('user_group');
		$groupRow = $groupObj->getObj($memberRow['exp'].' between minexp and maxexp and minexp > 0 and maxexp > 0','id','discount','desc');
		if(!empty($groupRow))
		{
			$dataArray = array('group_id' => $groupRow['id']);
			$memberObj->setData($dataArray);
			$memberObj->update('user_id = '.$userRow["id"]);
		}
    }

    //商品加入购物车[ajax]
    function joinCart()
    {
    	$link       = IReq::get('link');
    	$goods_id   = intval(IReq::get('goods_id'));
    	$goods_num  = IReq::get('goods_num') === null ? 1 : intval(IReq::get('goods_num'));
    	$type       = IFilter::act(IReq::get('type'));

		//加入购物车
    	$cartObj   = new Cart();
    	$addResult = $cartObj->add($goods_id,$goods_num,$type);

    	if($link != '')
    	{
    		if($addResult === false)
    		{
    			$this->cart(false);
    			Util::showMessage($cartObj->getError());
    		}
    		else
    		{
    			$this->redirect($link);
    		}
    	}
    	else
    	{
	    	if($addResult === false)
	    	{
		    	$result = array(
		    		'isError' => true,
		    		'message' => $cartObj->getError(),
		    	);
	    	}
	    	else
	    	{
		    	$result = array(
		    		'isError' => false,
		    		'message' => '添加成功',
		    	);
	    	}
	    	echo JSON::encode($result);
    	}
    }

    //根据goods_id获取货品
    function getProducts()
    {
    	$id           = IFilter::act(IReq::get('id'),'int');
    	$productObj   = new IModel('products');
    	$productsList = $productObj->query('goods_id = '.$id,'sell_price,id,spec_array,goods_id','store_nums','desc',7);
		if($productsList)
		{
			foreach($productsList as $key => $val)
			{
				$productsList[$key]['specData'] = Block::show_spec($val['spec_array']);
			}
			echo JSON::encode($productsList);
		}
    }

    //删除购物车
    function removeCart()
    {
    	$link      = IReq::get('link');
    	$goods_id  = IFilter::act(IReq::get('goods_id'),'int');
    	$type      = IReq::get('type');

    	$cartObj   = new Cart();
    	$cartInfo  = $cartObj->getMyCart();
    	$delResult = $cartObj->del($goods_id,$type);

    	if($link != '')
    	{
    		if($delResult === false)
    		{
    			$this->cart(false);
    			Util::showMessage($cartObj->getError());
    		}
    		else
    		{
    			$this->redirect($link);
    		}
    	}
    	else
    	{
	    	if($delResult === false)
	    	{
	    		$result = array(
		    		'isError' => true,
		    		'message' => $cartObj->getError(),
	    		);
	    	}
	    	else
	    	{
		    	$goodsRow = $cartInfo[$type]['data'][$goods_id];
		    	$cartInfo['sum']   -= $goodsRow['sell_price'] * $goodsRow['count'];
		    	$cartInfo['count'] -= $goodsRow['count'];

		    	$result = array(
		    		'isError' => false,
		    		'data'    => $cartInfo,
		    	);
	    	}

	    	echo JSON::encode($result);
    	}
    }

    //清空购物车
    function clearCart()
    {
    	$cartObj = new Cart();
    	$cartObj->clear();
    	$this->redirect('cart');
    }

    //购物车div展示
    function showCart()
    {
    	$cartObj  = new Cart();
    	$cartList = $cartObj->getMyCart();
    	$data['data'] = array_merge($cartList['goods']['data'],$cartList['product']['data']);
    	$data['count']= $cartList['count'];
    	$data['sum']  = $cartList['sum'];
    	echo JSON::encode($data);
    }

    //购物车页面及商品价格计算[复杂]
    function cart($redirect = false)
    {
    	//防止页面刷新
    	header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);

		//开始计算购物车中的商品价格
    	$countObj = new CountSum();
    	$result   = $countObj->cart_count();

    	//返回值
    	$this->final_sum = $result['final_sum'];
    	$this->promotion = $result['promotion'];
    	$this->proReduce = $result['proReduce'];
    	$this->sum       = $result['sum'];
    	$this->goodsList = $result['goodsList'];
    	$this->count     = $result['count'];
    	$this->reduce    = $result['reduce'];
    	$this->weight    = $result['weight'];

		//渲染视图
    	$this->redirect('cart',$redirect);
    }

    //计算促销规则[ajax]
    function promotionRuleAjax()
    {
    	$promotion = array();
    	$proReduce = 0;

    	//总金额满足的促销规则
    	if($this->user['user_id'])
    	{
    		$final_sum = intval(IReq::get('final_sum'));

    		//获取 user_group
	    	$groupObj = new IModel('member as m,user_group as g');
			$groupRow = $groupObj->getObj('m.user_id = '.$this->user['user_id'].' and m.group_id = g.id','g.*');
			$groupRow['id'] = empty($groupRow) ? 0 : $groupRow['id'];

	    	$proObj = new ProRule($final_sum);
	    	$proObj->setUserGroup($groupRow['id']);

	    	$promotion = $proObj->getInfo();
	    	$proReduce = $final_sum - $proObj->getSum();
    	}

		$result = array(
    		'promotion' => $promotion,
    		'proReduce' => $proReduce,
		);

    	echo JSON::encode($result);
    }

    //购物车寄存功能[写入]
    function deposit_cart_set()
    {
    	$is_ajax = IReq::get('is_ajax');

    	//必须为登录用户
    	if($this->user['user_id'] == null)
    	{
			$callback = "/simple/cart";
    		$this->redirect('/simple/login?callback={$callback}');
    	}

    	//获取购物车中的信息
    	$cartObj    = new Cart();
    	$myCartInfo = $cartObj->getMyCart();

		/*寄存的数据
		格式：goods => array (id => count);
		*/
    	$depositArray = array();

    	if(isset($myCartInfo['goods']['id']) && !empty($myCartInfo['goods']['id']))
    	{
    		foreach($myCartInfo['goods']['id'] as $id)
    		{
    			$depositArray['goods'][$id]   = $myCartInfo['goods']['data'][$id]['count'];
    		}
    	}

    	if(isset($myCartInfo['product']['id']) && !empty($myCartInfo['product']['id']))
    	{
    		foreach($myCartInfo['product']['id'] as $id)
    		{
    			$depositArray['product'][$id] = $myCartInfo['product']['data'][$id]['count'];
    		}
    	}

    	if(empty($depositArray))
    	{
    		$isError = true;
    		$message = '您的购物车中没有商品';
    	}
    	else
    	{
    		$isError = false;
	    	$dataArray   = array(
	    		'user_id'     => $this->user['user_id'],
	    		'content'     => serialize($depositArray),
	    		'create_time' => ITime::getDateTime(),
	    	);

	    	$goodsCarObj = new IModel('goods_car');
	    	$goodsCarRow = $goodsCarObj->getObj('user_id = '.$this->user['user_id']);
	    	$goodsCarObj->setData($dataArray);

	    	if(empty($goodsCarRow))
	    	{
	    		$goodsCarObj->add();
	    	}
	    	else
	    	{
	    		$goodsCarObj->update('user_id = '.$this->user['user_id']);
	    	}
	    	$message = '寄存成功';
    	}

		//ajax方式
    	if($is_ajax == 1)
    	{
    		$result = array(
    			'isError' => $isError,
    			'message' => $message,
    		);

    		echo JSON::encode($result);
    	}

    	//传统跳转方式
    	else
    	{
			//页面跳转
			$this->cart();
	    	if(isset($message))
	    	{
	    		Util::showMessage($message);
	    	}
    	}
    }

    //购物车寄存功能[读取]ajax
    function deposit_cart_get()
    {
    	//isError:0正常;1错误
    	$result = array('isError' => 1,'message' => '');

    	//必须为登录用户
    	if($this->user['user_id'] == null)
    	{
    		$result['message'] = '用户尚未登录';
    		echo JSON::encode($result);
    		return;
    	}

    	$goodsCatObj = new IModel('goods_car');
    	$goodsCarRow = $goodsCatObj->getObj('user_id = '.$this->user['user_id']);

    	if(!isset($goodsCarRow['content']))
    	{
    		$result['message'] = '您没有寄存任何商品';
    		echo JSON::encode($result);
    		return;
    	}

		$depositContent = unserialize($goodsCarRow['content']);

    	//获取购物车中的信息
    	$cartObj    = new Cart();
    	$myCartInfo = $cartObj->getMyCartStruct();

    	if(isset($depositContent['goods']))
    	{
	    	foreach($depositContent['goods'] as $id => $count)
	    	{
	    		$depositGoods = $cartObj->getUpdateCartData($myCartInfo,$id,$count,'goods');
	    		$myCartInfo = $depositGoods;
	    	}
    	}

    	if(isset($depositContent['product']))
    	{
	    	foreach($depositContent['product'] as $id => $count)
	    	{
	    		$depositProducts = $cartObj->getUpdateCartData($myCartInfo,$id,$count,'product');
	    		$myCartInfo = $depositProducts;
	    	}
    	}

    	//写入购物车
    	$cartObj->setMyCart($myCartInfo);
    	$result['isError'] = 0;
    	echo JSON::encode($result);
    }

    //清空寄存购物车
    function deposit_cart_clear()
    {
    	//必须为登录用户
    	if($this->user['user_id'] == null)
    	{
    		$this->redirect('/simple/login?callback=/simple/cart');
    	}

    	$goodsCarObj = new IModel('goods_car');
    	$goodsCarObj->del('user_id = '.$this->user['user_id']);
    	$this->cart();
    	Util::showMessage('操作成功');
    }

    //填写订单信息cart2
    function cart2()
    {
		$id        = IFilter::act(IReq::get('id'),'int');
		$type      = IFilter::act(IReq::get('type'));
		$promo     = IFilter::act(IReq::get('promo'));
		$active_id = IFilter::act(IReq::get('active_id'),'int');
		$buy_num   = IReq::get('num') ? IFilter::act(IReq::get('num'),'int') : 1;
		$tourist   = IReq::get('tourist');//游客方式购物

		//活动购买方式
		if($promo == 'groupon' && $active_id != '')
		{
			$hashId = $this->user['user_id'] ? $this->user['user_id'] : ICookie::get("regiment_{$active_id}");

			//此团购还存在已经报名但是未付款的情况
			if(regiment::hasJoined($active_id,$hashId) == true)
			{
				IError::show(403,'您已经参加过此次团购，请先完成支付');
				exit;
			}

			//团购已经达到限定的人数
			if(regiment::isFull($active_id) == true)
			{
				IError::show(403,'此团购的参加人数已满');
				exit;
			}
		}

    	//必须为登录用户
    	if($tourist === null && $this->user['user_id'] == null)
    	{
    		if($id == 0 || $type == '')
    		{
    			$this->redirect('/simple/login?tourist&callback=/simple/cart2');
    		}
    		else
    		{
    			$url  = '/simple/login?tourist&callback=/simple/cart2/id/'.$id.'/type/'.$type.'/num/'.$buy_num;
    			$url .= $promo     ? '/promo/'.$promo         : '';
    			$url .= $active_id ? '/active_id/'.$active_id : '';
    			$this->redirect($url);
    		}
    	}

		//游客的user_id默认为0
    	$user_id = ($this->user['user_id'] == null) ? 0 : $this->user['user_id'];

    	//获取收货地址
    	$addressObj  = new IModel('address');
    	$addressList = $addressObj->query('user_id = '.$user_id);

		//更新$addressList数据
    	foreach($addressList as $key => $val)
    	{
    		$temp = area::name($val['province'],$val['city'],$val['area']);

    		$addressList[$key]['province_val'] = $temp[$val['province']];
    		$addressList[$key]['city_val']     = $temp[$val['city']];
    		$addressList[$key]['area_val']     = $temp[$val['area']];
    		if($val['default'] == 1)
    		{
    			$this->defaultAddressId = $val['id'];
    		}
    	}

		//获取用户的道具红包和用户的习惯方式
		$this->prop = array();
		$memberObj = new IModel('member');
		$memberRow = $memberObj->getObj('user_id = '.$user_id,'prop,custom');

		if(isset($memberRow['prop']) && ($propId = trim($memberRow['prop'],',')))
		{
			$porpObj = new IModel('prop');
			$this->prop = $porpObj->query('id in ('.$propId.') and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1','id,name,value,card_name');
		}

		if(isset($memberRow['custom']) && $memberRow['custom'])
		{
			$this->custom = unserialize($memberRow['custom']);
		}
		else
		{
			$this->custom = array(
				'payment'  => '',
				'delivery' => '',
				'takeself' => '',
			);
		}

		//计算商品
		$countSumObj = new CountSum();

		//判断是特定活动还是购物车
		if($id != 0 && $type != '')
		{
			$result = $countSumObj->direct_count($id,$type,$buy_num,$promo,$active_id);
			$this->gid       = $id;
			$this->type      = $type;
			$this->num       = $buy_num;
			$this->promo     = $promo;
			$this->active_id = $active_id;
		}
		else
		{
			//计算购物车中的商品价格
			$result = $countSumObj->cart_count();
		}

		if($result['count'] == 0)
		{
			$this->redirect('/simple/cart');
			exit;
		}

    	//返回值
    	$this->final_sum = $result['final_sum'];
    	$this->promotion = $result['promotion'];
    	$this->proReduce = $result['proReduce'];
    	$this->sum       = $result['sum'];
    	$this->goodsList = $result['goodsList'];
    	$this->count       = $result['count'];
    	$this->reduce      = $result['reduce'];
    	$this->weight      = $result['weight'];
    	$this->freeFreight = $result['freeFreight'];

		//收货地址列表
		$this->addressList = $addressList;

		//获取商品税金
		$this->goodsTax    = $countSumObj->getGoodsTax($this->sum);

    	//渲染页面
    	$this->redirect('cart2');
    }

	/**
	 * 生成订单
	 */
    function cart3()
    {
    	$accept_name   = IFilter::act(IReq::get('accept_name'));
    	$province      = IFilter::act(IReq::get('province'),'int');
    	$city          = IFilter::act(IReq::get('city'),'int');
    	$area          = IFilter::act(IReq::get('area'),'int');
    	$address       = IFilter::act(IReq::get('address'));
    	$mobile        = IFilter::act(IReq::get('mobile'));
    	$telphone      = IFilter::act(IReq::get('telphone'));
    	$zip           = IFilter::act(IReq::get('zip'));
    	$delivery_id   = IFilter::act(IReq::get('delivery_id'),'int');
    	$accept_time   = IFilter::act(IReq::get('accept_time'));
    	$payment       = IFilter::act(IReq::get('payment'),'int');
    	$order_message = IFilter::act(IReq::get('message'));
    	$ticket_id     = IFilter::act(IReq::get('ticket_id'),'int');
    	$taxes         = IFilter::act(IReq::get('taxes'),'float');
    	$insured       = IFilter::act(IReq::get('insured'),'float');
    	$tax_title     = IFilter::act(IReq::get('tax_title'),'text');
    	$gid           = IFilter::act(IReq::get('direct_gid'),'int');
    	$num           = IFilter::act(IReq::get('direct_num'),'int');
    	$type          = IFilter::act(IReq::get('direct_type'));//商品或者货品
    	$promo         = IFilter::act(IReq::get('direct_promo'));
    	$active_id     = IFilter::act(IReq::get('direct_active_id'),'int');
    	$takeself      = IFilter::act(IReq::get('takeself'),'int');
    	$order_no      = Order_Class::createOrderNum();
    	$order_type    = 0;
    	$dataArray     = array();

		//防止表单重复提交
    	if(IReq::get('timeKey') != null)
    	{
    		if(ISafe::get('timeKey') == IReq::get('timeKey'))
    		{
	    		IError::show(403,'订单数据不能被重复提交');
	    		exit;
    		}
    		else
    		{
    			ISafe::set('timeKey',IReq::get('timeKey'));
    		}
    	}

    	if($province == 0 || $city == 0 || $area == 0)
    	{
    		IError::show(403,'请填写收货地址的省市地区');
    	}

    	if($delivery_id == 0)
    	{
    		IError::show(403,'请选择配送方式');
    	}

    	$user_id = ($this->user['user_id'] == null) ? 0 : $this->user['user_id'];

		//活动特殊处理
		if($promo != '' && $active_id != '')
		{
			//团购
			if($promo == 'groupon')
			{
				$hashId = $user_id ? $user_id : ICookie::get("regiment_{$active_id}");

				//此团购还存在已经报名但是未付款的情况
				if(regiment::hasJoined($active_id,$hashId) == true)
				{
					IError::show(403,'您已经参加过此次团购，请先完成支付');
					exit;
				}

				//团购已经达到限定的人数
				if(regiment::isFull($active_id) == true)
				{
					IError::show(403,'此团购的参加人数已满');
					exit;
				}

				$order_type = 1;

				//团购开始报名
				$joinUserId = $user_id ? $user_id : null;
				$resultData = regiment::join($active_id,$joinUserId);
				$is_success = '';

				if($resultData['flag'] == true)
				{
					$regimentRelationObj = new IModel('regiment_user_relation');
					$regimentRelationObj->setData(array('order_no' => $order_no));
					$is_success          = $regimentRelationObj->update('id = '.$resultData['relation_id']);
				}

				if($is_success == '' || $resultData['flag'] == false)
				{
					$errorMsg = ( isset($resultData['data']) && $resultData['data'] != '' ) ? $resultData['data'] : '团购报名失败';
					IError::show(403,$errorMsg);
				}
			}
			//限时抢购
			else if($promo == 'time')
			{
				$order_type = 2;
			}
		}

		//配送方式,判断是否为货到付款
		$deliveryObj = new IModel('delivery');
		$deliveryRow = $deliveryObj->getObj('id = '.$delivery_id);

		if($deliveryRow['type'] == 0)
		{
			if($payment == 0)
			{
				IError::show(403,'请选择正确的支付方式');
			}
		}
		else if($deliveryRow['type'] == 1)
		{
			$payment = 0;
		}
		else if($deliveryRow['type'] == 2)
		{
			if($takeself == 0)
			{
				IError::show(403,'请选择正确的自提点');
			}
		}
		//如果不是自提方式自动清空自提点
		if($deliveryRow['type'] != 2)
		{
			$takeself = 0;
		}

		//计算费用
    	$countSumObj = new CountSum();

    	//直接购买商品方式
    	if($type !='' && $gid != 0)
    	{
    		//计算$gid商品
    		$goodsResult = $countSumObj->direct_count($gid,$type,$num,$promo,$active_id);
    	}
    	else
    	{
			//计算购物车中的商品价格$goodsResult
			$goodsResult = $countSumObj->cart_count();

			//清空购物车
	    	$cartObj = new Cart();
	    	$cartObj->clear();
    	}

    	//判断商品商品是否存在
    	if(empty($goodsResult['goodsList']) || $goodsResult['sum'] <= 0)
    	{
    		IError::show(403,'商品数据错误');
    		exit;
    	}

		//获取红包减免金额
		if($ticket_id != '')
		{
			$memberObj = new IModel('member');
			$memberRow = $memberObj->getObj('user_id = '.$user_id,'prop,custom');

			if(ISafe::get('ticket_'.$ticket_id) == $ticket_id || stripos(','.trim($memberRow['prop'],',').',',','.$ticket_id.',') !== false)
			{
				$propObj   = new IModel('prop');
				$ticketRow = $propObj->getObj('id = '.$ticket_id.' and NOW() between start_time and end_time and type = 0 and is_close = 0 and is_userd = 0 and is_send = 1');
				if(!empty($ticketRow))
				{
					$dataArray['prop'] = $ticket_id;
				}

				//锁定红包状态
				$propObj->setData(array('is_close' => 2));
				$propObj->update('id = '.$ticket_id);
			}
		}

		$paymentObj = new IModel('payment');
		$paymentRow = $paymentObj->getObj('id = '.$payment,'type,name');
		$paymentName= $paymentRow['name'];
		$paymentType= $paymentRow['type'];

		//最终订单金额计算
		$orderData = $countSumObj->countOrderFee($goodsResult['sum'],$goodsResult['final_sum'],$goodsResult['weight'],$province,$delivery_id,$payment,$goodsResult['freeFreight'],$insured,$taxes);

		//生成的订单数据
		$dataArray = array(
			'order_no'            => $order_no,
			'user_id'             => $user_id,
			'accept_name'         => $accept_name,
			'pay_type'            => $payment,
			'distribution'        => $delivery_id,
			'postcode'            => $zip,
			'telphone'            => $telphone,
			'province'            => $province,
			'city'                => $city,
			'area'                => $area,
			'address'             => $address,
			'mobile'              => $mobile,
			'create_time'         => ITime::getDateTime(),
			'postscript'          => $order_message,
			'accept_time'         => $accept_time,
			'exp'                 => $goodsResult['exp'],
			'point'               => $goodsResult['point'],
			'type'                => $order_type,

			//红包道具
			'prop'                => isset($dataArray['prop']) ? $dataArray['prop'] : null,

			//商品价格
			'payable_amount'      => $goodsResult['sum'],
			'real_amount'         => $goodsResult['final_sum'],

			//运费价格
			'payable_freight'     => $orderData['deliveryOrigPrice'],
			'real_freight'        => $orderData['deliveryPrice'],

			//手续费
			'pay_fee'             => $orderData['paymentPrice'],

			//税金
			'invoice'             => $taxes ? 1 : 0,
			'invoice_title'       => $tax_title,
			'taxes'               => $taxes,

			//优惠价格
			'promotions'          => $goodsResult['proReduce'] + $goodsResult['reduce'] + (isset($ticketRow['value']) ? $ticketRow['value'] : 0),

			//订单应付总额
			'order_amount'        => $orderData['orderAmountPrice'] - (isset($ticketRow['value']) ? $ticketRow['value'] : 0),

			//订单保价
			'if_insured'          => $insured ? 1 : 0,
			'insured'             => $insured,

			//自提点ID
			'takeself'            => $takeself,
		);

		$dataArray['order_amount'] = $dataArray['order_amount'] <= 0 ? 0 : $dataArray['order_amount'];

		$orderObj  = new IModel('order');
		$orderObj->setData($dataArray);

		$this->order_id = $orderObj->add();

		if($this->order_id == false)
		{
			IError::show(403,'订单生成错误');
		}

		/*将订单中的商品插入到order_goods表*/
    	$orderInstance = new Order_Class();
    	$orderInstance->insertOrderGoods($this->order_id,$goodsResult);

		//记录用户默认习惯的数据
		if(!isset($memberRow['custom']))
		{
			$memberObj = new IModel('member');
			$memberRow = $memberObj->getObj('user_id = '.$user_id,'custom');
		}

		$memberData = array(
			'custom' => serialize(
				array(
					'payment'  => $payment,
					'delivery' => $delivery_id,
					'takeself' => $takeself,
				)
			),
		);
		$memberObj->setData($memberData);
		$memberObj->update('user_id = '.$user_id);

		//收货地址的处理
		if($user_id)
		{
			$addressObj = new IModel('address');

			//如果用户之前没有收货地址,那么会自动记录此次的地址信息并且为默认
			$addressRow = $addressObj->getObj('user_id = '.$user_id);
			if(empty($addressRow))
			{
				$addressData = array('default'=>'1','user_id'=>$user_id,'accept_name'=>$accept_name,'province'=>$province,'city'=>$city,'area'=>$area,'address'=>$address,'zip'=>$zip,'telphone'=>$telphone,'mobile'=>$mobile);
				$addressObj->setData($addressData);
				$addressObj->add();
			}
			else
			{
				//如果用户有收货地址,但是没有设置默认项,那么会自动设置此次地址信息为默认
				$radio_address = intval(IReq::get('radio_address'));
				if($radio_address != 0)
				{
					$addressDefRow = $addressObj->getObj('user_id = '.$user_id.' and `default` = 1');
					if(empty($addressDefRow))
					{
						$addressData = array('default' => 1);
						$addressObj->setData($addressData);
						$addressObj->update('user_id = '.$user_id.' and id = '.$radio_address);
					}
				}
			}
		}

		//获取备货时间
		$siteConfigObj = new Config("site_config");
		$site_config   = $siteConfigObj->getInfo();
		$this->stockup_time = isset($site_config['stockup_time'])?$site_config['stockup_time']:2;

		//数据渲染
		$this->order_num   = $dataArray['order_no'];
		$this->final_sum   = $dataArray['order_amount'];
		$this->payment     = $paymentName;
		$this->paymentType = $paymentType;
		$this->delivery    = $deliveryRow['name'];
		$this->tax_title   = $tax_title;
		$this->deliveryType= $deliveryRow['type'];

		//自提方式短信验证
		if($takeself > 0)
		{
			$takeselfObj = new IModel('takeself');
			$takeselfRow = $takeselfObj->getObj('id = '.$takeself);
			if($takeselfRow)
			{
				$smsContent = smsTemplate::takeself(array('{orderNo}' => $order_no,'{address}' => $takeselfRow['address']));
				$result = Hsms::send($mobile,$smsContent);
			}
		}

		//订单金额为0时，订单自动完成
		if($this->final_sum <= 0)
		{
			$order_id = Order_Class::updateOrderStatus($dataArray['order_no']);
			if($order_id != '')
			{
				if($user_id)
				{
					$this->redirect('/site/success/message/'.urlencode("订单确认成功，等待发货").'/?callback=ucenter/order_detail/id/'.$order_id);
				}
				else
				{
					$this->redirect('/site/success/message/'.urlencode("订单确认成功，等待发货"));
				}
			}
			else
			{
				IError::show(403,'订单修改失败');
			}
		}
		else
		{
			$this->setRenderData($dataArray);
			$this->redirect('cart3');
		}
    }

    //到货通知处理动作
	function arrival_notice()
	{
		$user_id  = IFilter::act(ISafe::get('user_id'),'int');
		$email    = IFilter::act(IReq::get('email'));
		$mobile   = IFilter::act(IReq::get('mobile'));
		$goods_id = IFilter::act(IReq::get('goods_id'),'int');
		$register_time = date('Y-m-d H:i:s');

		if(!$goods_id)
		{
			IError::show(403,'商品ID不存在');
		}

		$model = new IModel('notify_registry');
		$obj = $model->getObj("email = '{$email}' and user_id = '{$user_id}' and goods_id = '$goods_id'");
		if(empty($obj))
		{
			$model->setData(array('email'=>$email,'user_id'=>$user_id,'mobile'=>$mobile,'goods_id'=>$goods_id,'register_time'=>$register_time));
			$model->add();
		}
		else
		{
			$model->setData(array('email'=>$email,'user_id'=>$user_id,'mobile'=>$mobile,'goods_id'=>$goods_id,'register_time'=>$register_time,'notify_status'=>0));
			$model->update('id = '.$obj['id']);
		}
		$this->redirect('arrival_result');
	}

	/**
	 * @brief 邮箱找回密码进行
	 */
    function find_password_email()
	{
		$username = IReq::get('username');
		if($username === null || !Util::is_username($username)  )
		{
			IError::show(403,"请输入正确的用户名");
		}

		$email = IReq::get("email");
		if($email === null || !IValidate::email($email ))
		{
			IError::show(403,"请输入正确的邮箱地址");
		}

		$tb_user  = new IModel("user");
		$username = IFilter::act($username);
		$email    = IFilter::act($email);
		$user     = $tb_user->getObj(" username='{$username}' AND email='{$email}' ");
		if(!$user)
		{
			IError::show(403,"对不起，用户不存在");
		}
		$hash = IHash::md5( microtime(true) .mt_rand());

		//重新找回密码的数据
		$tb_find_password = new IModel("find_password");
		$tb_find_password->setData( array( 'hash' => $hash ,'user_id' => $user['id'] , 'addtime' => time() ) );

		if($tb_find_password->query("`hash` = '{$hash}'") || $tb_find_password->add())
		{
			$url     = IUrl::getHost().IUrl::creatUrl("/simple/restore_password/hash/{$hash}");
			$content = mailTemplate::findPassword(array("{url}" => $url));

			$smtp   = new SendMail();
			$result = $smtp->send($user['email'],"您的密码找回",$content);

			if($result===false)
			{
				IError::show(403,"发信失败,请重试！或者联系管理员查看邮件服务是否开启");
			}
		}
		else
		{
			IError::show(403,"生成HASH重复，请重试");
		}
		$message = "恭喜您，密码重置邮件已经发送！请到您的邮箱中去激活";
		$this->redirect("/site/success/message/".urlencode($message));
	}

	//手机短信找回密码
	function find_password_mobile()
	{
		$username = IReq::get('username');
		if($username === null || !Util::is_username($username)  )
		{
			IError::show(403,"请输入正确的用户名");
		}

		$mobile = IReq::get("mobile");
		if($mobile === null || !IValidate::mobi($mobile))
		{
			IError::show(403,"请输入正确的电话号码");
		}

		$mobile_code = IReq::get('mobile_code');
		if($mobile_code === null)
		{
			IError::show(403,"请输入短信校验码");
		}

		$userDB = new IModel('user as u , member as m');
		$userRow = $userDB->getObj('u.username = "'.$username.'" and m.mobile = "'.$mobile.'" and u.id = m.user_id');
		if($userRow)
		{
			$findPasswordDB = new IModel('find_password');
			$dataRow = $findPasswordDB->getObj('user_id = '.$userRow['user_id'].' and hash = "'.$mobile_code.'"');
			if($dataRow)
			{
				//短信验证码已经过期
				if(time() - $dataRow['addtime'] > 3600)
				{
					$findPasswordDB->del("user_id = ".$userRow['user_id']);
					IError::show(403,"您的短信校验码已经过期了，请重新找回密码");
				}
				else
				{
					$this->redirect('/simple/restore_password/hash/'.$mobile_code);
				}
			}
			else
			{
				IError::show(403,"您输入的短信校验码错误");
			}
		}
		else
		{
			IError::show(403,"用户名与手机号码不匹配");
		}
	}

	//发送手机验证码短信
	function send_message_mobile()
	{
		$username = IFilter::act(IReq::get('username'));
		$mobile = IFilter::act(IReq::get('mobile'));

		if($username === null || !Util::is_username($username))
		{
			die("请输入正确的用户名");
		}

		if($mobile === null || !IValidate::mobi($mobile))
		{
			die("请输入正确的手机号码");
		}

		$userDB = new IModel('user as u , member as m');
		$userRow = $userDB->getObj('u.username = "'.$username.'" and m.mobile = "'.$mobile.'" and u.id = m.user_id');

		if($userRow)
		{
			$findPasswordDB = new IModel('find_password');
			$dataRow = $findPasswordDB->query('user_id = '.$userRow['user_id'],'*','addtime','desc');
			$dataRow = current($dataRow);

			//120秒是短信发送的间隔
			if( isset($dataRow['addtime']) && (time() - $dataRow['addtime'] <= 120) )
			{
				die("申请验证码的时间间隔过短，请稍候再试");
			}
			$mobile_code = rand(10000,99999);
			$findPasswordDB->setData(array(
				'user_id' => $userRow['user_id'],
				'hash'    => $mobile_code,
				'addtime' => time(),
			));
			if($findPasswordDB->add())
			{
				$content = smsTemplate::findPassword(array('{mobile_code}' => $mobile_code));
				$result = Hsms::send($mobile,$content);
				if($result == 'success')
				{
					die('success');
				}
				die('短信发送失败');
			}
		}
		else
		{
			die('手机号码与用户名不符合');
		}
	}

	/**
	 * @brief 邮箱链接激活验证
	 */
	function restore_password()
	{
		$hash = IFilter::act(IReq::get("hash"));
		if(!$hash)
		{
			IError::show(403,"找不到校验码");
		}
		$tb = new IModel("find_password");
		$addtime = time() - 3600*72;
		$where  = " `hash`='$hash' AND addtime > $addtime ";
		$where .= $this->user['user_id'] ? " and user_id = ".$this->user['user_id'] : "";

		$row = $tb->getObj($where);
		if(!$row)
		{
			IError::show(403,"校验码已经超时");
		}

		$this->formAction = IUrl::creatUrl("/simple/do_restore_password/hash/$hash");
		$this->redirect("restore_password");
	}

	/**
	 * @brief 执行密码修改重置操作
	 */
	function do_restore_password()
	{
		$hash = IFilter::act(IReq::get("hash"));
		if(!$hash)
		{
			IError::show(403,"找不到校验码");
		}
		$tb = new IModel("find_password");
		$addtime = time() - 3600*72;
		$where  = " `hash`='$hash' AND addtime > $addtime ";
		$where .= $this->user['user_id'] ? " and user_id = ".$this->user['user_id'] : "";

		$row = $tb->getObj($where);
		if(!$row)
		{
			IError::show(403,"校验码已经超时");
		}

		//开始修改密码
		$pwd   = IReq::get("password");
		$repwd = IReq::get("repassword");
		if($pwd == null || strlen($pwd) < 6 || $repwd!=$pwd)
		{
			IError::show(403,"新密码至少六位，且两次输入的密码应该一致。");
		}
		$pwd = md5($pwd);
		$tb_user = new IModel("user");
		$tb_user->setData(array("password" => $pwd));
		$re = $tb_user->update("id='{$row['user_id']}'");
		if($re !== false)
		{
			$message = "修改密码成功";
			$tb->del("`hash`='{$hash}'");
			$this->redirect("/site/success/message/".urlencode($message));
			exit;
		}
		IError::show(403,"密码修改失败，请重试");
	}

    //添加收藏夹
    function favorite_add()
    {
    	$goods_id = intval(IReq::get('goods_id'));
    	$message  = '';

    	if($goods_id == 0)
    	{
    		$message = '商品id值不能为空';
    	}
    	else if(!isset($this->user['user_id']) || !$this->user['user_id'])
    	{
    		$message = '请先登录';
    	}
    	else
    	{
    		$favoriteObj = new IModel('favorite');
    		$goodsRow    = $favoriteObj->getObj('user_id = '.$this->user['user_id'].' and rid = '.$goods_id);
    		if($goodsRow)
    		{
    			$message = '您已经收藏过此件商品';
    		}
    		else
    		{
    			$catObj = new IModel('category_extend');
    			$catRow = $catObj->getObj('goods_id = '.$goods_id);
    			$cat_id = $catRow ? $catRow['category_id'] : 0;

	    		$dataArray   = array(
	    			'user_id' => $this->user['user_id'],
	    			'rid'     => $goods_id,
	    			'time'    => ITime::getDateTime(),
	    			'cat_id'  => $cat_id,
	    		);
	    		$favoriteObj->setData($dataArray);
	    		$favoriteObj->add();
    		}
    	}

    	if($message == '')
    	{
    		$result = array(
    			'isError' => false,
    			'message' => '收藏成功',
    		);
    	}
    	else
    	{
    		$result = array(
    			'isError' => true,
    			'message' => $message,
    		);
    	}

    	echo JSON::encode($result);
    }

    //获取oauth登录地址
    public function oauth_login()
    {
    	$id       = IFilter::act(IReq::get('id'),'int');
    	$callback = IFilter::act(IReq::get('callback'),'text');

    	//记录回调地址
    	ISafe::set('callback',$callback);

    	if($id)
    	{
    		$oauthObj = new Oauth($id);
			$result   = array(
				'isError' => false,
				'url'     => $oauthObj->getLoginUrl(),
			);
    		ISession::set('oauth',$id);
    	}
    	else
    	{
			$result   = array(
				'isError' => true,
				'message' => '请选择要登录的平台',
			);
    	}
    	echo JSON::encode($result);
    }

    //获取令牌
    public function oauth_callback()
    {
    	$id = intval(ISession::get('oauth'));
    	if(!$id)
    	{
    		$this->redirect('login');
    		exit;
    	}
    	$oauthObj = new Oauth($id);
    	$result   = $oauthObj->checkStatus($_GET);

    	if($result === true)
    	{
    		$oauthObj->getAccessToken($_GET);
	    	$userInfo = $oauthObj->getUserInfo();

	    	if(isset($userInfo['id']) && isset($userInfo['name']) && $userInfo['id'] != '' &&  $userInfo['name'] != '')
	    	{
	    		$this->bindUser($userInfo,$id);
	    	}
	    	else
	    	{
	    		$this->redirect('login');
	    	}
    	}
    	else
    	{
    		$this->redirect('login');
    	}
    }

    //同步绑定用户数据
    public function bindUser($userInfo,$oauthId)
    {
    	$oauthUserObj = new IModel('oauth_user');
    	$oauthUserRow = $oauthUserObj->getObj("oauth_user_id = '{$userInfo['id']}' and oauth_id = '{$oauthId}' ",'user_id');

    	//没有绑定账号
    	if(empty($oauthUserRow))
    	{
	    	$userObj   = new IModel('user');
	    	$userCount = $userObj->getObj("username = '{$userInfo['name']}'",'count(*) as num');

	    	//没有重复的用户名
	    	if($userCount['num'] == 0)
	    	{
	    		$username = $userInfo['name'];
	    	}
	    	else
	    	{
	    		//随即分配一个用户名
	    		$username = $userInfo['name'].$userCount['num'];
	    	}

	    	ISafe::set('oauth_username',$username);
	    	ISession::set('oauth_id',$oauthId);
	    	ISession::set('oauth_userInfo',$userInfo);

	    	$this->redirect('bind_user');
    	}

    	//存在绑定账号
    	else
    	{
    		$userObj = new IModel('user');
    		$tempRow = $userObj->getObj("id = '{$oauthUserRow['user_id']}'");
    		$userRow = CheckRights::isValidUser($tempRow['email'],$tempRow['password']);
    		$this->loginAfter($userRow);

			//自定义跳转页面
			$callback = ISafe::get('callback');

			if($callback && !strpos($callback,'reg') && !strpos($callback,'login'))
			{
				$this->redirect($callback);
			}
			else
			{
				$this->redirect('/ucenter/index');
			}
    	}
    }

	//绑定已存在用户
    public function bind_exists_user()
    {
    	$login_info     = IReq::get('login_info');
    	$password       = IReq::get('password');
    	$oauth_id       = IFilter::act(ISession::get('oauth_id'));
    	$oauth_userInfo = IFilter::act(ISession::get('oauth_userInfo'));

    	if(!$oauth_id || !isset($oauth_userInfo['id']))
    	{
    		$this->redirect('login');
    		exit;
    	}

    	if($userRow = CheckRights::isValidUser($login_info,md5($password)))
    	{
    		$oauthUserObj = new IModel('oauth_user');

    		//插入关系表
    		$oauthUserData = array(
    			'oauth_user_id' => $oauth_userInfo['id'],
    			'oauth_id'      => $oauth_id,
    			'user_id'       => $userRow['user_id'],
    			'datetime'      => ITime::getDateTime(),
    		);
    		$oauthUserObj->setData($oauthUserData);
    		$oauthUserObj->add();

    		$this->loginAfter($userRow);

			//自定义跳转页面
			$callback = ISafe::get('callback');
			$this->redirect('/site/success?message='.urlencode("登录成功！").'&callback='.$callback);
    	}
    	else
    	{
    		$this->login_info = $login_info;
    		$this->message    = '用户名和密码不匹配';
    		$_GET['bind_type']= 'exists';
    		$this->redirect('bind_user',false);
    	}
    }

	//绑定不存在用户
    public function bind_nexists_user()
    {
    	$username       = IFilter::act(IReq::get('username'));
    	$email          = IFilter::act(IReq::get('email'));
    	$oauth_id       = IFilter::act(ISession::get('oauth_id'));
    	$oauth_userInfo = IFilter::act(ISession::get('oauth_userInfo'));

		/*注册信息校验*/
    	if(IValidate::email($email) == false)
    	{
    		$message = '邮箱格式不正确';
    	}
    	else if(!Util::is_username($username))
    	{
    		$message = '用户名必须是由2-20个字符，可以为字数，数字下划线和中文';
    	}
    	else
    	{
    		$userObj = new IModel('user');
    		$where   = 'email = "'.$email.'" or username = "'.$email.'" or username = "'.$username.'"';
    		$userRow = $userObj->getObj($where);

    		if(!empty($userRow))
    		{
    			if($email == $userRow['email'])
    			{
    				$message = '此邮箱已经被注册过，请重新更换';
    			}
    			else
    			{
    				$message = "此用户名已经被注册过，请重新更换";
    			}
    		}
    		else
    		{
				$userData = array(
					'email'    => $email,
					'username' => $username,
					'password' => md5(ITime::getDateTime()),
				);
				$userObj->setData($userData);
				$user_id = $userObj->add();

				$memberObj  = new IModel('member');
				$memberData = array(
					'user_id'   => $user_id,
					'true_name' => $oauth_userInfo['name'],
					'last_login'=> ITime::getDateTime(),
					'sex'       => isset($oauth_userInfo['sex']) ? $oauth_userInfo['sex'] : 1,
					'time'      => ITime::getDateTime(),
				);
				$memberObj->setData($memberData);
				$memberObj->add();

				$oauthUserObj = new IModel('oauth_user');

				//插入关系表
				$oauthUserData = array(
					'oauth_user_id' => $oauth_userInfo['id'],
					'oauth_id'      => $oauth_id,
					'user_id'       => $user_id,
					'datetime'      => ITime::getDateTime(),
				);
				$oauthUserObj->setData($oauthUserData);
				$oauthUserObj->add();

				$userRow = CheckRights::isValidUser($userData['email'],$userData['password']);
				$this->loginAfter($userRow);

				//自定义跳转页面
				$callback = ISafe::get('callback');
				$this->redirect('/site/success?message='.urlencode("注册成功！").'&callback='.$callback);
    		}
    	}

    	if($message != '')
    	{
    		$this->message = $message;
    		$this->redirect('bind_user',false);
    	}
    }

	/**
	 * @brief 商户的增加动作
	 */
	public function seller_reg()
	{
		$seller_name = IFilter::act(IReq::get('seller_name'));
		$email       = IFilter::act(IReq::get('email'));
		$password    = IFilter::act(IReq::get('password'));
		$repassword  = IFilter::act(IReq::get('repassword'));
		$truename    = IFilter::act(IReq::get('true_name'));
		$phone       = IFilter::act(IReq::get('phone'));
		$mobile      = IFilter::act(IReq::get('mobile'));
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$home_url    = IFilter::act(IReq::get('home_url'));

		if($password == '')
		{
			$errorMsg = '请输入密码！';
		}

		if($password != $repassword)
		{
			$errorMsg = '两次输入的密码不一致！';
		}

		//创建商户操作类
		$sellerDB = new IModel("seller");
		if($sellerDB->getObj("seller_name = '{$seller_name}'"))
		{
			$errorMsg = "登录用户名重复";
		}
		else if($sellerDB->getObj("true_name = '{$truename}'"))
		{
			$errorMsg = "商户真实全程重复";
		}

		//操作失败表单回填
		if(isset($errorMsg))
		{
			$this->sellerRow = $_POST;
			$this->redirect('seller',false);
			Util::showMessage($errorMsg);
		}

		//待更新的数据
		$sellerRow = array(
			'true_name' => $truename,
			'phone'     => $phone,
			'mobile'    => $mobile,
			'email'     => $email,
			'address'   => $address,
			'province'  => $province,
			'city'      => $city,
			'area'      => $area,
			'home_url'  => $home_url,
			'is_lock'   => 1,
		);

		//商户资质上传
		if(isset($_FILES['paper_img']['name']) && $_FILES['paper_img']['name'])
		{
			$uploadObj = new PhotoUpload();
			$uploadObj->setIterance(false);
			$photoInfo = $uploadObj->run();
			if(isset($photoInfo['paper_img']['img']) && file_exists($photoInfo['paper_img']['img']))
			{
				$sellerRow['paper_img'] = $photoInfo['paper_img']['img'];
			}
		}

		$sellerRow['seller_name'] = $seller_name;
		$sellerRow['password']    = md5($password);
		$sellerRow['create_time'] = ITime::getDateTime();

		$sellerDB->setData($sellerRow);
		$sellerDB->add();

		//短信通知商城平台
		$siteConfig = new Config('site_config');
		if($siteConfig->mobile)
		{
			$content = smsTemplate::sellerReg(array('{true_name}' => $truename));
			$result = Hsms::send($mobile,$content);
		}

		$this->redirect('/site/success?message='.urlencode("申请成功！请耐心等待管理员的审核"));
	}

	/**
	 * @brief 发送验证邮箱邮件
	 */
	public function send_check_mail()
	{
		$email = IReq::get('email');
		if(IValidate::email($email) == false)
		{
			IError::show(403,'邮件格式错误');
		}

		$userDB  = new IModel('user');
		$userRow = $userDB->getObj('email = "'.$email.'"');
		$code    = base64_encode($userRow['email']."|".$userRow['id']);
		$url     = IUrl::getHost().IUrl::creatUrl("/simple/check_mail/code/{$code}");
		$content = mailTemplate::checkMail(array("{url}" => $url));

		//发送邮件
		$smtp   = new SendMail();
		$result = $smtp->send($email,"用户注册邮箱验证",$content);
		if($result===false)
		{
			IError::show(403,"发信失败,请重试！或者联系管理员查看邮件服务是否开启");
		}

		$message = "您的邮箱验证邮件已发送到{$email}！请到您的邮箱中去激活";
		$this->redirect('/site/success?message='.urlencode($message).'&email='.$email);
	}

	/**
	 * @brief 验证邮箱
	 */
	public function check_mail()
	{
		$code = IReq::get("code");
		list($email,$user_id) = explode('|',base64_decode($code));
		$email   = IFilter::act($email);
		$user_id = IFilter::act($user_id,'int');

		$userDB  = new IModel("user");
		$userRow = $userDB->getObj(" email = '{$email}' and id = ".$user_id);
		if($userRow)
		{
			$memberObj = new IModel("member");
			$memberObj->setData(array("status" => 1));
			$memberObj->update("user_id = ".$user_id);
			$message = "恭喜，您的邮箱激活成功！";
		}
		else
		{
			$message = "验证信息有误，请核实！";
		}
		$this->redirect('/site/success?message='.urlencode($message));
	}

	//添加地址ajax
	function address_add()
	{
		$accept_name = IFilter::act(IReq::get('accept_name'));
		$province    = IFilter::act(IReq::get('province'),'int');
		$city        = IFilter::act(IReq::get('city'),'int');
		$area        = IFilter::act(IReq::get('area'),'int');
		$address     = IFilter::act(IReq::get('address'));
		$zip         = IFilter::act(IReq::get('zip'));
		$telphone    = IFilter::act(IReq::get('telphone'));
		$mobile      = IFilter::act(IReq::get('mobile'));
        $user_id     = $this->user['user_id'];

        if(!$user_id)
        {
        	die(JSON::encode(array('data' => null)));
        }

		//整合的数据，检查数据库中是否存在此收货地址
        $sqlData = array(
        	'user_id'     => $user_id,
        	'accept_name' => $accept_name,
        	'zip'         => $zip,
        	'telphone'    => $telphone,
        	'province'    => $province,
        	'city'        => $city,
        	'area'        => $area,
        	'address'     => $address,
        	'mobile'      => $mobile,
        );
        $sqlArray = array();
        foreach($sqlData as $key => $val)
        {
        	$sqlArray[] = $key.'="'.$val.'"';
        }

        $model       = new IModel('address');
		$addressRow  = $model->getObj(join(' and ',$sqlArray));

		if($addressRow)
		{
			$result = array('data' => null);
		}
		else
		{
			//获取地区text
			$areaList = area::name($province,$city,$area);

			//执行insert
			$model->setData($sqlData);
			$model->add();

			$sqlData['province_val'] = $areaList[$province];
			$sqlData['city_val']     = $areaList[$city];
			$sqlData['area_val']     = $areaList[$area];

			$result = array('data' => $sqlData);
		}
		die(JSON::encode($result));
	}
}
