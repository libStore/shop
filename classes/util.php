<?php
/**
 * @copyright Copyright(c) 2014 appoil.com
 * @file util.php
 * @brief 公共函数类
 * @author Jason
 * @date 2014-01-13
 * @version 0.6
 * @note
 */

 /**
 * @class Util
 * @brief 公共函数类
 */
class Util
{
	/**
	 * @brief 显示错误信息（dialog框）
	 * @param string $message	错误提示字符串
	 */
	public static function showMessage($message)
	{
		echo '<script type="text/javascript">art.dialog.tips("'.$message.'")</script>';
		exit;
	}

	/**
	 * 处理二维数组
	 *
	 * 根据第二维某个索引的值来设置相应的第一维数组的key
	 * 如原来是
	 * array(array('id'=>'a','data'=>'') ,array('id'=>1000,'data'=>'')  )
	 * 按照id索引处理后：
	 * array('a'=>array('id'=>'a','data'=>'') ,1000=>array('id'=>1000,'data'=>'')  )
	 *
	 * @author Jason
	 * @param array $arr	待处理的二维数组
	 * @param array $key	获取第二维值的索引
	 * @return array
	 */
	public static function array_rekey($arr,$key='id')
	{
		$fun_re=array();
		foreach($arr as $value)
		{
			$fun_re[$value[$key]]=$value;
		}
		return $fun_re;
	}
	/**
	 * 检测是否为合法的用户名
	 *
	 * 合法的用户名：英文字母、数组、下划线、短横线、中文
	 * @param string $username
	 * @return bool
	 * @author Jason
	 */
	 public static function is_username($username)
	 {
	 	return preg_match("!^[_a-zA-Z0-9\\x{4e00}-\\x{9fa5}]{2,20}$!u",$username);
	 }

	/**
	 * 把一、二维数组转成CSV格式的字符串
	 *
	 * @param array $array
	 * @param string $delimiter
	 * @param string $enclosure
	 * @return string
	 * @author Jason
	 */
	public static function array2csv($array,$delimiter=",",$enclosure='"')
	{
		$string = array();
		foreach($array as $row)
		{
			$row_string=array();
			//判断是否为二维数组
			if(is_array($row))
			{
				foreach($row as $value)
				{
					$row_string[] = self::string2csv($value,$delimiter=",",$enclosure='"');
				}
				$string[] = implode($delimiter,$row_string);
			}
			else
			{
				$string[] = self::string2csv($row,$delimiter=",",$enclosure='"');
			}
		}
		$string = implode("\n",$string);
		return $string;
	}
	/**
	 * 单个字段内容转成csv格式
	 *
	 * @param array $string
	 * @param string $delimiter
	 * @param string $enclosure
	 * @return string
	 * @author Jason
	 */
	public static function string2csv($string,$delimiter=",",$enclosure='"')
	{
		if($string)
		{
			$string = str_replace($enclosure,$enclosure.$enclosure,$string);
			$string = $enclosure.$string.$enclosure;
		}
		return $string;
	}
	//字符串拼接
	public static function joinStr($id)
	{
		if(is_array($id))
		{
			$where = "id in (".join(',',$id).")";
		}
		else
		{
			$where = 'id = '.$id;
		}
		return $where;
	}

	/**
	 * 商品价格格式化
	 * @param $price float 商品价
	 * @return float 格式化后的价格
	 */
	public static function priceFormat($price)
	{
		return round($price,2);
	}

	/**
	 * 检索自动执行
	 * @param array $search 查询拼接规则， key => like,likeValue
	 */
	public static function search($search)
	{
		if(!$search)
		{
			return '';
		}

		$where = array();

		//like子句处理
		if(isset($search['like']) && $search['likeValue'])
		{
			$where[] = $search['like']." like '%".$search['likeValue']."%'";
		}
		unset($search['like']);
		unset($search['likeValue']);

		//自定义子句处理
		foreach($search as $key => $val)
		{
			if($val === '')
			{
				continue;
			}

			if(strpos($key,'num') !== false)
			{
				$where[] = $key.$val;
			}
			else
			{
				$where[] = $key."'".$val."'";
			}
		}

		return join(" and ",$where);
	}

	/**
	 * @brief 获取$_GET指定的参数
	 * @param $urlKey 参数名称
	 * @return mixed
	 */
	public static function getUrlParam($urlKey)
	{
		$tempGet = $_GET;
		foreach($tempGet as $key => $val)
		{
			if($key != $urlKey)
			{
				unset($tempGet[$key]);
			}
		}
		return $tempGet;
	}
}