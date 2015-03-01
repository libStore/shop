<?php
/**
 * @copyright (c) 2014 appoil.com
 * @file error.php
 * @brief 错误处理类
 * @author Jason
 * @date 2014-12-16
 * @version 0.6
 */
class Error extends IController
{
	//获取当前场景的常量
	public static function getScene()
	{
		return defined("WEB_SCENE") ? WEB_SCENE : null;
	}

	public function error404()
	{
		$heading = '文件不存在';
		$data    = '请确定要访问的页面在是否存在';

		switch(self::getScene())
		{
			case themeroute::SCENE_SYSDEFAULT:
			case themeroute::SCENE_SYSSELLER:
			{
				$this->redirect('/block/error/?msg='.urlencode($data).'&heading='.urlencode($heading));
			}
			break;

			default:
			{
				$this->redirect('/site/error/?msg='.urlencode($data).'&heading='.urlencode($heading));
			}
		}
	}

	public function error403($data)
	{
		$heading = '操作发生错误';
		if(is_array($data))
		{
			$data = isset($data['message']) ? $data['message'] : '';
		}

		switch(self::getScene())
		{
			case themeroute::SCENE_SYSDEFAULT:
			case themeroute::SCENE_SYSSELLER:
			{
				$this->redirect('/block/error/?msg='.urlencode($data).'&heading='.urlencode($heading));
			}
			break;

			default:
			{
				$this->redirect('/site/error/?msg='.urlencode($data).'&heading='.urlencode($heading));
			}
		}
	}

	public function error503()
	{
		$data = '您无权限操作此功能';
		$this->redirect('/block/error/?msg='.urlencode($data));
	}
}