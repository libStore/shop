<?php
/**
 * @brief 淘宝采集器
 * @author Jason
 * @date 2015/1/4 16:12:50
 */
class taobao_collect extends collect
{
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
	}

	/**
	 * @brief 检查详情url
	 */
	public function checkShowUrl($url)
	{
		return strpos($url,'http://item.taobao.com/item.htm') === false ? false : true;
	}

	/**
	 * @brief 获取商品详情从详情页面
	 * @return string 商品的详情数据
	 */
	public function pickGoodsContentFromShow()
	{
		$content = '';
		preg_match_all('/<script[^>]*>[^<]+<\/script>/is',$this->showPageHtml,$match);
		if(isset($match[0]) && $match[0])
		{
			$content = join(" ",$match[0]).'<div id="description"><div id="J_DivItemDesc">商品描述已经加载，必须从前台页面动态显示</div></div>';
		}
		else
		{
			$this->error[] = "获取不到页面中的JS脚本";
			return;
		}
		return $content;
	}

	/**
	 * @brief 采集商品信息
	 * @param int $num 采集数量
	 * @return array
	 */
	public function collect($num)
	{

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
}