<?php
/**
 * @brief ��̬�������ͼ��
 */
class Thumb
{
	//����ͼ·��
	public static $thumbDir = "runtime/_thumb/";

	/**
	 * @brief ��ȡ����ͼ����·��
	 */
	public static function getThumbDir()
	{
		return Web::$app->getBasePath() . self::$thumbDir;
	}

	/**
	 * @brief �������ͼ
	 * @param string $imgSrc ͼƬ·��
	 * @param int $width ͼƬ���
	 * @param int $height ͼƬ�߶�
	 * @return string WEBͼƬ·�����
	 */
    public static function get($imgSrc,$width=100,$height=100)
    {
    	if($imgSrc == '')
    	{
    		return '';
    	}

		//��Ʒ����ʵ��·��
		$sourcePath = Web::$app->getBasePath() . $imgSrc;

		//����ͼ�ļ���
		$preThumb      = "{$width}_{$height}_";
		$thumbFileName = $preThumb.basename($imgSrc);

		//����ͼĿ¼
		$thumbDir    = self::getThumbDir().dirname($imgSrc)."/";
		$webThumbDir = self::$thumbDir.dirname($imgSrc)."/";

		if(is_file($thumbDir.$thumbFileName) == false && is_file($sourcePath))
		{
			IImage::thumb($sourcePath,$width,$height,$preThumb,$thumbDir);
		}
		return $webThumbDir.$thumbFileName;
    }
}