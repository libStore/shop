<?php
/**
 * check website safe strategy
 * @date 2014/9/8 17:00:46
 * @author Jason
 */
class safeStrategy
{
	private $safeInfo = array();

	/**
	 * constructor
	 */
	public function __construct()
	{
	}

	/**
	 * start check website safe options and return a array
	 * @return array
	 */
	public function check()
	{
		$this->cInstall();
		$this->cAuthorize();
		return $this->safeInfo;
	}

	/**
	 * check authorize info
	 */
	private function cAuthorize()
	{
		$return = Proxy::getAuthorize();
		if($return == false)
		{
			$this->safeInfo[] = array('content' => '<a href="http://www.appoil.com/buy" target="_blank">点击授权</a>');
		}
	}

	/**
	 * check the install dir whether exists
	 * @return boolean
	 */
	private function cInstall()
	{
		$appBasePath = Web::$app->getBasePath();
		$installPath = $appBasePath . 'install';

		if(file_exists($installPath))
		{
			$this->safeInfo[] = array('content' => 'install没有删除');
		}
	}
}