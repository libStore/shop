<?php
/**
 * @brief 商品采集器与appoil外观模式
 * @author Jason
 * @date 2014/1/2 8:25:15
 */
class collect_facade
{
	/**
	 * @brief 创建不同类型的采集器
	 * @param $collect_name 采集器名称
	 * @return collect
	 */
	private static function createInstance($collect_name)
	{
		$collectorObject = null;
		$pluginDir = Web::$app->getBasePath() . 'plugins/collect/';
		switch($collect_name)
		{
			//京东商城数据采集器
			case 'jd':
			{
				include_once($pluginDir.'jd_collect.php');
				$collectorObject = new jd_collect();
			}
			break;

			case 'taobao':
			{
				include_once($pluginDir.'taobao_collect.php');
				$collectorObject = new taobao_collect();
			}
			break;
		}
		return $collectorObject;
	}

	/**
	 * @brief 运行采集功能
	 * @param string $collect_name 采集器名字
	 * @param string $url 采集器url地址
	 * @param int $num 采集商品数量
	 * @return array('result' => success or fail,'msg' => '错误信息','data' => 数据信息)
	 */
	public static function run($collect_name,$url,$num = 20)
	{
		set_time_limit(0);
		ini_set("max_execution_time", "0");

		$collectorObject = self::createInstance($collect_name);
		if(!$collectorObject)
		{
			return array('result' => 'fail','message' => '没有找到采集器');
		}

		$collectorObject->readListPage($url);
		$goodsData = $collectorObject->collect($num);

		//实例化对象
		$catObj    = new IModel('category');
		$catExtObj = new IModel('category_extend');
		$attrObj   = new IModel('attribute');
		$goodsObj  = new IModel('goods');
		$goodsAttrObj = new IModel('goods_attribute');
		$photoObj     = new IModel('goods_photo');
		$photoRelObj  = new IModel('goods_photo_relation');
		$modelObj     = new IModel('model');
		$productsObj  = new IModel('products');

		//信息入库
		if(isset($goodsData['cat']) && $goodsData['cat'])
		{
			$model_id = 0;
			$attrMap  = array();
			if(isset($goodsData['attr']) && $goodsData['attr'])
			{
				$modelName = end($goodsData['cat']);

				//1,模型存在情况-直接读取
				if($modelRow = $modelObj->getObj('name = "'.$modelName.'"'))
				{
					$model_id = $modelRow['id'];
					$attrList = $attrObj->query('model_id = '.$model_id);
					foreach($attrList as $key => $val)
					{
						$attrMap[$val['name']] = $val['id'];
					}
				}
				//2,模型不存在情况-插入操作
				else
				{
					//创建模型
					$modelObj->setData(array('name' => $modelName));
					$model_id = $modelObj->add();

					//创建模型属性
					foreach($goodsData['attr'] as $key => $val)
					{
						$attrObj->setData(array('model_id' => $model_id,'type' => 2,'name' => $key,'value' => $val,'search' => 1));
						$newAttrId = $attrObj->add();
						$attrMap[$key] = $newAttrId;
					}
				}
			}

			//分类添加
			$parentId = 0;
			$catPath = array();
			foreach($goodsData['cat'] as $key => $val)
			{
				if($catRow = $catObj->getObj('name = "'.$val.'"'))
				{
					$catPath[] = $parentId = $catRow['id'];
				}
				else
				{
					$catObj->setData(array('name' => $val,'parent_id' => $parentId));
					$parentId = $catObj->add();
					$catPath[] = $parentId;
				}
			}

			//处理商品数据
			foreach($goodsData['item'] as $key => $val)
			{
				//商品添加
				$goodsObj->setData(array(
					'name'         => $val['name'],
					'sell_price'   => $val['price'],
					'market_price' => $val['price'],
					'model_id'     => $model_id,
					'goods_no'     => $val['goods_no'],
					'up_time'      => $val['up_time'],
					'content'      => IFilter::act($val['content'],'text'),
					'store_nums'   => 100,
					'weight'       => $val['weight'],
					'unit'         => $val['unit'],
					'create_time'  => date('Y-m-d H:i:s'),
				));
				$goods_id = $goodsObj->add();

				//商品图片拷贝
				if(isset($val['img']) && $val['img'])
				{
					foreach($val['img'] as $img)
					{
						$md5Val     = md5_file($img);
						$photoData  = $photoObj->getObj('id = "'.$md5Val.'"');

						//如果图库中没有图片数据就要拷贝
						if(!$photoData)
						{
							$destFile = PhotoUpload::hashDir().'/'.basename($img);

							while(true)
							{
								$copyResult = IFile::copy($img, Web::$app->getBasePath() . "/" . $destFile);
								if($copyResult)
								{
									$photoObj->setData(array('id' => $md5Val,'img' => $destFile));
									$photoObj->add();
									break;
								}
							}
						}

						//商品图片关联
						$photoRelObj->setData(array('goods_id' => $goods_id,'photo_id' => $md5Val));
						$photoRelObj->add();
					}

					$imgVal = isset($destFile) ? $destFile : $photoData['img'];
					$goodsObj->setData(array('img' => $imgVal));
					$goodsObj->update('id = '.$goods_id);
				}

				//商品与商品分类关联
				if($catPath)
				{
					foreach($catPath as $catId)
					{
						$catExtObj->setData(array('goods_id' => $goods_id,'category_id' => $catId));
						$catExtObj->add();
					}
				}

				//商品与属性关联
				if(isset($val['attr']) && $val['attr'])
				{
					foreach($val['attr'] as $attrName => $attrVal)
					{
						if(isset($attrMap[$attrName]))
						{
							$attrArray = explode(',',$attrVal);
							foreach($attrArray as $k => $v)
							{
								$goodsAttrObj->setData(array(
									'goods_id' => $goods_id,
									'attribute_id' => $attrMap[$attrName],
									'attribute_value' => $v,
									'model_id' => $model_id
								));
								$goodsAttrObj->add();
							}
						}
					}
				}
			}
			if($collectorObject->error)
			{
				return array('result' => 'fail','msg' => join("<br/>",$collectorObject->error));
			}
			else
			{
				return array('result' => 'success','msg' => join("<br/>",$collectorObject->error));
			}
		}
		else
		{
			return array('result' => 'fail','msg' => '采集信息不存在');
		}
	}

	/**
	 * @brief 采集商品详情页面信息
	 * @param string $collectName 采集器名称
	 * @param string $url 商品详情地址
	 * @return array('result' => success or fail,'msg' => '错误信息','data' => 数据信息)
	 */
	public static function runDetail($collectName,$url)
	{
		set_time_limit(0);
		ini_set("max_execution_time", "0");

		$collectorObject = self::createInstance($collectName);
		if(!$collectorObject)
		{
			return array('result' => 'fail','msg' => '没有找到采集器');
		}
		$content = $collectorObject->collectDetail($url);

		if($collectorObject->error)
		{
			return array('result' => 'fail','msg' => join("<br/>",$collectorObject->error));
		}
		else
		{
			return array('result' => 'success','data' => $content);
		}
	}
}

/**
 * @brief 采集器抽象类
 * @date 2014/1/1 20:21:11
 * @author Jason
 */
abstract class collect
{
	//已经采集到的列表页面html代码
	protected $listPageHtml = '';

	//已经采集到的详情页面html代码
	protected $showPageHtml = '';

	//错误堆栈
	public $error = array();

	/**
	 * @brief 获取列表页面的html代码
	 * @param $url string 列表页面url地址
	 */
	public function readListPage($url)
	{
		if($this->checkListUrl($url) == false)
		{
			$this->error[] = 'URL不符合规范';
			return;
		}

		if(!$content = file_get_contents($url,false,$this->context))
		{
			$this->error[] = '没有采集到列表页面的html代码';
			return;
		}

		//转码GBK转换UTF-8
		$this->listPageHtml = $this->converContent($content);
	}

	/**
	 * @brief 获取商品详情页面数据
	 * @param $url string 详情页面url
	 */
	public function readShowPage($url)
	{
		if($this->checkShowUrl($url) == false)
		{
			$this->error[] = 'URL不符合规范';
			return;
		}

		$content = file_get_contents($url,false,$this->context);

		//转码GBK转换UTF-8
		$this->showPageHtml = $this->converContent($content);
	}

	/**
	 * @brief 字符串转码
	 * @param $content string 要转换的字符串
	 * @return string
	 */
	public function converContent($content)
	{
		if(IString::isUTF8($content))
		{
			return $content;
		}
		else
		{
			return IString::converEncode($content,"UTF-8","GBK");
		}
	}

	/**
	 * @brief 过滤掉空间
	 * @param $content string 要过滤的字符串
	 * @return string
	 */
	public function filterSpace($content)
	{
		return str_replace(array(PHP_EOL,"\r\n","\r","\n"),"",$content);
	}

	/**
	 * @brief 检查商品列表url的合法性
	 * @param $url string
	 * @return boolean
	 */
	abstract public function checkListUrl($url);

	/**
	 * @brief 检查商品详情url的合法性
	 * @param $url string
	 * @return boolean
	 */
	abstract public function checkShowUrl($url);

	/**
	 * @brief 采集商品信息
	 * @param int $num 采集数量
	 * @return array
	 */
	abstract public function collect($num);

	/**
	 * @brief 采集商品详情信息
	 * @param int $url 采集URL地址
	 * @return string
	 */
	abstract public function collectDetail($url);
}