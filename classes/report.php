<?php
/**
 * @copyright (c) 2014 appoil
 * @file report.php
 * @brief 导出excel类库
 * @author Jason
 * @date 2014/11/28 22:09:43
 * @version 1.0.0
 */

class report
{
	//文件名
	private $fileName = 'user';
	
	public function setFileName($fileName)
	{
		$this->fileName = $fileName;
	}
	//开始下载
	public function toDownload ($strTable)
	{
		header("Content-type: application/vnd.ms-excel");
		header("Content-Type: application/force-download");
		header("Content-Disposition: attachment; filename=".$this->fileName."_".date('Y-m-d').".xls");
		header('Expires:0');
		header('Pragma:public');
		echo $strTable;
	}
}
?>