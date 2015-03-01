<?php
/**
 * @brief 检索商品类
 * @date 2014/12/1 18:34:35
 * @author Jason
 */
class search_goods
{
	//商品检索的属性过滤 array(key => array(id,name,value))
	public static $attrSearch = array();

	//商品检索的品牌过滤 array(key => array(id,name))
	public static $brandSearch = array();

	//商品检索的价格过滤
	public static $priceSearch = array();

	//[条件检索url处理]对于query url中已经存在的数据进行删除;没有的参数进行添加
	public static function searchUrl($queryKey,$queryVal = '')
	{
		if(is_array($queryKey))
		{
			$concatStr = '';
			$fromStr   = array();
			$toStr     = array();

			foreach($queryKey as $k => $v)
			{
				$urlVal  = IReq::get($v);
				$tempVal = isset($queryVal[$k]) ? $queryVal[$k] : $queryVal;

				if($urlVal === null)
				{
					$concatStr.='&'.$v.'='.$tempVal;
				}
				else
				{
					$fromStr[] = '&'.$v.'='.$urlVal;
					$toStr[]   = '&'.$v.'='.$tempVal;
				}
			}
			return IFilter::clearUrl(str_replace($fromStr,$toStr,'?'.urldecode($_SERVER['QUERY_STRING'])).$concatStr);
		}
		else
		{
			/*URL变量 arg[key] 格式支持
			 *由于在 URL get方式传参时系统会把变量 arg[key] 直接判定为数组
			 *所以这里需要对此类参数进行特殊处理;
			 */
			preg_match('|(\w+)\[(\d+)\]|',$queryKey,$match);
			$urlVal = null;

			if(isset($match[2]))
			{
				//获取在url中已存储数据
				$urlArray = IReq::get($match[1]);
				if(isset($urlArray[$match[2]]))
				{
					$urlVal = $urlArray[$match[2]];
				}
			}
			//考虑列表排序按钮的效果
			else
			{
				$urlVal = IReq::get($queryKey);
			}

			//如果此项url中没有$urlVal 并且 赋值还存在，则直接追加到url中即可
			if($urlVal === null && $queryVal !== '')
			{
				return IFilter::clearUrl('?'.$_SERVER['QUERY_STRING'].'&'.$queryKey.'='.$queryVal);
			}
			else
			{
				$fromStr[] = '&'.$queryKey.'='.$urlVal;

				if($queryVal === '')
				{
					$toStr = '';
				}
				else
				{
					$toStr[] = '&'.$queryKey.'='.$queryVal;
				}
				return IFilter::clearUrl(str_replace($fromStr,$toStr,'?'.urldecode($_SERVER['QUERY_STRING'])));
			}
		}
	}

	/**
	 * @brief 获取列表页面排序
	 * @return string
	 */
	public static function getListOrder()
	{
		$order = IFilter::act(IReq::get('order'),'url');
		if(!$order)
		{
			//获取配置信息
			$siteConfigObj = new Config("site_config");
			return $siteConfigObj->order_type == 'asc' ? $siteConfigObj->order_by.'_toggle' : $siteConfigObj->order_by;
		}
		return $order;
	}

	/**
	 * @brief 获取列表展示
	 * @param $showType string 展示方式
	 * @return string 展示方式
	 */
	public static function getListShow($listType)
	{
		if(!$listType)
		{
			//获取配置信息
			$siteConfigObj = new Config("site_config");
			return $siteConfigObj->list_type;
		}
		return $listType;
	}

	/**
	 * @brief 获取列表尺寸
	 * @param $listType 展示方案
	 * @return array('width' => '宽度','height' => '高度')
	 */
	public static function getListSize($listType)
	{
		$listType = self::getListShow($listType);
		switch($listType)
		{
			case "win":
			{
				return array('width' => 200,'height' => 200);
			}
			break;

			case "list":
			{
				return array('width' => 115,'height' => 115);
			}
			break;
		}
	}

	/**
	 * @brief 获取总的排序方式
	 * @return array(代号 => 名字)
	 */
	public static function getOrderType()
	{
		return array('sale' =>'销量','cpoint' =>'评分','price'=>'价格','new'=>'最新上架');
	}

	/**
	 * @brief 判断当前排序方式
	 * @param $order string 排序方式代码
	 * @return boolean
	 */
	public static function isOrderCurrent($order)
	{
		$currentOrder = self::getListOrder();
		if(stripos($currentOrder,$order) !== false)
		{
			return true;
		}
		return false;
	}

	/**
	 * @brief 排序是否为倒序
	 * @return boolean
	 */
	public static function isOrderDesc()
	{
		$currentOrder = self::getListOrder();
		if(stripos($currentOrder,'_toggle') !== false)
		{
			return false;
		}
		return true;
	}

	/**
	 * @brief 获取排序值
	 * @param $order string 排序方式代码
	 * @return string
	 */
	public static function getOrderValue($order)
	{
		$currentOrder = IFilter::act(IReq::get('order'));
		return $currentOrder == $order ? $order.'_toggle' : $order;
	}

	/**
	 * @brief 商品检索,可以直接读取 $_GET 全局变量:attr,order,brand,min_price,max_price
	 *        在检索商品过程中计算商品结果中的进一步属性和规格的筛选
	 * @param mixed $defaultWhere string(条件) or array('search' => '模糊查找','category_extend' => '商品分类ID','字段' => 对应数据)
	 * @param int $limit 读取数量
	 * @param bool $isCondition 是否筛选出商品的属性，价格等数据
	 * @return IQuery
	 */
	public static function find($defaultWhere = '',$limit = 21,$isCondition = true)
	{
		//获取配置信息
		$siteConfigObj = new Config("site_config");
		$site_config   = $siteConfigObj->getInfo();

		//开始查询
		$goodsObj = new IQuery("goods as go");
		$goodsObj->page     = isset($_GET['page']) ? intval($_GET['page']) : 1;
		$goodsObj->fields   = ' go.* ';
		$goodsObj->pagesize = $limit;

		/*where条件拼接*/
		//(1),当前产品分类
		$where = ' go.is_del = 0 ';

		//(2),商品属性,规格筛选
		$attrCond  = array();
		$childSql  = '';
		$attrArray = IReq::get('attr') ? IFilter::act(IReq::get('attr')) : array();

		foreach($attrArray as $key => $val)
		{
			if($key && $val)
			{
				$attrCond[] = ' attribute_id = '.intval($key).' and FIND_IN_SET("'.$val.'",attribute_value)';
			}
		}

		//合并规格与属性的值,并且生成SQL查询语句
		$GoodsId = null;
		if($attrCond)
		{
			$tempArray = array();
			foreach($attrCond as $key => $cond)
			{
				$tempArray[] =  '('.$cond.')';
			}
			$childSql = join(' or ',$tempArray);

			$goodsAttrObj          = new IQuery('goods_attribute');
			$goodsAttrObj->fields  = 'goods_id';
			$goodsAttrObj->where   = $childSql;
			$goodsAttrObj->group   = 'goods_id';
			$goodsAttrObj->having  = 'count(goods_id) >= '.count($attrCond); //每个子条件都有一条记录，则存在几个count(条件)必须包含count(goods_id)条数量
			$goodsIdArray          = $goodsAttrObj->find();

			$goodsIds = array();
			foreach($goodsIdArray as $key => $val)
			{
				$goodsIds[] = $val['goods_id'];
			}

			$GoodsId = $GoodsId === null ? $goodsIds : array_intersect($goodsIds,$GoodsId);
		}

		//(3),处理defaultWhere条件 goods, category_extend
		if($defaultWhere)
		{
			//兼容array 和 string 数据类型的goods条件筛选
			$goodsCondArray = array();
			if(is_string($defaultWhere))
			{
				$goodsCondArray[] = $defaultWhere;
			}
			else if(is_array($defaultWhere))
			{
				foreach($defaultWhere as $key => $val)
				{
					if(!$val)
					{
						continue;
					}

					//商品分类检索
					if($key == 'category_extend')
					{
						$currentCatGoods    = array();
						$categoryExtendObj  = new IModel('category_extend');
						$categoryExtendList = $categoryExtendObj->query("category_id in (".$val.")",'goods_id');
						foreach($categoryExtendList as $key => $val)
						{
							$currentCatGoods[] = $val['goods_id'];
						}
						$GoodsId = $GoodsId === null ? $currentCatGoods : array_intersect($currentCatGoods,$GoodsId);
					}
					//搜索词模糊
					else if($key == 'search')
					{
						$wordWhere = array(' name like "%'.$defaultWhere['search'].'%" ');
						$wordData = words_facade::run($defaultWhere['search']);
						if(isset($wordData['data']) && count($wordData['data']) >= 2)
						{
							foreach($wordData['data'] as $word)
							{
								$wordWhere[] = ' name like "%'.$word.'%" ';
							}
						}
						$goodsCondArray[] = join(' or ',$wordWhere);
					}
					//其他条件
					else
					{
						$goodsCondArray[] = $key.' = "'.$val.'"';
					}
				}
			}

			//goods 条件
			if($goodsCondArray)
			{
				$goodsDB = new IModel('goods as go');
				$goodsCondData = $goodsDB->query(join(" and ",$goodsCondArray),"id");

				$goodsCondId = array();
				foreach($goodsCondData as $key => $val)
				{
					$goodsCondId[] = $val['id'];
				}
				$GoodsId = $GoodsId === null ? $goodsCondId : array_intersect($goodsCondId,$GoodsId);
			}
		}
		$GoodsId = $GoodsId === array() ? array(0) : $GoodsId;

		//存在商品ID数据
		if($GoodsId)
		{
			$where .= " and go.id in (".join(',',$GoodsId).") ";

			//商品属性进行检索
			if($isCondition == true)
			{
				/******属性 开始******/
				$attrTemp = array();
				$goodsAttrDB = new IModel('goods_attribute');
				$attrData    = $goodsAttrDB->query("goods_id in (".join(',',$GoodsId).")");
				foreach($attrData as $key => $val)
				{
					//属性
					if($val['attribute_id'])
					{
						if(!isset($attrTemp[$val['attribute_id']]))
						{
							$attrTemp[$val['attribute_id']] = array();
						}

						$checkSelectedArray = explode(",",$val['attribute_value']);
						foreach($checkSelectedArray as $k => $v)
						{
							if(!in_array($v,$attrTemp[$val['attribute_id']]))
							{
								$attrTemp[$val['attribute_id']][] = $v;
							}
						}
					}
				}

				//属性的数据拼接
				if($attrTemp)
				{
					$attrDB   = new IModel('attribute');
					$attrData = $attrDB->query("id in (".join(',',array_keys($attrTemp)).") and search = 1","*","id","asc",8);
					foreach($attrData as $key => $val)
					{
						self::$attrSearch[] = array('id' => $val['id'],'name' => $val['name'],'value' => $attrTemp[$val['id']]);
					}
				}
				/******属性 结束******/

				/******品牌 开始******/
				$brandQuery = new IModel('brand as b,goods as go');
				self::$brandSearch = $brandQuery->query("go.brand_id = b.id and go.id in (".join(',',$GoodsId).")","distinct b.id,b.name","b.sort","asc",10);
				/******品牌 结束******/

				/******价格 开始******/
				self::$priceSearch = goods_class::getGoodsPrice(join(',',$GoodsId));
				/******价格 结束******/
			}
		}

		//(4),商品价格
		$where.= floatval(IReq::get('min_price')) ? ' and go.sell_price >= '.floatval(IReq::get('min_price')) : '';
		$where.= floatval(IReq::get('max_price')) ? ' and go.sell_price <= '.floatval(IReq::get('max_price')) : '';

		//(5),商品品牌
		$where.= intval(IReq::get('brand')) ? ' and go.brand_id = '.intval(IReq::get('brand')) : '';

		//排序类别
		$order = IFilter::act(IReq::get('order'),'url');
		if($order == null)
		{
			$order = isset($site_config['order_by'])   ? $site_config['order_by']  :'new';
			$asc   = isset($site_config['order_type']) ? $site_config['order_type']:'desc';
		}
		else
		{
			if(stripos($order,'_toggle'))
			{
				$order = str_replace('_toggle','',$order);
				$asc   = 'asc';
			}
			else
			{
				$asc   = 'desc';
			}
		}

		switch($order)
		{
			//销售量
			case "sale":
			{
				$goodsObj->order = ' go.sale '.$asc;
			}
			break;

			//评分
			case "cpoint":
			{
				$goodsObj->order = ' go.grade '.$asc;
			}
			break;

			//最新上架
			case "new":
			{
				$goodsObj->order = ' go.id '.$asc;
			}
			break;

			//价格
			case "price":
			{
				$goodsObj->order = ' go.sell_price '.$asc;
			}
			break;

			//根据排序字段
			default:
			{
				$goodsObj->order = 'go.sort asc';
			}
		}

		//设置IQuery类的各个属性
		$goodsObj->where = $where;
		return $goodsObj;
	}
}