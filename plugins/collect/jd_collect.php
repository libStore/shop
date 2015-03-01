<?php
/**
 * @brief 京东商品列表采集器,适用于普通类，不可再分类的列表
 * @author Jason
 * @date 2014/12/25 13:52:19
 */
class jd_collect extends collect
{
	//属性数据
	private $cacheAttrData = array();

	//读取超时设置
	public $httpContext = array(
		'http' => array(
			'method'  => "GET",
			'timeout' => 550,
		)
	);

	/**
	 * @brief 构造函数
	 */
	public function __construct()
	{
		$this->context = stream_context_create($this->httpContext);
	}

	/**
	 * @brief 检查列表url
	 */
	public function checkListUrl($url)
	{
		return strpos($url,'http://list.jd.com/list.html?cat=') === false ? false : true;
	}

	/**
	 * @brief 检查详情url
	 */
	public function checkShowUrl($url)
	{
		return strpos($url,'http://item.jd.com') === false ? false : true;
	}

	/**
	 * @brief 挑选分类
	 * @return array 根据层次返回分类
	 */
	public function pickCatFromList()
	{
		$catExp = '|<strong><a href=.+?</a></span>|';
		preg_match($catExp,$this->listPageHtml,$match);
		if(!isset($match[0]))
		{
			$this->error[] = '页面缺少商品分类';
			return;
		}
		return explode('&nbsp;&gt;&nbsp;',trim(strip_tags($match[0])));
	}

	/**
	 * @brief 挑选属性
	 * @return array 属性数据
	 */
	public function pickAttributeFromList()
	{
		$keyExp   = '@<div class="a-key">(.+?)</div>@';
		$valueExp = '@<ul class="f-list">(.+?)</ul>@';

		preg_match_all($keyExp,$this->listPageHtml,$matchKey);
		preg_match_all($valueExp,$this->listPageHtml,$matchValue);

		if(!isset($matchKey[1]) && !isset($matchValue[1]))
		{
			$keyExp   = '@<div class=\\\\"a-key\\\\">(.+?)</div>@';
			$valueExp = '@<ul class=\\\\"f-list\\\\">(.+?)</ul>@';
			preg_match_all($keyExp,$this->listPageHtml,$matchKey);
			preg_match_all($valueExp,$this->listPageHtml,$matchValue);
		}

		//过滤无用的数据
		array_shift($matchKey[1]);//移除品牌
		array_shift($matchKey[1]);//移除价格
		array_shift($matchValue[1]);//移除价格

		$attrData = array();
		foreach($matchKey[1] as $key => $val)
		{
			$attrData[trim($val,'：')] = trim(strip_tags(strtr($matchValue[1][$key],array('</li>' => '</li>,'))),',');
		}
		return $attrData;
	}

	/**
	 * @brief 挑选列表页面的商品连接
	 * @return array 商品详情的url
	 */
	public function pickGoodsLinkFromList()
	{
		$linkExp = '@<div class="p-img"><a target="_blank" href="(.+?)"@';
		preg_match_all($linkExp,$this->listPageHtml,$match);

		if(!isset($match[1]))
		{
			$linkExp = '@<div class=\\\\"p-img\\\\"><a target=\\\\"_blank\\\\" href=\\\\"(.+?)\\\\"@';
			preg_match_all($linkExp,$this->listPageHtml,$match);
		}
		return $match[1];
	}

	/**
	 * @brief 获取商品名称从详情页面
	 * @return string 商品名字
	 */
	public function pickGoodsNameFromShow()
	{
		$exp = '@<h1>.+?</h1>@';
		preg_match($exp,$this->showPageHtml,$match);

		if(!isset($match[0]))
		{
			$this->error[] = '没有找到商品名称';
			return;
		}
		return strip_tags($match[0]);
	}

	/**
	 * @brief 获取商品价格从API
	 * @param $idArray string 商品id数组,如：J_970602
	 * @return string 商品价格json
	 */
	public function getGoodsPriceFromAPI($idString)
	{
		$apiUrl = 'http://p.3.cn/prices/mgets?skuIds='.trim($idString,',');
		$result = file_get_contents($apiUrl,false,$this->context);
		$result = strtr($result,array('J_' => ''));
		return JSON::decode($result);
	}

	/**
	 * @brief 获取商品属性从详情页面
	 * @return string 商品某属性
	 */
	public function pickGoodsAttributeFromShow()
	{
		$exp = '@<ul class="detail-list">(.+?)</ul>@s';
		preg_match($exp,$this->showPageHtml,$match);

		if(!isset($match[1]))
		{
			$exp = '@<ul id="parameter2" class="p-parameter-list">(.+?)</ul>@s';
			preg_match($exp,$this->showPageHtml,$match);

			if(!isset($match[1]))
			{
				$this->error[] = '没有找到商品属性';
				return;
			}
		}

		//利用li的结束补加"@"的方式进行explode属性分组
		$match[1] = $this->filterSpace(strip_tags(strtr($match[1],array('</li>' => '@</li>'))));
		$tempArray = explode('@',$match[1]);

		$attrArray = array();
		$tmp = array();
		foreach($tempArray as $key => $val)
		{
			$tmp = explode('：',$val);
			if(isset($tmp[1]))
			{
				$attrArray[$tmp[0]] = trim(strtr($tmp[1],array("，" => ",")));
			}
		}
		return $this->cacheAttrData = $attrArray;
	}

	/**
	 * @brief 获取商品图片从详情页面
	 * @return array 商品的图片url
	 */
	public function pickGoodsImageFromShow()
	{
		$exp = '@data-url=["\'](.+?)["\']@';
		preg_match_all($exp,$this->showPageHtml,$match);

		if(!isset($match[1]) || !is_array($match[1]))
		{
			$this->error[] = '没有找到商品图片';
			return;
		}

		$jdImageServerPre = 'http://img13.360buyimg.com/n0/';
		foreach($match[1] as $key => $val)
		{
			$match[1][$key] = $jdImageServerPre.$val;
		}
		return $match[1];
	}

	/**
	 * @brief 获取商品规格从详情页面
	 * @return array 商品的规格 array(规格名称=>规格值)
	 */
	public function pickGoodsSpecFromShow()
	{
		$exp = '@<li id="choose-(?:version|color)".*?>.*?</li>@s';
		preg_match_all($exp,$this->showPageHtml,$match);

		$result = array();
		if(isset($match[0]) && $match[0])
		{
			foreach($match[0] as $key => $val)
			{
				$val = trim(strip_tags(strtr($val,array('</a>' => '</a>,'))),',');
				$temp = explode('：',$val);

				if(isset($temp[1]))
				{
					$result[$temp[0]] = $temp[1];
				}
			}
		}
		return $result;
	}

	/**
	 * @brief 获取商品详情从详情页面
	 * @return string 商品的详情数据
	 */
	public function pickGoodsContentFromShow()
	{
		$match = array();
		$exp = '@<div class="detail-content">.*<!--product-detail end-->@s';
		preg_match($exp,$this->showPageHtml,$match);

		if(!isset($match[0]))
		{
			preg_match("@skuid: (\d+)@",$this->showPageHtml,$code);
			if(isset($code[1]))
			{
				//ajax异步读取详情信息
				$content = file_get_contents("http://d.item.jd.com/".$code[1].".html",false,$this->context);
				$content = trim(str_replace("\n","",$this->converContent($content)),"showdesc()");
				$contentArray = JSON::decode($content);
				if(isset($contentArray['content']))
				{
					$match[0] = $contentArray['content'];
				}
				else
				{
					$this->error[] = "ajax详情,没有正确解析json";
				}
			}
			else
			{
				$this->error[] = "ajax详情,没有查询到商品编号";
			}
		}

		if(isset($match[0]))
		{
			return strtr($match[0],array('data-lazyload' => 'src'));
		}
		return '';
	}

	/**
	 * @brief 获取商品重量
	 * @return string 商品重量
	 */
	public function pickGoodsWeightFromShow()
	{
		if(!$this->cacheAttrData)
		{
			$this->pickGoodsAttributeFromShow();
		}

		if(isset($this->cacheAttrData['商品毛重']))
		{
			preg_match('@[\d\.]+@',$this->cacheAttrData['商品毛重'],$matchAttr);
		}
		return isset($matchAttr[0]) ? $matchAttr[0] : 0;
	}

	/**
	 * @brief 获取商品计量单位
	 * @return string 计量单位
	 */
	public function pickGoodsUnitFromShow()
	{
		if(!$this->cacheAttrData)
		{
			$this->pickGoodsAttributeFromShow();
		}

		if(isset($this->cacheAttrData['商品毛重']))
		{
			preg_match('@[\d\.]+(.*)$@',$this->cacheAttrData['商品毛重'],$matchAttr);
		}

		return isset($matchAttr[1]) ? $matchAttr[1] : '千克';
	}

	/**
	 * @brief 采集商品详情信息
	 * @param int $url 采集URL地址
	 * @return string
	 */
	public function collectDetail($url)
	{
		$this->readShowPage($url);
		if(!$this->error)
		{
			return $this->pickGoodsContentFromShow();
		}
	}

	/**
	 * @brief 开始采集商品
	 * @param int $num 采集数量
	 * @return array('cat' => '商品分类','attr' => '属性','item' => array(
	 * 'goods_no' => '商品编号','up_time' => '上架时间','weight' => '重量','unit' => '计量单位','name' => '商品名字','price' => '商品价格','img' => array(商品图片),'content' => '商品详情','spec' => '商品规格','attr' => '商品属性'
	 * ))
	 */
	public function collect($num = 20)
	{
		$result = array(
			'cat' => $this->pickCatFromList(),
			'attr'=> $this->pickAttributeFromList(),
			'item'=> array()
		);

		$goodsUrl = $this->pickGoodsLinkFromList();
		foreach($goodsUrl as $key => $val)
		{
			if($num > 0 && $key >= $num)
			{
				break;
			}

			$this->readShowPage($val);
			preg_match('@\d+@',$val,$match);

			$priceObj = $this->getGoodsPriceFromAPI('J_'.$match[0]);
			$attrData = $this->pickGoodsAttributeFromShow();

			$result['item'][] = array(
				'goods_no' => isset($attrData['商品编号']) ? $attrData['商品编号'] : '',
				'up_time'  => isset($attrData['上架时间']) ? $attrData['上架时间'] : '',
				'weight' => $this->pickGoodsWeightFromShow(),
				'unit'   => $this->pickGoodsUnitFromShow(),
				'name'   => $this->pickGoodsNameFromShow(),
				'price'  => $priceObj[0]['p'],
				'img'    => $this->pickGoodsImageFromShow(),
				'content'=> $this->pickGoodsContentFromShow(),
				'spec'   => $this->pickGoodsSpecFromShow(),
				'attr'   => $attrData
			);
		}
		return $result;
	}
}