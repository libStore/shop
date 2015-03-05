<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file crowd_class.php
 * @brief 众筹管理类库
 * @author Jason
 * @date 2014/8/18 11:53:43
 * @version 2.6
 */
class crowd_class
{
	//算账类库
	private static $countsumInstance = null;

	//商户ID
	public $seller_id = '';

	//构造函数
	public function __construct($seller_id = '')
	{
		$this->seller_id = $seller_id;
	}

	/**
	 * 获取众筹价格
	 * @param int $crowd_id 众筹ID
	 * @param float $sell_price 众筹销售价
	 */
	public static function price($crowd_id,$sell_price)
	{
		if(self::$countsumInstance == null)
		{
			self::$countsumInstance = new CountSum();
		}
		$price = self::$countsumInstance->getGroupPrice($crowd_id);
		return $price ? $price : $sell_price;
	}

	/**
	 * 生成众筹货号
	 * @return string 货号
	 */
	public static function createCrowdNo()
	{
		$config = new Config('site_config');
		return $config->crowd_no_pre.time().rand(10,99);
	}

	/**
	 * @brief 修改众筹数据
	 * @param int $id 众筹ID
	 * @param array $paramData 众筹所需数据
	 */
	public function update($id,$paramData)
	{
		$postData = array();
		$nowDataTime = ITime::getDateTime();

		foreach($paramData as $key => $val)
		{
			$postData[$key] = $val;

			//数据过滤分组
			if(strpos($key,'attr_id_') !== false)
			{
				$crowdAttrData[ltrim($key,'attr_id_')] = IFilter::act($val);
			}
			else if($key == 'content')
			{
				$crowdUpdateData['content'] = IFilter::addSlash($val);
			}
			else if($key[0] != '_')
			{
				$crowdUpdateData[$key] = IFilter::act($val,'text');
			}
		}

		//商户发布众筹默认设置
		if($this->seller_id)
		{
			$crowdUpdateData['seller_id'] = $this->seller_id;
			$crowdUpdateData['is_del'] = $crowdUpdateData['is_del'] == 2 ? 2 : 3;

			//如果商户是VIP则无需审核众筹
			if($crowdUpdateData['is_del'] == 3)
			{
				$sellerDB = new IModel('seller');
				$sellerRow= $sellerDB->getObj('id = '.$this->seller_id);
				if($sellerRow['is_vip'] == 1)
				{
					$crowdUpdateData['is_del'] = 0;
				}
			}
		}

		//上架或者下架处理
		if(isset($crowdUpdateData['is_del']))
		{
			//上架
			if($crowdUpdateData['is_del'] == 0)
			{
				$crowdUpdateData['up_time']   = $nowDataTime;
				$crowdUpdateData['down_time'] = null;
			}
			//下架
			else if($crowdUpdateData['is_del'] == 2)
			{
				$crowdUpdateData['up_time']  = null;
				$crowdUpdateData['down_time']= $nowDataTime;
			}
			//审核或者删除
			else
			{
				$crowdUpdateData['up_time']   = null;
				$crowdUpdateData['down_time'] = null;
			}
		}

		//是否存在货品
		$crowdUpdateData['spec_array'] = '';
		if(isset($postData['_spec_array']))
		{
			//生成crowd中的spec_array字段数据
			$crowd_spec_array = array();
			foreach($postData['_spec_array'] as $key => $val)
			{
				foreach($val as $v)
				{
					$tempSpec = JSON::decode($v);
					if(!isset($crowd_spec_array[$tempSpec['id']]))
					{
						$crowd_spec_array[$tempSpec['id']] = array('id' => $tempSpec['id'],'name' => $tempSpec['name'],'type' => $tempSpec['type'],'value' => array());
					}
					$crowd_spec_array[$tempSpec['id']]['value'][] = $tempSpec['value'];
				}
			}
			foreach($crowd_spec_array as $key => $val)
			{
				$val['value'] = array_unique($val['value']);
				$crowd_spec_array[$key]['value'] = join(',',$val['value']);
			}
			$crowdUpdateData['spec_array'] = JSON::encode($crowd_spec_array);
		}

		$crowdUpdateData['crowd_no']     = isset($postData['_crowd_no'])     ? current($postData['_crowd_no'])     : '';
		$crowdUpdateData['store_nums']   = array_sum($postData['_store_nums']);

		$crowdUpdateData['amount_total'] = $postData['amount_total'];
		$crowdUpdateData['amount_self']  = $postData['amount_self'];
		$crowdUpdateData['amount_loan']  = $postData['amount_loan'];
		$crowdUpdateData['amount_mini']  = $postData['amount_mini'];
		$crowdUpdateData['expire_days']  = $postData['expire_days'];

		$crowdUpdateData['update_time'] = $nowDataTime;

		//处理众筹
		$crowdDB = new IModel('crowd');
		if($id)
		{
			$crowdDB->setData($crowdUpdateData);

			$where = " id = {$id} ";
			if($this->seller_id)
			{
				$where .= " and seller_id = ".$this->seller_id;
			}

			if($crowdDB->update($where) === false)
			{
				die("更新众筹错误");
			}
		}
		else
		{
			$crowdUpdateData['create_time'] = $nowDataTime;
			$crowdDB->setData($crowdUpdateData);
			$id = $crowdDB->add();
		}

		//处理众筹属性
		$crowdAttrDB = new IModel('crowd_attribute');
		$crowdAttrDB->del('crowd_id = '.$id);
		if(isset($crowdAttrData) && $crowdAttrData)
		{
			foreach($crowdAttrData as $key => $val)
			{
				$attrData = array(
					'crowd_id' => $id,
					'model_id' => $crowdUpdateData['model_id'],
					'attribute_id' => $key,
					'attribute_value' => is_array($val) ? join(',',$val) : $val
				);
				$crowdAttrDB->setData($attrData);
				$crowdAttrDB->add();
			}
		}

		//是否存在货品
		$projectsDB = new IModel('projects');
		$projectsDB->del('crowd_id = '.$id);
		if(isset($postData['_spec_array']))
		{
			$projectIdArray = array();

			//创建货品信息
			foreach($postData['_crowd_no'] as $key => $rs)
			{
				$projectsData = array(
					'crowd_id' => $id,
					'project_no' => $postData['_crowd_no'][$key],
					'store_nums' => $postData['_store_nums'][$key],
					'market_price' => $postData['_market_price'][$key],
					'sell_price' => $postData['_sell_price'][$key],
					'cost_price' => $postData['_cost_price'][$key],
					'weight' => $postData['_weight'][$key],
					'spec_array' => "[".join(',',$postData['_spec_array'][$key])."]"
				);
				$projectsDB->setData($projectsData);
				$projectIdArray[$key] = $projectsDB->add();
			}
		}

		//处理众筹分类
		$categoryDB = new IModel('crowd_category_extend');
		$categoryDB->del('crowd_id = '.$id);
		if(isset($postData['_crowd_category']) && $postData['_crowd_category'])
		{
			foreach($postData['_crowd_category'] as $item)
			{
				$categoryDB->setData(array('crowd_id' => $id,'category_id' => $item));
				$categoryDB->add();
			}
		}

		//处理众筹促销
		$commendDB = new IModel('crowd_commend');
		$commendDB->del('crowd_id = '.$id);
		if(isset($postData['_crowd_commend']) && $postData['_crowd_commend'])
		{
			foreach($postData['_crowd_commend'] as $item)
			{
				$commendDB->setData(array('crowd_id' => $id,'commend_id' => $item));
				$commendDB->add();
			}
		}

		//处理众筹关键词
		keywords::add($crowdUpdateData['search_words']);

		//处理众筹图片
		$photoRelationDB = new IModel('crowd_photo_relation');
		$photoRelationDB->del('crowd_id = '.$id);
		if(isset($postData['_imgList']) && $postData['_imgList'])
		{
			$postData['_imgList'] = str_replace(',','","',trim($postData['_imgList'],','));
			$photoDB = new IModel('crowd_photo');
			$photoData = $photoDB->query('img in ("'.$postData['_imgList'].'")','id');
			if($photoData)
			{
				foreach($photoData as $item)
				{
					$photoRelationDB->setData(array('crowd_id' => $id,'photo_id' => $item['id']));
					$photoRelationDB->add();
				}
			}
		}

		//处理会员组的价格
		$groupPriceDB = new IModel('crowd_group_price');
		$groupPriceDB->del('crowd_id = '.$id);
		if(isset($projectIdArray) && $projectIdArray)
		{
			foreach($projectIdArray as $index => $value)
			{
				if(isset($postData['_groupPrice'][$index]) && $postData['_groupPrice'][$index])
				{
					$temp = JSON::decode($postData['_groupPrice'][$index]);
					foreach($temp as $k => $v)
					{
						$groupPriceDB->setData(array(
							'crowd_id' => $id,
							'project_id' => $value,
							'group_id' => $k,
							'price' => $v
						));
						$groupPriceDB->add();
					}
				}
			}
		}
		else
		{
			if(isset($postData['_groupPrice'][0]) && $postData['_groupPrice'][0])
			{
				$temp = JSON::decode($postData['_groupPrice'][0]);
				foreach($temp as $k => $v)
				{
					$groupPriceDB->setData(array(
						'crowd_id' => $id,
						'group_id' => $k,
						'price' => $v
					));
					$groupPriceDB->add();
				}
			}
		}
		return true;
	}

	/**
	* @brief 删除与众筹相关表中的数据
	*/
	public function del($crowd_id)
	{
		$crowdWhere = " id = {$crowd_id} ";
		if($this->seller_id)
		{
			$crowdWhere .= " and seller_id = ".$this->seller_id;
		}

		//删除众筹表
		$tb_crowd = new IModel('crowd');
		if(!$tb_crowd ->del($crowdWhere))
		{
			return;
		}
	}
	/**
	 * 获取编辑众筹所有数据
	 * @param int $id 众筹ID
	 */
	public function edit($id)
	{
		$crowdWhere = " id = {$id} ";
		if($this->seller_id)
		{
			$crowdWhere .= " and seller_id = ".$this->seller_id;
		}

		//获取众筹
		$obj_crowd = new IModel('crowd');
		$crowd_info = $obj_crowd->getObj($crowdWhere);

		if(!$crowd_info)
		{
			return null;
		}

		//获取众筹的会员价格
		$groupPriceDB = new IModel('crowd_group_price');
		$crowdPrice   = $groupPriceDB->query("crowd_id = ".$crowd_info['id']." and project_id is NULL ");
		$temp = array();
		foreach($crowdPrice as $key => $val)
		{
			$temp[$val['group_id']] = $val['price'];
		}
		$crowd_info['groupPrice'] = $temp ? JSON::encode($temp) : '';

		//赋值到FORM用于渲染
		$data = array('form' => $crowd_info);

		//获取货品
		$projectObj = new IModel('projects');
		$project_info = $projectObj->query('crowd_id = '.$id);
		if($project_info)
		{
			//获取货品会员价格
			foreach($project_info as $k => $rs)
			{
				$temp = array();
				$projectPrice = $groupPriceDB->query('project_id = '.$rs['id']);
				foreach($projectPrice as $key => $val)
				{
					$temp[$val['group_id']] = $val['price'];
				}
				$project_info[$k]['groupPrice'] = $temp ? JSON::encode($temp) : '';
			}
			$data['project'] = $project_info;
		}

		//加载推荐类型
		$tb_commend_crowd = new IModel('crowd_commend');
		$commend_crowd = $tb_commend_crowd->query('crowd_id='.$crowd_info['id'],'commend_id');
		if($commend_crowd)
		{
			foreach($commend_crowd as $value)
			{
				$data['crowd_commend'][] = $value['commend_id'];
			}
		}

		//相册
		$tb_crowd_photo = new IQuery('crowd_photo_relation as ghr');
		$tb_crowd_photo->join = 'left join crowd_photo as gh on ghr.photo_id=gh.id';
		$tb_crowd_photo->fields = 'gh.img';
		$tb_crowd_photo->where = 'ghr.crowd_id='.$crowd_info['id'];
		$tb_crowd_photo->order = 'ghr.id asc';
		$data['crowd_photo'] = $tb_crowd_photo->find();

		//扩展基本属性
		$crowdAttr = new IQuery('crowd_attribute');
		$crowdAttr->where = "crowd_id=".$crowd_info['id']." and attribute_id != '' ";
		$attrInfo = $crowdAttr->find();
		if($attrInfo)
		{
			foreach($attrInfo as $item)
			{
				//key：属性名；val：属性值
				$data['crowd_attr'][$item['attribute_id']] = $item['attribute_value'];
			}
		}

		//众筹分类
		$categoryExtend = new IQuery('crowd_category_extend');
		$categoryExtend->where = 'crowd_id = '.$crowd_info['id'];
		$tb_crowd_photo->fields = 'category_id';
		$cateData = $categoryExtend->find();
		if($cateData)
		{
			foreach($cateData as $item)
			{
				$data['crowd_category'][] = $item['category_id'];
			}
		}
		return $data;
	}
	/**
	 * @param
	 * @return array
	 * @brief 无限极分类递归函数
	 */
	public static function sortdata($catArray, $id = 0 , $prefix = '')
	{
		static $formatCat = array();
		static $floor     = 0;

		foreach($catArray as $key => $val)
		{
			if($val['parent_id'] == $id)
			{
				$str         = self::nstr($prefix,$floor);
				$val['name'] = $str.$val['name'];

				$val['floor'] = $floor;
				$formatCat[]  = $val;

				unset($catArray[$key]);

				$floor++;
				self::sortdata($catArray, $val['id'] ,$prefix);
				$floor--;
			}
		}
		return $formatCat;
	}

	/**
	 * @brief 计算众筹的价格区间
	 * @param $crowdId      众筹id,逗号间隔
	 * @param $showPriceNum 展示分组最大数量
	 * @return array        价格区间分组
	 */
	public static function getCrowdPrice($crowdId,$showPriceNum = 5)
	{
		$crowdObj     = new IModel('crowd');
		$crowdPrice   = $crowdObj->getObj('id in ('.$crowdId.')','MIN(sell_price) as min,MAX(sell_price) as max');
		if($crowdPrice['min'] <= 0)
		{
			return array();
		}

		$minPrice = floor($crowdPrice['min']);

		//众筹价格计算
		$result   = array('1-'.$minPrice);
		$perPrice = floor(($crowdPrice['max'] - $minPrice)/($showPriceNum - 1));

		if($perPrice > 0)
		{
			for($addPrice = $minPrice+1; $addPrice < $crowdPrice['max'];)
			{
				$stepPrice = $addPrice + $perPrice;
				$stepPrice = substr(intval($stepPrice),0,1).str_repeat('9',(strlen(intval($stepPrice)) - 1));
				$result[]  = $addPrice.'-'.$stepPrice;
				$addPrice  = $stepPrice + 1;
			}
		}
		return $result;
	}

	//处理众筹列表显示缩进
	public static function nstr($str,$num=0)
	{
		$return = '';
		for($i=0;$i<$num;$i++)
		{
			$return .= $str;
		}
		return $return;
	}

	/**
	 * @brief  获取分类数据
	 * @param  int   $catId  分类ID
	 * @return array $result array(id => name)
	 */
	public static function catRecursion($catId)
	{
		$result = array();
		$catDB  = new IModel('category');
		$catRow = $catDB->getObj('id = '.$catId);
		while(true)
		{
			if($catRow)
			{
				array_unshift($result,array('id' => $catRow['id'],'name' => $catRow['name']));
				$catRow = $catDB->getObj('id = '.$catRow['parent_id']);
			}
			else
			{
				break;
			}
		}
		return $result;
	}

	/**
	 * @brief 获取树形分类
	 * @param int $catId 分类ID
	 * @return array
	 */
	public static function catTree($catId)
	{
		$result    = array();
		$catDB     = new IModel('category');
		$childList = $catDB->query('parent_id = '.$catId);
		if(!$childList)
		{
			$catRow = $catDB->getObj('id = '.$catId);
			$childList = $catDB->query('parent_id = '.$catRow['parent_id']);
		}
		return $childList;
	}

	/**
	 * @brief 获取子分类可以无限递归获取子分类
	 * @param int $catId 分类ID
	 * @param int $level 层级数
	 * @return array
	 */
	public static function catChild($catId,$level = 1)
	{
		if($level == 0)
		{
			return $catId;
		}

		$temp   = array();
		$result = array($catId);
		$catDB  = new IModel('category');

		while(true)
		{
			$id = current($result);
			if(!$id)
			{
				break;
			}
			$temp = $catDB->query('parent_id = '.$id);
			foreach($temp as $key => $val)
			{
				$result[] = $val['id'];
			}
			next($result);
		}
		return join(',',$result);
	}

	/**
	 * @brief 返回众筹状态
	 * @param int $is_del 众筹状态
	 * @return string 状态文字
	 */
	public static function statusText($is_del)
	{
		$date = array('0' => '上架','1' => '删除','2' => '下架','3' => '等审');
		return isset($date[$is_del]) ? $date[$is_del] : '';
	}

	public static function getCrowdCategory($crowd_id){

		$gcQuery         = new IQuery('category_extend as ce');
		$gcQuery->join   = "left join category as c on c.id = ce.category_id";
		$gcQuery->where  = "ce.crowd_id = ".$crowd_id;
		$gcQuery->fields = 'c.name';

		$gcList = $gcQuery->find();
		$strCategoryNames = '';
		foreach($gcList as $val){
			$strCategoryNames .= $val['name'] . ',';
		}
		unset($gcQuery,$gcList);
		return $strCategoryNames;
	}

	/**
	 * @brief 返回检索条件相关信息
	 * @param int $search 条件数组
	 * @return array 查询条件（$join,$where）数据组
	 */
	public static function getSearchCondition($search)
	{
		$join  = " left join seller as seller on go.seller_id = seller.id ";
		$where = " 1 ";
		//条件筛选处理
		if(isset($search['name']) && isset($search['keywords']) && $search['keywords'])
		{
			$where .= " and ".$search['name']." like '%".$search['keywords']."%' ";
		}

		if(isset($search['category_id']) && $search['category_id'])
		{
			$join  .= " left join category_extend as ce on ce.crowd_id = go.id ";
			$where .= " and ce.category_id = ".$search['category_id'];
		}

		if(isset($search['is_del']) && $search['is_del'] !== '')
		{
			$where .= " and go.is_del = ".$search['is_del'];
		}
		else
		{
			$where .= " and go.is_del != 1";
		}

		if(isset($search['store_nums']) && $search['store_nums'])
		{
			$search['store_nums'] = htmlspecialchars_decode($search['store_nums']);
			$where .= " and ".$search['store_nums'];
		}

		if(isset($search['commend_id']) && $search['commend_id'])
		{
			$join  .= " left join commend_crowd as cg on go.id = cg.crowd_id ";
			$where .= " and cg.commend_id = ".$search['commend_id'];
		}

		if(isset($search['seller_id']) && $search['seller_id'])
		{
			$where .= " and go.seller_id ".$search['seller_id'];
		}
		$results = array($join,$where);
		unset($join,$where);
		return $results;
	}
}