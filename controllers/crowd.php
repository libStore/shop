<?php
/**
 * @brief 众筹模块
 * @class Crowd
 * @note  后台
 */
class Crowd extends IController
{
	public $checkRight  = 'all';
    public $layout = 'admin';
    private $data = array();

	public function init()
	{
		IInterceptor::reg('CheckRights@onCreateAction');
	}

	/**
	 * @brief 众筹列表
	 */
	function crowd_list()
	{
		//搜索条件
		$search = IFilter::act(IReq::get('search'));
		$page   = IReq::get('page') ? IFilter::act(IReq::get('page'),'int') : 1;

		//条件筛选处理
		list($join,$where) = crowd_class::getSearchCondition($search);

		//拼接sql
		$crowdHandle = new IQuery('crowd as go');
		$crowdHandle->order    = "go.sort asc,go.id desc";
		$crowdHandle->distinct = "go.id";
		$crowdHandle->fields   = "go.*,seller.true_name";
		$crowdHandle->page     = $page;
		$crowdHandle->where    = $where;
		$crowdHandle->join     = $join;

		$this->search      = $search;
		$this->crowdHandle = $crowdHandle;
		$this->redirect("crowd_list");
	}


	/**
	 * 选择规格数据
	 */
	function select_spec()
	{
		$this->layout = '';
		$this->redirect('select_spec');
	}
	/**
	 * @brief 众筹添加中图片上传的方法
	 */
	public function crowd_img_upload()
	{
		//获得配置文件中的数据
		$config = new Config("site_config");

	 	//调用文件上传类
		$photoObj = new PhotoUpload();
		$photo    = current($photoObj->run());

		//判断上传是否成功，如果float=1则成功
		if($photo['flag'] == 1)
		{
			$result = array(
				'flag'=> 1,
				'img' => $photo['img']
			);
		}
		else
		{
			$result = array('flag'=> $photo['flag']);
		}
		echo JSON::encode($result);
	}
    /**
	 * @brief 众筹模型添加/修改
	 */
    public function model_update()
    {
    	// 获取POST数据
    	$model_id   = IFilter::act(IReq::get("model_id"));
    	$model_name = IFilter::act(IReq::get("model_name"));
    	$attribute  = IFilter::act(IReq::get("attr"));
    	$spec       = IFilter::act(IReq::get("spec"));

    	//初始化Model类对象
		$modelObj = new Model();

		//更新模型数据
		$result = $modelObj->model_update($model_id,$model_name,$attribute,$spec);

		if($result)
		{
			$this->redirect('model_list');
		}
		else
		{
			//处理post数据，渲染到前台
    		$result = $modelObj->postArrayChange($attribute,$spec);
			$this->data = array(
				'id'         => $model_id,
				'name'       => $model_name,
				'model_attr' => $result['model_attr'],
				'model_spec' => $result['model_spec']
			);
    		$this->setRenderData($this->data);
			$this->redirect('model_edit',false);
		}
    }
	/**
	 * @brief 众筹模型修改
	 */
    public function model_edit()
    {
    	// 获取POST数据
    	$id = IFilter::act(IReq::get("id"),'int');
    	if($id)
    	{
    		//初始化Model类对象
    		$modelObj = new Model();
    		//获取模型详细信息
			$model_info = $modelObj->get_model_info($id);
			//向前台渲染数据
			$this->setRenderData($model_info);
    	}
		$this->redirect('model_edit');
    }

	/**
	 * @brief 众筹模型删除
	 */
    public function model_del()
    {
    	//获取POST数据
    	$id = IFilter::act(IReq::get("id"));
    	$id = !is_array($id) ? array($id) : $id;

    	if($id)
    	{
	    	foreach($id as $key => $val)
	    	{
	    		//初始化crowd_attribute表类对象
	    		$crowd_attrObj = new IModel("crowd_attribute");

	    		//获取众筹属性表中的该模型下的数量
	    		$attrData = $crowd_attrObj->query("model_id = ".$val);
	    		if($attrData)
	    		{
	    			$this->redirect('model_list',false);
	    			Util::showMessage("无法删除此模型，请确认该模型下以及回收站内都无众筹");
	    		}

	    		//初始化Model表类对象
	    		$modelObj = new IModel("crowd_model");

	    		//删除众筹模型
				$result = $modelObj->del("id = ".$val);
	    	}
    	}
		$this->redirect('model_list');
    }
	/*
	 * @breif 后台添加给众筹规格
	 * */
	function search_spec()
	{
		$this->layout = '';
		$data = array();

		//获得model_id的值
		$model_id = IFilter::act(IReq::get('model_id'),'int');
		$crowd_id = IFilter::act(IReq::get('crowd_id'),'int');
		$specId   = '';

		if($crowd_id)
		{
			$tb_crowd = new IModel('crowd');
			$crowd_info = $tb_crowd->getObj('id = '.$crowd_id,'spec_array');
			$data['crowdSpec'] = JSON::decode($crowd_info['spec_array']);
			if($data['crowdSpec'])
			{
				foreach($data['crowdSpec'] as $item)
				{
					$specId .= $item['id'].',';
				}
			}
		}
		else if($model_id)
		{
			$modelObj  = new IModel('crowd_model');
			$modelInfo = $modelObj->getObj('id = '.$model_id,'spec_ids');
			$specId    = $modelInfo['spec_ids'] ? $modelInfo['spec_ids'] : '';
		}

		if($specId)
		{
			$specObj = new IModel('crowd_spec');
			$data['specData'] = $specObj->query('id in ('.trim($specId,',').')');
		}

		$this->setRenderData($data);
		$this->redirect("search_spec");
	}
	/**
	 * @breif 后台添加为每一件众筹添加会员价
	 * */
	function member_price()
	{
		$this->layout = '';

		$crowd_id   = IFilter::act(IReq::get('crowd_id'),'int');
		$project_id = IFilter::act(IReq::get('project_id'),'int');
		$sell_price = IFilter::act(IReq::get('sell_price'),'float');

		$date = array(
			'sell_price' => $sell_price
		);

		if($crowd_id)
		{
			$where  = 'crowd_id = '.$crowd_id;
			$where .= $project_id ? ' and project_id = '.$project_id : '';

			$priceRelationObject = new IModel('crowd_group_price');
			$priceData = $priceRelationObject->query($where);
			$date['price_relation'] = $priceData;
		}

		$this->setRenderData($date);
		$this->redirect('member_price');
	}
	/**
	 * @brief 众筹添加和修改视图
	 */
	public function crowd_edit()
	{
		$crowd_id = IFilter::act(IReq::get('id'),'int');

		//初始化数据
		$crowd_class = new crowd_class();

		//获取众筹分类列表
		$tb_category = new IModel('crowd_category');
		$this->category = $crowd_class->sortdata($tb_category->query(false,'*','sort','asc'),0,'--');

		//获取所有众筹扩展相关数据
		$data = $crowd_class->edit($crowd_id);

		if($crowd_id && !$data)
		{
			die("没有找到相关众筹！");
		}

		$this->setRenderData($data);
		$this->redirect('crowd_edit');
	}
	/**
	 * @brief 保存修改众筹信息
	 */
	function crowd_update()
	{
		$id       = IFilter::act(IReq::get('id'),'int');
		$callback = IFilter::act(IReq::get('callback'),'url');
		$callback = strpos($callback,'crowd/crowd_list') === false ? '' : $callback;

		//检查表单提交状态
		if(!$_POST)
		{
			die('请确认表单提交正确');
		}

		//初始化众筹数据
		unset($_POST['id']);
		unset($_POST['callback']);

		$crowdObject = new crowd_class();
		$crowdObject->update($id,$_POST);

		$callback ? $this->redirect($callback) : $this->redirect("crowd_list");
	}

	/**
	 * @brief 删除众筹
	 */
	function crowd_del()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'));

	    //生成crowd对象
	    $tb_crowd = new IModel('crowd');
	    $tb_crowd->setData(array('is_del'=>1));
	    if(!empty($id))
		{
			$tb_crowd->update(Util::joinStr($id));
		}
		else
		{
			Util::showMessage('请选择要删除的数据');
		}
		$this->redirect("crowd_list");
	}
	/**
	 * @brief 众筹上下架
	 */
	function crowd_stats()
	{
		//post数据
	    $id   = IFilter::act(IReq::get('id'));
	    $type = IFilter::act(IReq::get('type'));

	    //生成crowd对象
	    $tb_crowd = new IModel('crowd');
	    if($type == 'up')
	    {
	    	$updateData = array('is_del' => 0,'up_time' => ITime::getDateTime(),'down_time' => null);
	    }
	    else if($type == 'down')
	    {
	    	$updateData = array('is_del' => 2,'up_time' => null,'down_time' => ITime::getDateTime());
	    }
	    else if($type == 'check')
	    {
	    	$updateData = array('is_del' => 3,'up_time' => null,'down_time' => null);
	    }

	    $tb_crowd->setData($updateData);

	    if($id)
		{
			$tb_crowd->update(Util::joinStr($id));
		}
		else
		{
			Util::showMessage('请选择要操作的数据');
		}

		if(IClient::isAjax() == false)
		{
			$this->redirect("crowd_list");
		}
	}
	/**
	 * @brief 众筹彻底删除
	 * */
	function crowd_recycle_del()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'));

	    //生成crowd对象
	    $crowd = new crowd_class();
	    if($id)
		{
			if(is_array($id))
			{
				foreach($id as $key => $val)
				{
					$crowd->del($val);
				}
			}
			else
			{
				$crowd->del($id);
			}
		}

		$this->redirect("crowd_recycle_list");
	}
	/**
	 * @brief 众筹还原
	 * */
	function crowd_recycle_restore()
	{
		//post数据
	    $id = IFilter::act(IReq::get('id'));
	    //生成crowd对象
	    $tb_crowd = new IModel('crowd');
	    $tb_crowd->setData(array('is_del'=>0));
	    if($id)
		{
			$tb_crowd->update(Util::joinStr($id));
		}
		else
		{
			Util::showMessage('请选择要删除的数据');
		}
		$this->redirect("crowd_recycle_list");
	}

	//众筹导出 Excel
	public function crowd_report()
	{
		//搜索条件
		$search = IFilter::act(IReq::get('search'));
		//条件筛选处理
		list($join,$where) = crowd_class::getSearchCondition($search);
		//拼接sql
		$crowdHandle = new IQuery('crowd as go');
		$crowdHandle->order    = "go.sort asc,go.id desc";
		$crowdHandle->distinct = "go.id";
		$crowdHandle->fields   = "go.id, go.name,go.sell_price,go.store_nums,go.sale,go.is_del,go.create_time,seller.true_name";
		$crowdHandle->join     = $join;
		$crowdHandle->where    = $where;
		$crowdList = $crowdHandle->find();

		//构建 Excel table;
		$strTable ='<table width="500" border="1">';
		$strTable .= '<tr>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="*">众筹名称</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="160">分类</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">售价</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">库存</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">销量</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">发布时间</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">状态</td>';
		$strTable .= '<td style="text-align:center;font-size:12px;" width="60">隶属商户</td>';
		$strTable .= '</tr>';

		foreach($crowdList as $k=>$val){
			$strTable .= '<tr>';
			$strTable .= '<td style="text-align:center;font-size:12px;">&nbsp;'.$val['name'].'</td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.crowd_class::getCrowdCategory($val['id']).' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['sell_price'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['store_nums'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['sale'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['create_time'].' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.crowd_class::statusText($val['is_del']).' </td>';
			$strTable .= '<td style="text-align:left;font-size:12px;">'.$val['true_name'].'&nbsp;</td>';
			$strTable .= '</tr>';
		}
		$strTable .='</table>';
		unset($crowdList);
		$reportObj = new report();
		$reportObj->setFileName('crowd');
		$reportObj->toDownload($strTable);
		exit();
	}

	/**
	 * @brief 众筹分类添加、修改
	 */
	function category_edit()
	{
		$category_id = IFilter::act(IReq::get('cid'),'int');
		$parent_id = IFilter::act(IReq::get('pid'),'int');
		$this->data['category']['parent_id'] = $parent_id;

		//编辑众筹分类 读取众筹分类信息
		if($category_id)
		{
			$obj_category = new IModel('crowd_category');
			$category_info = $obj_category->getObj('id='.$category_id);
			if($category_info)
			{
				$this->data['category'] = $category_info;
			}
			else
			{
				$this->category_list();
				Util::showMessage("没有找到相关众筹分类！");
				return;
			}
		}
		//加载分类
		if(!isset($obj_category))
		{
			$obj_category = new IModel('crowd_category');
		}

		$crowd = new crowd_class();
		$this->data['all_category'] = $crowd->sortdata($obj_category->query(false,'*','sort','asc'),0,'--');
		$this->setRenderData($this->data);
		$this->redirect('category_edit');
	}

	/**
	 * @brief 保存众筹分类
	 */
	function category_save()
	{
		//获得post值
		$category_id = IFilter::act(IReq::get('category_id'),'int');
		$name = IFilter::act(IReq::get('name'));
		$parent_id = IFilter::act(IReq::get('parent_id'),'int');
		$visibility = IFilter::act(IReq::get('visibility'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');
		$title = IFilter::act(IReq::get('title'));
		$keywords = IFilter::act(IReq::get('keywords'));
		$descript = IFilter::act(IReq::get('descript'));

		if(!$name)
		{
			$this->category_list();
			exit;
		}

		$tb_category = new IModel('crowd_category');
		$category_info = array(
			'name'      => $name,
			'parent_id' => $parent_id,
			'sort'      => $sort,
			'visibility'=> $visibility,
			'keywords'  => $keywords,
			'descript'  => $descript,
			'title'     => $title
		);
		$tb_category->setData($category_info);
		if($category_id)									//保存修改分类信息
		{
			$where = "id=".$category_id;
			$tb_category->update($where);
		}
		else												//添加新众筹分类
		{
			$tb_category->add();
		}

		$this->category_list();
	}

	/**
	 * @brief 删除众筹分类
	 */
	function category_del()
	{
		$category_id = IFilter::act(IReq::get('cid'),'int');
		if($category_id)
		{
			$tb_category = new IModel('crowd_category');
			$catRow      = $tb_category->getObj('parent_id = '.$category_id);

			//要删除的分类下还有子节点
			if(!empty($catRow))
			{
				$this->category_list();
				Util::showMessage('无法删除此分类，此分类下还有子分类，或者回收站内还留有子分类');
				exit;
			}

			$tb_category_extend  = new IModel('crowd_category_extend');
			$cate_ext = $tb_category_extend->getObj('category_id = '.$category_id);

			//要删除的分类下还有众筹
			if(!empty($cate_ext))
			{
				$this->category_list();
				Util::showMessage('此分类下还有众筹,请先删除众筹！');
				exit;
			}

			if($tb_category->del('id = '.$category_id))
			{
				$this->category_list();
			}
			else
			{
				$this->category_list();
				$msg = "没有找到相关分类记录！";
				Util::showMessage($msg);
			}
		}
		else
		{
			$this->category_list();
			$msg = "没有找到相关分类记录！";
			Util::showMessage($msg);
		}
	}

	/**
	 * @brief 众筹分类列表
	 */
	function category_list()
	{
		$tb_category = new IModel('crowd_category');
		$crowd = new crowd_class();
		$this->data['category'] = $crowd->sortdata($tb_category->query(false,'*','sort','asc'));
		$this->setRenderData($this->data);
		$this->redirect('category_list',false);
	}

	//修改规格页面
	function spec_edit()
	{
		$this->layout = '';

		$id        = IFilter::act(IReq::get('id'));
		$seller_id = IFilter::act(IReq::get('seller_id'));

		$dataRow = array(
			'id'        => '',
			'name'      => '',
			'type'      => '',
			'value'     => '',
			'note'      => '',
			'seller_id' => $seller_id,
		);

		if($id)
		{
			$obj     = new IModel('crowd_spec');
			$dataRow = $obj->getObj("id = {$id}");
		}

		$this->setRenderData($dataRow);
		$this->redirect('spec_edit');
	}

	//增加或者修改规格
    function spec_update()
    {
    	$id         = IFilter::act(IReq::get('id'));
    	$name       = IFilter::act(IReq::get('name'));
    	$specType   = IFilter::act(IReq::get('type'));
    	$valueArray = IFilter::act(IReq::get('value'));
    	$note       = IFilter::act(IReq::get('note'));
    	$seller_id  = IFilter::act(IReq::get('seller_id'));

		//要插入的数据
    	if(is_array($valueArray) && isset($valueArray[0]) && $valueArray[0]!='')
    	{
    		$valueArray = array_unique($valueArray);
    		foreach($valueArray as $key => $rs)
    		{
    			if($rs=='')
    			{
    				unset($valueArray[$key]);
    			}
    		}

			if(!$valueArray)
			{
				$isPass = false;
				$errorMessage = "请上传规格图片";
			}
			else
			{
				$value = JSON::encode($valueArray);
			}
		}
    	else
    	{
    		$value = '';
    	}

    	$editData = array(
    		'id'        => $id,
    		'name'      => $name,
    		'value'     => $value,
    		'type'      => $specType,
    		'note'      => $note,
    		'seller_id' => $seller_id,
    	);

    	//校验
    	$isPass = true;
    	if($value=='')
    	{
    		$isPass = false;
    		$errorMessage = '规格值不能为空,请填写规格值或上传规格图片';
    	}

    	if($editData['name']=='')
    	{
    		$isPass = false;
    		$errorMessage = '规格名称不能为空';
    	}

    	if($isPass==false)
    	{
    		echo JSON::encode(array('flag' => 'fail','message' => $errorMessage));
    		exit;
    	}
    	else
    	{
    		$obj = new IModel('crowd_spec');

			//执行操作
	    	$obj->setData($editData);

	    	//更新修改
	    	if($id)
	    	{
	    		$where = 'id = '.$id;
	    		if($seller_id)
	    		{
	    			$where .= ' and seller_id = '.$seller_id;
	    		}
	    		$result = $obj->update($where);
	    	}
	    	//添加插入
	    	else
	    	{
	    		$result = $obj->add();
	    	}

			//执行状态
	    	if($result===false)
	    	{
    			echo JSON::encode(array('flag' => 'fail','message' => '数据库更新失败'));
	    	}
	    	else
	    	{
	    		//获取自动增加ID
	    		$editData['id'] = $id ? $id : $result;
	    		echo JSON::encode(array('flag' => 'success','data' => $editData));
	    	}
    	}
    }

	//批量删除规格
    function spec_del()
    {
    	$id = IFilter::act(IReq::get('id'));
		if($id)
		{
			$obj = new IModel('crowd_spec');
			$obj->setData(array('is_del'=>1));
			$obj->update(Util::joinStr($id));
			$this->redirect('spec_list');
		}
		else
		{
			$this->redirect('spec_list',false);
			Util::showMessage('请选择要删除的规格');
		}
    }
	//彻底批量删除规格
    function spec_recycle_del()
    {
    	$id = IFilter::act(IReq::get('id'),'int');
		if($id)
		{
			$obj = new IModel('crowd_spec');
			$obj->del(Util::joinStr($id));
			$this->redirect('spec_recycle_list');
		}
		else
		{
			$this->redirect('spec_recycle_list',false);
			Util::showMessage('请选择要删除的规格');
		}
    }
	//批量还原规格
    function spec_recycle_restore()
    {
    	$id = IReq::get('id');
		if(!empty($id))
		{
			$obj = new IModel('crowd_spec');
			$obj->setData(array('is_del'=>0));
			$obj->update(Util::joinStr($id));
			$this->redirect('spec_recycle_list');
		}
		else
		{
			$this->redirect('spec_recycle_list',false);
			Util::showMessage('请选择要还原的规格');
		}
    }
    //规格图片删除
    function spec_photo_del()
    {
    	$id = IReq::get('id','post');
    	if(isset($id[0]) && $id[0]!='')
    	{
    		$obj = new IModel('crowd_spec_photo');
    		$id_str = '';
    		foreach($id as $rs)
    		{
    			if($id_str!='')
    			{
    				$id_str.=',';
    			}
    				$id_str.=$rs;

    			$photoRow = $obj->getObj('id = '.$rs,'address');
    			if(file_exists($photoRow['address']))
    			{
    				unlink($photoRow['address']);
    			}
    		}

	    	$where = ' id in ('.$id_str.')';
	    	$obj->del($where);
	    	$this->redirect('spec_photo');
    	}
    	else
    	{
    		$this->redirect('spec_photo',false);
    		Util::showMessage('请选择要删除的id值');
    	}
    }

	/**
	 * @brief 分类排序
	 */
	function category_sort()
	{
		$category_id = IFilter::act(IReq::get('id'));
		$sort = IFilter::act(IReq::get('sort'));

		$flag = 0;
		if($category_id)
		{
			$tb_category = new IModel('crowd_category');
			$category_info = $tb_category->getObj('id='.$category_id);
			if(count($category_info)>0)
			{
				if($category_info['sort']!=$sort)
				{
					$tb_category->setData(array('sort'=>$sort));
					if($tb_category->update('id='.$category_id))
					{
						$flag = 1;
					}
				}
			}
		}
		echo $flag;
	}
	/**
	 * @brief 品牌分类排序
	 */
	public function brand_sort()
	{
		$brand_id = IFilter::act(IReq::get('id'));
		$sort = IFilter::act(IReq::get('sort'));
		$flag = 0;
		if($brand_id)
		{
			$tb_brand = new IModel('brand');
			$brand_info = $tb_brand->getObj('id='.$brand_id);
			if(count($brand_info)>0)
			{
				if($brand_info['sort']!=$sort)
				{
					$tb_brand->setData(array('sort'=>$sort));
					if($tb_brand->update('id='.$brand_id))
					{
						$flag = 1;
					}
				}
			}
		}
		echo $flag;
	}
	/**
	 * @brief import csv file
	 */
	public function csvImport()
	{
		$this->layout = '';
		$this->redirect('csvImport');
	}
	/**
	 * @brief csv file import
	 */
	public function importCsvFile()
	{
		csvimport_facade::run();
	}

	/**
	 * @brief web crowd collect
	 */
	public function collect_import()
	{
		$this->layout = '';
		$this->redirect('collect_import');
	}

	/**
	 * @brief 开始采集众筹信息
	 */
	public function collect_crowd()
	{
		$collect_name = IFilter::act(IReq::get('collect_name'));
		$url          = IFilter::act(IReq::get('url'));
		$num          = IFilter::act(IReq::get('num'),'int');

		if($url)
		{
			foreach($url as $key => $val)
			{
				if($val)
				{
					$result = collect_facade::run($collect_name,$val,$num);
					if($result['result'] == 'fail')
					{
						die($result['msg']);
					}
				}
			}
		}
		die('<script type="text/javascript">parent.artDialogCallback();</script>');
	}

	//采集众筹详情页面
	public function collect_crowd_detail()
	{
		$collectType = IFilter::act(IReq::get('collectType'));
		$collectUrl  = IFilter::act(IReq::get('collectUrl'),'url');
		$result      = collect_facade::runDetail($collectType,$collectUrl);
		if($result['result'] == 'success')
		{
			die(JSON::encode( array('result' => 'success','data' => $result['data']) ));
		}
		else
		{
			die(JSON::encode( array('result' => 'fail','msg' => $result['msg']) ));
		}
	}

	//修改排序
	public function ajax_sort()
	{
		$id   = IFilter::act(IReq::get('id'),'int');
		$sort = IFilter::act(IReq::get('sort'),'int');

		$crowdDB = new IModel('crowd');
		$crowdDB->setData(array('sort' => $sort));
		$crowdDB->update("id = {$id}");
	}

	//更新库存
	public function update_store()
	{
		$data     = IFilter::act(IReq::get('data'),'int'); //key => 众筹ID或货品ID ; value => 库存数量
		$crowd_id = IFilter::act(IReq::get('crowd_id'),'int');//存在即为货品
		$crowdSum = array_sum($data);

		if(!$data)
		{
			die(JSON::encode(array('result' => 'fail','data' => '众筹数据不存在')));
		}

		//货品方式
		if($crowd_id)
		{
			$projectDB = new IModel('projects');
			foreach($data as $key => $val)
			{
				$projectDB->setData(array('store_nums' => $val));
				$projectDB->update('id = '.$key);
			}
		}
		else
		{
			$crowd_id = key($data);
		}

		$crowdDB = new IModel('crowd');
		$crowdDB->setData(array('store_nums' => $crowdSum));
		$crowdDB->update('id = '.$crowd_id);

		die(JSON::encode(array('result' => 'success','data' => $crowdSum)));
	}

	//更新众筹价格
	public function update_price()
	{
		$data     = IFilter::act(IReq::get('data'),'float'); //key => 众筹ID或货品ID ; value => 库存数量
		$crowd_id = IFilter::act(IReq::get('crowd_id'),'int');//存在即为货品

		if(!$data)
		{
			die(JSON::encode(array('result' => 'fail','data' => '众筹数据不存在')));
		}

		//货品方式
		if($crowd_id)
		{
			$projectDB  = new IModel('projects');
			$updateData = current($data);
			foreach($data as $pid => $item)
			{
				$projectDB->setData($item);
				$projectDB->update("id = ".$pid);
			}
		}
		else
		{
			$crowd_id   = key($data);
			$updateData = current($data);
		}

		$crowdDB = new IModel('crowd');
		$crowdDB->setData($updateData);
		$crowdDB->update('id = '.$crowd_id);

		die(JSON::encode(array('result' => 'success','data' => number_format($updateData['sell_price'],2))));
	}

	//更新众筹推荐标签
	public function update_commend()
	{
		$data = IFilter::act(IReq::get('data'),'int'); //key => 众筹ID或货品ID ; value => commend值 1~4
		if(!$data)
		{
			die(JSON::encode(array('result' => 'fail','data' => '众筹数据不存在')));
		}

		$crowdCommendDB = new IModel('crowd_commend');

		//清理旧的commend数据
		$crowdIdArray = array_keys($data);
		$crowdCommendDB->del("crowd_id in (".join(',',$crowdIdArray).")");

		//插入新的commend数据
		foreach($data as $id => $commend)
		{
			foreach($commend as $k => $value)
			{
				$crowdCommendDB->setData(array('commend_id' => $value,'crowd_id' => $id));
				$crowdCommendDB->add();
			}
		}
		die(JSON::encode(array('result' => 'success')));
	}

	//众筹标签分词
	public function crowd_tags_words()
	{
		$content = IFilter::act(IReq::get('content'));
		$words   = words_facade::run($content);

		$result = array('result' => 'fail');

		if(isset($words['data']) && $words['data'])
		{
			$result = array(
				'result' => 'success',
				'data'   => join(",",$words['data']),
			);

		}
		die( JSON::encode($result) );
	}
}
