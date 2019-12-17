<?php
namespace app\admin\controller;

use think\View;
use think\Controller;
use think\Db;
use think\Session;
use app\admin\dxcommon\BaseApi;
use think\Log;
use app\admin\dxcommon\FTPUpload;
use app\admin\model\Affiliate as AffiliateModel;
//use app\admin\controller\Tool;

/**
 * 广告联盟相关业务
 *
 */
class Affiliate extends Action
{
	public function __construct(){
       Action::__construct();
       define('AFFILIATE_COMMISSION', 'affiliate_commission');//mysql数据表
       $this->Menu_logo();
    }
	/*
	 * banner管理
	 */
	public function banner()
	{
	    $ConfigName = 'AffiliateBannerSize';
	    $SysCofig = model("SysConfig")->getSysCofig($ConfigName);
	    $size1 = json_decode($SysCofig['ConfigValue'],true);
	    $Name = input("Name");
	    $Size = input("Size");
	    if(!empty($Name)){
	        $where['Name'] = ["like","$Name"];
        }
        if(!empty($Size)){
            $where['Size'] = (int)$Size;
        }
	    $where['IsDelete'] = (int)0;
        $page_size = input("page_size",20);
        $page = input("page",1);
	    $data = model("Affiliate")->getBanner($where,$page_size,$page);
	    $this->assign("data",$data);
        $this ->assign("size",$size1);
		return View();
	}


    //affiliate类别
    public function saveBanner(){
        if(request()->isPost()){
            $data['_id'] = input('_id/d');
            $data['Name'] = input('Name');
            $data['Size'] = input('Size/d');
            $data['Site'] = input('Site');
            $data['AlternateText'] = input('AlternateText');
            $data['BannerImg'] = input('BannerImg');
            $data['EndDate'] = strtotime(input('EndDate'));
            $data['StartDate'] = strtotime(input('StartDate'));
            $data['Language'] = input('Language');
            $data['Status'] = input('Status/d');
            $data['IsDelete'] = input("IsDelete/d",(int)0);
            if(empty($data['_id'])){
                unset($data['_id']);
                $data['AddTime'] = time();
                $data['AddAuthor'] = Session::get("username");
                $res = model("Affiliate")->saveBanner($data);
            }else{
                $data['UpdateAuthor'] = Session::get("username");
                $data['UpdateTime'] = time();
                $res = model("Affiliate")->saveBanner($data);
                $res = 1;
            }
            if($res){
                return array('code'=>200,'result'=>'操作成功');
            }else{
                return array('code'=>100,'result'=>'操作失败');
            }
        }else{
            $url = config("api_base_url")."share/header/langs";
            $langs = accessTokenToCurl($url,null,'',true);
            $ConfigName = 'AffiliateBannerSize';
            $SysCofig = model("SysConfig")->getSysCofig($ConfigName);
            $size = json_decode($SysCofig['ConfigValue'],true);
            $data['_id'] = input('banner_id');
            if(!empty($data['_id'])){
                $banner = model("Affiliate")->getBannerById($data['_id']);
                $this->assign("banner",$banner);
            }
            $this->assign('langs',$langs);
            $this->assign('size',$size);
            return view();
        }
    }

    /*
     * banner删除
     * */
    public function delBanner(){
        $data['_id'] = input('banner_id');
        $data['IsDelete'] = 1;
        $data['UpdateAuthor'] = Session::get("username");
        $data['UpdateTime'] = time();
        $res = model("Affiliate")->saveBanner($data);
        if($res){
            return array('code'=>200,'result'=>'操作成功');
        }else{
            return array('code'=>100,'result'=>'操作失败');
        }

    }

    /*
* 远程上传
* */
    public function remoteUpload(){
        //http://".config('ftp_config.DX_FTP_SERVER_ADDRESS').config('ftp_config.DX_FTP_ACCESS_PATH').'/'.
        $localres = localUpload();
        if($localres['code']==200){
            $remotePath = config("ftp_config.UPLOAD_DIR")['AFFILIATE_IMAGES'].date("Ymd");
            $config = [
                'dirPath'=>$remotePath, // ftp保存目录
                'romote_file'=>$localres['FileName'], // 保存文件的名称
                'local_file'=>$localres['url'], // 要上传的文件
            ];
            $ftp = new FTPUpload();
            $upload = $ftp->data_put($config);
            if($upload){
                unlink($localres['url']);
                $res['code'] = 200;
                $res['msg'] = "Success";
                $res['url'] = $remotePath.'/'.$localres['FileName'];
                $res['complete_url'] = DX_FTP_ACCESS_URL.'/'.$remotePath.'/'.$localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "Remote Upload Fail";
            }
            echo json_encode($res);
        }
    }

    /**
     * 按类别审核商品--获取数据
     * addby hengzhang 2018-04-21     *
     */
    public function checkProductByClass(){
    	$class_id=0;
    	$type =1;
    	$status =0;
    	$seller_id =0;
    	if(request()->isPost()){
    		$class_id= input('ClassName/d');
    		$seller_id = input('SellerID/d');
    		$type =input('type/d');
    		$status = input('status/d');

    		if(!empty($class_id) && $class_id >0){
    			$where['class_id'] = $class_id;
    		}
    		if(!empty($seller_id) && $seller_id >0){
    			$where['seller_id'] = $seller_id;
    		}
    		$where['type'] = $type;
    		$where['status'] = $status;
    	}else{
    		$where['status'] = 0;
    		$where['type'] =1;
    	}
    	//var_dump($status);
    	$tool = new Tool();
    	$mongodbClass = $tool -> getProductFirstClass();
    	$ClassNameOptionHtml = '';
    	foreach ($mongodbClass as $key => $value) {
    		$isSeleced = '';
    		if($value["id"] == $class_id){
    			$isSeleced = ' selected="selected" ';
    		}
    		$ClassNameOptionHtml .= '<option value="'.$value["id"].'" '.$isSeleced.'>'.$value["title_en"].'</option>';
    	}
    	$list = Db(AFFILIATE_COMMISSION)->where($where)->order('add_time desc')->paginate(20);
    	$list_data = $list->items();
    	$ids = array();
    	foreach ($list_data as $key => $value) {
    		if(!empty($value['class_id']) && $value['class_id'] != -99){ //-99 代表非已设置的类别id
    			array_push($ids,$value['class_id']);
    		}
    	}
    	$classNames = array();
    	if(!empty($ids)) {
    		$classNames = $tool -> getProductFirstClassByIDs($ids);
    	}
    	//print_r($classNames);
    	//die();
    	foreach ($list_data as $key => $value) {
    		#查找类别名称
    		foreach ($classNames as $ckey => $cvalue) {
    			if($cvalue['id'] == $value['class_id']){
    				$list_data[$key]['class_name'] = $cvalue['title_en'];
    			}
    		}
    		if(!isset($list_data[$key]['class_name'])){
    			$list_data[$key]['class_name'] ='无';
    		}
    		//数据类型: 1 默认佣金; 2 类别佣金;
    		$type_text = '';
    		switch($value['type']){
    			case 1:
    				$type_text = '默认佣金';
    				break;
    			case 2:
    				$type_text = '类别佣金';
    				break;
    		}
    		$list_data[$key]['type_text'] = $type_text;
    		//是否审核:0 待审核; 1 审核通过; 2 审核不通过;
    		$status_text = '';
    		switch($value['status']){
    			case 0:
    				$status_text = '待审核';
    				break;
    			case 1:
    				$status_text = '审核通过';
    				break;
    			case 2:
    				$status_text = '审核不通过';
    				break;
    		}
    		$list_data[$key]['status_text'] = $status_text;
    	}
    	$this->assign(['data'=>$list_data,'page' => $list->render(),'ClassNameOption'=>$ClassNameOptionHtml
    			       ,'type'=>$type,'status'=>$status]);
    	return view();
    }

    /**
     * 按类别审核商品--执行审核ok
     * addby hengzhang 2018-04-21     *
     */
    public function async_checkCommission_OK(){
	    if($data = request()->post()){
	          $data['id'] = explode(",", $data['id']);
	          $updateDate['status'] =1;
	          $updateDate['update_time'] = time();
	          $updateDate['operater_user'] = Session::get("username");
	          $result = Db(AFFILIATE_COMMISSION)
	          				->where('id','in',$data['id'])
	          				->update($updateDate);
	          //print_r($result);
	          if($result){
	          	   $resultList = Db(AFFILIATE_COMMISSION)
			          				->where('id','in',$data['id'])
			          				->where('status',1)
			          	            ->field('class_id','commission')
			          				->select();
	          	   if(!empty($resultList)){
	          	   	     $num=1;
	          	   	     $allData = count($resultList);
	          	   	     $errorMsg = '';
	          	   	     //情况特殊，此处使用遍历更新数据库·
	          	   	    foreach ($resultList as $key => $value){
	          	   	    	$productModel =new ProductManagement();
	          	   	    	$resultProduct = $productModel -> updateCommissionBySellerID($key, $value);
	          	   	    	if($resultProduct ===200){
	          	   	    		$num++;
	          	   	    	}else{
	          	   	    		$errorMsg .= ','.$resultProduct;
	          	   	    	}
	          	   	    }
	          	   	    if($num == $allData){
	          	   	    	echo json_encode(array('code'=>200,'result'=>'操作成功'));
	          	   	    	exit;
	          	   	    }else{
	          	   	    	//log::write('','','','');  //TODO 写日志
	          	   	    	echo json_encode(array('code'=>102,'result'=>'操作失败，系统异常，更新商城数据库部分操作失败,详情:'.$errorMsg));
	          	   	    	exit;
	          	   	    }
	          	   }else{
		          	   	echo json_encode(array('code'=>101,'result'=>'操作失败，数据异常，未查询到后台审核后的数据'));
		          	   	exit;
	          	   }
	          }else{
	              echo json_encode(array('code'=>100,'result'=>'操作失败，请联系管理员'));
	              exit;
	          }
	   }
    }

    /**
     * 按类别审核商品--执行审核不通过
     * addby hengzhang 2018-05-26     *
     */
    public function async_checkCommission_Fail(){
    	if($data = request()->post()){
    		$data['id'] = explode(",", $data['id']);
    		$updateDate['status'] =2;
    		$updateDate['update_time'] = time();
    		$updateDate['operater_user'] = Session::get("username");
    		$updateDate['remark'] = empty($data['reason'])? '系统批量审核':$data['reason'];
    		$result = Db(AFFILIATE_COMMISSION)
			    		->where('id','in',$data['id'])
			    		->update($updateDate);
    		if($result){
    			echo json_encode(array('code'=>200,'result'=>'操作成功'));
    			exit;
    		}else{
    			echo json_encode(array('code'=>100,'result'=>'操作失败，请联系管理员'));
    			exit;
    		}
    	}
    }
    /**
     * 审核主推商品--获取数据
     * addby hengzhang 2018-04-21     *
     */
    public function checkHotProduct(){
        $where['seller_id'] = input('seller_id');
        //数据类型:1 非主推产品; 2 主推产品;
        $where['type'] = 2;
        //一级分类ID
        $where['class_id'] = input('class_id');
        //审核状态:0 待审核; 1 审核通过; 2 审核不通过;
        $where['status'] = input('status',0);
        //分页大小
        $where['page_size'] = input('page_size', 10);

        $model = new AffiliateModel();
        $data = $model->getAffiliateProductList($where);
        //获取商品一级类别
        $tool = new Tool();
        $class_data = $tool->getProductFirstClass();
        if(!empty($where['seller_id']) || !empty($where['class_id']) || !empty($where['status'])){
           $page = 'status='.$where['status'].'&seller_id='.$where['class_id'].'&class_id='.$where['class_id'];
           $data['page'] = str_replace("page",$page.'&page',$data['page']);
        }
        // $class_data = str_replace(".png","_70x70.png",json_encode($class_data));
        $this->assign([
            'data'=>$data,
            'class_data'=>$class_data,
            'dx_mall_img_url'=>config('dx_mall_img_url'),
        ]);
    	return view();
    }
    /**
     * 审核主推商品--执行审核
     * addby hengzhang 2018-04-21     *
     */
    public function async_checkHotProduct(){
    	if(request()->isPost()){

    	}else{
    		return view('checkHotProduct');
    	}
    }

    /**
     * 批量更新联盟营销佣金产品状态
     * @return \think\response\Json
     */
    public function async_checkProduct(){
        if($data = request()->post()){
            $rtn = ['code'=>0,'result'=>''];
            $id_arr = explode(",", $data['id']);
            $status = $data['status'];
            $remark = $data['remark'];
            Log::record('async_checkProduct->'.print_r($id_arr, true));
            Log::record('async_checkProduct->'.print_r($status, true));
            $model = new AffiliateModel();
            if ($model->checkProductStatusByData(['id'=>$id_arr, 'status'=>$status, 'remark'=>$remark])){
                $rtn['code'] = 200;
                $rtn['result'] = '操作成功';
            }else{
                $rtn['result'] = '审核失败，请重试';
            }
            return json($rtn);
        }
    }

    /*
    * affiliate code管理页面
    */
    public function code(){
        $codeModel = new \app\admin\model\Affiliate();
        $where=array();
        $code = input("affiliate_code");
        if(!empty($code)){
            $where['_id'] = (int)$code;
        }
        $this->assign(['affiliate_code'=>$code]);
        $data = $codeModel->getCodeList($where);
        $this->assign(['Brand_list'=>$data,'page'=>$data['Page']]);
        return view('code');
    }

    /**
     * affiliate code
     * @return View
     */
    public function saveCode(){
        if(request()->isPost()){
            $codeModel = new \app\admin\model\Affiliate();
            $params = input();
            $data['_id'] = (int)$params['affiliate_code'];
            $data['Remark'] = $params['affiliate_remark'];
            $data['Html'] = $params['affiliate_html'];
            if(empty($params['id'])){
                $data['AddTime'] = time();
                $res = $codeModel->addAffiliate($data);
            }else{
                $res = $codeModel->updateAffiliate($data);
            }
            if($res){
                return array('code'=>200,'result'=>'操作成功');
            }else{
                return array('code'=>100,'result'=>'操作失败');
            }

        }else{
            $data['_id'] = input('code_id');
            if(!empty($data['_id'])){
                $code = model("Affiliate")->getCode($data['_id']);
                $code['Html'] = htmlspecialchars($code['Html']);
                $this->assign("content",$code);
            }
            return view();
        }
    }
}