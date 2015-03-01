<?php
/**
 * @brief 公共方法集合
 * @class Common
 * @note  公开方法集合适用于整个系统
 */
class Common
{
	/**
	 * @brief 获取语言包,主题,皮肤的方案
	 * @param string $type  方案类型: theme:主题; skin:皮肤; lang:语言包;
	 * @param string $theme 此参数只有$type为skin时才有用，获取任意theme下的skin方案;
	 * @return string 方案的路径
	 */
	public static function getSitePlan($type,$theme = null)
	{
		$except      = array('.','..','.svn','.htaccess','sysdefault','sysseller');
		$defaultConf = 'config.php';
		$ctrlObj = Web::$app->getController();
		$planPath    = null;    //资源方案的路径
		$planList    = array(); //方案列表
		$configKey   = array('name','version','author','time','thumb','info');

		//根据不同的类型设置方案路径
		switch($type)
		{
			case "theme":
			{
				$planPath = dirname($ctrlObj->getViewPath());
				$webPath  = $ctrlObj->themeDir();
			}
			break;

			case "skin":
			{
				$planPath = dirname($ctrlObj->getViewPath()).'/'.$theme.'/'.$ctrlObj->skinDir();
				$webPath  = $ctrlObj->themeDir().'/'.$theme.'/'.$ctrlObj->skinDir();
			}
			break;

			case "lang":
			{
				$planPath = dirname($ctrlObj->getLangPath());
			}
			break;
		}

		if($planPath != null)
		{
			$planList = array();
			$dirRes   = opendir($planPath);

			//遍历目录读取配置文件
			while($dir = readdir($dirRes))
			{
				if(!in_array($dir,$except))
				{
					$fileName = $planPath.'/'.$dir.'/'.$defaultConf;
					$tempData = file_exists($fileName) ? include($fileName) : array();
					if($tempData)
					{
						//拼接系统所需数据
						foreach($configKey as $val)
						{
							if(!isset($tempData[$val]))
							{
								$tempData[$val] = '';
							}
						}

						//缩略图拼接路径
						if(isset($tempData['thumb']) && isset($webPath))
						{
							$tempData['thumb'] = $webPath.'/'.$dir.'/'.$tempData['thumb'];
						}
						$planList[$dir] = $tempData;
					}
				}
			}
		}
		return $planList;
	}

	/**
	 * @brief 检查主题方案是否被应用
	 * @param string $plan 方案名称
	 * @return boolean
	 */
	public static function isThemeUsed($plan)
	{
		if (isset(Web::$app->config['theme']))
		{
			if (is_array(Web::$app->config['theme']))
			{
				if (in_array($plan, Web::$app->config['theme']))
				{
					return true;
				}
			} elseif (Web::$app->config['theme'] == $plan)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * @brief 检查皮肤方案是否被应用
	 * @param string $plan 方案名称
	 * @return boolean
	 */
	public static function isSkinUsed($plan)
	{
		if (isset(Web::$app->config['skin']))
		{
			if (is_array(Web::$app->config['skin']))
			{
				if (in_array($plan, Web::$app->config['skin']))
				{
					return true;
				}
			} elseif (Web::$app->config['skin'] == $plan)
			{
				return true;
			}
		}
		return false;
	}

	/**
	 * @brief 应用主题方法
	 * @param array $themeArray 主题数据 array('平台' => '主题名称')
	 */
	public static function applyTheme($themeArray)
	{
		//兼容老式主题，仅有PC平台的非数组形式
		$theme1 = is_array(Web::$app->config['theme']) ? Web::$app->config['theme'] : array('pc' => Web::$app->config['theme']);
		$themeArray = array_merge($theme1,$themeArray);

		//获取$theme主题下皮肤方案
		$skinArray = array();
		foreach($themeArray as $client => $theme)
		{
			$skinList = array_keys(Common::getSitePlan('skin',$theme));
			$skin     = $skinList ? current($skinList) : null;
			$skinArray = array_merge($skinArray,array($client => $skin));
		}

		$data = array(
			'theme' => $themeArray,
			'skin'  => $skinArray,
		);

		Config::edit('config/config.php',$data);
	}
	/**
	 * @brief 应用皮肤方法
	 * @param string $theme 主题
	 * @param string $skin 皮肤
	 */
	public static function applySkin($theme,$skin)
	{
		//兼容老式主题，仅有PC平台的非数组形式
		$skinArray = is_array(Web::$app->config['skin']) ? Web::$app->config['skin'] : array('pc' => Web::$app->config['skin']);

		//根据其theme查询出所属于的平台
		foreach (Web::$app->config['theme'] as $client => $themeParam)
		{
			if($themeParam == $theme)
			{
				$skinArray = array_merge($skinArray,array($client => $skin));
			}
		}

		$data = array(
			'skin' => $skinArray
		);

		Config::edit('config/config.php',$data);
	}

	/**
	 * @brief 获取评价分数
	 * @param $grade float 分数
	 * @param $comments int 评论次数
	 * @return float
	 */
	public static function gradeWidth($grade,$comments = 1)
	{
		return $comments == 0 ? 0 : 14*($grade/$comments);
	}

	/**
	 * @brief 获取用户状态
	 * @param $status int 状态代码
	 * @return string
	 */
	public static function userStatusText($status)
	{
		$mapping = array('1' => '正常','2' => '删除','3' => '锁定');
		return isset($mapping[$status]) ? $mapping[$status] : '';
	}

    //[账户余额] 提现状态判定
    public static function getWithdrawStatus($status)
    {
    	$data = array(
    		'0'  => '未处理',
    		'-1' => '提现失败',
    		'1'  => '处理中',
    		'2'  => '提现成功',
    	);
    	return isset($data[$status]) ? $data[$status] : '未知状态';
    }
}