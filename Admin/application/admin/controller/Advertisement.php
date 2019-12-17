<?php
namespace app\admin\controller;

use app\admin\dxcommon\AD;
use app\admin\model\AdvertisementManage;
use think\Log;

/**
 * 广告管理类
 * @author tinghu.liu
 * @date 2018-04-12
 * @package app\admin\controller
 */
class Advertisement extends Action
{
    public function __construct(){
        parent::__construct();
    }

    /**
     * 广告管理
     * @return \think\response\View
     */
    public function index(){

        $info = input();
        $model = new AdvertisementManage();
        $this->assign('info', $info);
        //站点信息
        $this->assign('site_data', AD::getSiteInfo(2));
        //页面信息
        $this->assign('page_data', $model->getPageData(['SiteID'=>(int)$info['SiteID']]));
        //区域信息
        $this->assign('area_data', $model->getRegionData(['PageID'=>(int)$info['PageID']]));
        //布局编号列表信息
        $this->assign('number_data', $model->getRegionLayoutData(['AreaID'=>(int)$info['AreaID']]));
        //列表信息
        $where = [];
        if (!empty($info['SiteID'])){
            $where['SiteID'] = (int)$info['SiteID'];
        }
        if (!empty($info['PageID'])){
            $where['PageID'] = (int)$info['PageID'];
        }
        if (!empty($info['AreaID'])){
            $where['AreaID'] = (int)$info['AreaID'];
        }
        if (!empty($info['AreasLayoutID'])){
            $where['AreasLayoutID'] = (int)$info['AreasLayoutID'];
        }
        $page_size = config('paginate.list_rows');
        $list = $model->getActivityDataPaginate($where,$page_size);
        $this->assign('list', $list);
        //站点信息
        $this->assign('site_data', AD::getSiteInfo(2));
        //广告类型
        $this->assign('type_data', AD::getADContentType());
        //相关url
        $this->assign('url', json_encode([
            'get_page_url'=>url('Advertisement/m_getPages'),
            'get_region_url'=>url('Advertisement/m_getRegion'),
            'get_region_number_url'=>url('Advertisement/m_getRegionNumber'),
            'add_url'=>url('Advertisement/add'),
            'editor_url'=>url('Advertisement/editor'),
        ]));
        $this->assign('editor_url',url('Advertisement/editor'));
        return view();
    }

    /**
     * 增加广告
     * @return \think\response\View
     */
    public function add(){
        $info = input();
        $model = new AdvertisementManage();
        $this->assign('info', $info);
        //站点信息
        $this->assign('site_data', AD::getSiteInfo(2));
        //页面信息
        $this->assign('page_data', $model->getPageData(['SiteID'=>(int)$info['SiteID']]));
        //区域信息
        $this->assign('area_data', $model->getRegionData(['PageID'=>(int)$info['PageID']]));
        //布局编号列表信息
        $this->assign('number_data', $model->getRegionLayoutData(['AreaID'=>(int)$info['AreaID']]));
        //布局编号详细信息
        $number_detail = $model->getRegionLayoutData(['_id'=>(int)$info['AreasLayoutID']])[0];
        $number_detail['IsMoreImage_str'] = '否';
        $is_more_img = 0;
        if ($number_detail['IsMoreImage']){
            $number_detail['IsMoreImage_str'] = '是';
            $is_more_img = 1;
        }
        $type_info = $model->getContentTypeData(['ContentTypeID'=>$number_detail['ContentTypeID']])[0];
        $number_detail['ContentTypeName'] = $type_info['ContentTypeName'];
        $this->assign('number_detail', $number_detail);
        $this->assign('is_more_img', $is_more_img);
        //相关url
        $this->assign('url', json_encode([
            'get_page_url'=>url('Advertisement/m_getPages'),
            'get_region_url'=>url('Advertisement/m_getRegion'),
            'get_region_number_url'=>url('Advertisement/m_getRegionNumber'),
            'save_activity_url'=>url('Advertisement/m_saveActivity'),
        ]));
        return view();
    }

    /**
     * 修改广告
     * @return \think\response\View
     */
    public function editor(){
        $activity_id = input('id/d');
        $info = input();
        $model = new AdvertisementManage();
        $this->assign('info', $info);

        //广告详情
        $activity_info = $model->getActivityData(['_id'=>(int)$activity_id])[0];

        $this->assign('activity_info', $activity_info);
//        print_r($activity_info);

        //站点信息
        $this->assign('site_data', AD::getSiteInfo(2));
        //页面信息
        $this->assign('page_data', $model->getPageData(['SiteID'=>(int)$info['SiteID']]));
        //区域信息
        $this->assign('area_data', $model->getRegionData(['PageID'=>(int)$info['PageID']]));
        //布局编号列表信息
        $this->assign('number_data', $model->getRegionLayoutData(['AreaID'=>(int)$info['AreaID']]));
        //布局编号详细信息
        $number_detail = $model->getRegionLayoutData(['_id'=>(int)$info['AreasLayoutID']])[0];
        $number_detail['IsMoreImage_str'] = '否';
        $is_more_img = 0;
        if ($number_detail['IsMoreImage']){
            $number_detail['IsMoreImage_str'] = '是';
            $is_more_img = 1;
        }
        $type_info = $model->getContentTypeData(['ContentTypeID'=>$number_detail['ContentTypeID']])[0];
        $number_detail['ContentTypeName'] = $type_info['ContentTypeName'];
        $this->assign('number_detail', $number_detail);
        $this->assign('is_more_img', $is_more_img);
        //相关url
        $this->assign('url', json_encode([
            'get_page_url'=>url('Advertisement/m_getPages'),
            'get_region_url'=>url('Advertisement/m_getRegion'),
            'get_region_number_url'=>url('Advertisement/m_getRegionNumber'),
            'save_activity_url'=>url('Advertisement/m_editorActivity'),
        ]));

        $this->assign('activity_id', $activity_id);



        return view();
    }

    /**
     * 站点管理【暂时不做】
     * @return \think\response\View
     */
    public function site()
    {
        return view();
    }

    /**
     * 页面管理
     * @return \think\response\View
     */
    public function page()
    {
        $model = new AdvertisementManage();
        $page_size = config('paginate.list_rows');
        $list = $model->getPageDataPaginate($page_size);
        $this->assign('list', $list);
        $this->assign('url', json_encode([
            'save_url'=>url('Advertisement/m_savePages'),
            'update_url'=>url('Advertisement/m_updatePages'),
        ]));
        return view();
    }

    /**
     * 区域管理
     * @return \think\response\View
     */
    public function region()
    {
        $model = new AdvertisementManage();
        $page_size = config('paginate.list_rows');
        $list = $model->getRegionDataPaginate($page_size);
        $page_data = AD::getPagesData();
        $this->assign('list', $list);
        $this->assign('page_data', $page_data);
        $this->assign('url', json_encode([
            'save_url'=>url('Advertisement/m_saveRegion'),
            'update_url'=>url('Advertisement/m_updateRegion'),
        ]));
        return view();
    }

    /**
     * 区域编号管理
     * @return \think\response\View
     */
    public function regionNumber(){
        $model = new AdvertisementManage();
        $page_size = config('paginate.list_rows');
        $list = $model->getRegionLayoutDataPaginate($page_size);
        $this->assign('list', $list);
        //站点信息
        $this->assign('site_data', AD::getSiteInfo(2));
        //广告类型
        $this->assign('type_data', AD::getADContentType());
        $this->assign('editor_url', url('Advertisement/editorRegionNumber'));
        $this->assign('url', json_encode([
            'save_url'=>url('Advertisement/m_saveRegionNumber'),
            'get_page_url'=>url('Advertisement/m_getPages'),
            'get_region_url'=>url('Advertisement/m_getRegion'),
        ]));

        return view();
    }

    /**
     * 区域编号编辑页面
     * @return \think\response\View
     */
    public function editorRegionNumber(){
        $model = new AdvertisementManage();
        $info = $model->getRegionLayoutData(['_id'=>(int)input('id')])[0];
        $this->assign('info', $info);
        $this->assign('page_data', $model->getPageData(['SiteID'=>(int)$info['SiteID']]));
        $this->assign('area_data', $model->getRegionData(['PageID'=>(int)$info['PageID']]));
        //站点信息
        $this->assign('site_data', AD::getSiteInfo(2));
        //广告类型
        $this->assign('type_data', AD::getADContentType());
        $this->assign('url', json_encode([
            'save_url'=>url('Advertisement/m_editorRegionNumber'),
            'get_page_url'=>url('Advertisement/m_getPages'),
            'get_region_url'=>url('Advertisement/m_getRegion'),
        ]));
        return view();
    }

    /**
     * 广告类型管理【暂时不做】
     * @return \think\response\View
     */
    public function adType(){

        return view();
    }

    /**
     * 保存页面数据
     * @return mixed
     */
    public function m_savePages(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增失败';
        $get_data = input();
        if (!empty($get_data) && !empty($get_data['SiteName']) && !empty($get_data['SiteID']) && !empty($get_data['PageName']) && !empty($get_data['Domain'])){
            $page_name = $get_data['PageName'];
            $data = [
                "CreateBy"=>session('username'),
                "CreateTime"=>time(),
                "UpdateBy"=>'',
                "UpdateTime"=>'',
                "SiteID"=>(int)$get_data['SiteID'],
                "SiteName"=>$get_data['SiteName'],
                "PageName"=>$page_name,
                "Domain"=>$get_data['Domain']
            ];
            $model = new AdvertisementManage();
            $page_data = $model->getPageData(['PageName'=>$page_name]);
            if(empty($page_data)){
                if ($model->insertPagesData($data)){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '新增失败，请重试';
                }
            }else{
                $rtn['msg'] = '页面已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 修改页面数据
     * @return mixed
     */
    public function m_updatePages(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '修改失败';
        $get_data = input();
        if (!empty($get_data) && !empty($get_data['_id']) && !empty($get_data['SiteName']) && !empty($get_data['SiteID']) && !empty($get_data['PageName']) && !empty($get_data['Domain'])){
            $page_name = $get_data['PageName'];
            $_id = (int)$get_data['_id'];
            $where = ['_id'=>$_id];
            $update_data = [
                "UpdateBy"=>session('username'),
                "UpdateTime"=>time(),
                "SiteID"=>(int)$get_data['SiteID'],
                "SiteName"=>$get_data['SiteName'],
                "PageName"=>$page_name,
                "Domain"=>$get_data['Domain']
            ];
            $model = new AdvertisementManage();
            $page_data = $model->getPageData(['PageName'=>$page_name, '_id'=>['<>',$_id]]);
            if(empty($page_data)){
                $res = $model->updatePagesData($where, $update_data);
                if ($res){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '更新失败，请重试';
                }
            }else{
                $rtn['msg'] = '页面已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 新增页面区域
     * @return \think\response\Json
     */
    public function m_saveRegion(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增失败';
        $get_data = input();
        if (
            !empty($get_data)
            && !empty($get_data['SiteID'])
            && !empty($get_data['PageID'])
            && !empty($get_data['AreaName'])
        ){
            $area_name = $get_data['AreaName'];
            $page_id = (int)$get_data['PageID'];
            $data = [
                "CreateBy"=>session('username'),
                "CreateTime"=>time(),
                "UpdateBy"=>'',
                "UpdateTime"=>'',
                "AreaName"=>$area_name,
                "SiteID"=>(int)$get_data['SiteID'],
                "PageID"=>$page_id
            ];
            $model = new AdvertisementManage();
            $region_data = $model->getRegionData(['AreaName'=>$area_name, "PageID"=>$page_id]);
            if(empty($region_data)){
                if ($model->insertRegionData($data)){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '新增失败，请重试';
                }
            }else{
                $rtn['msg'] = '该页面下的区域已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 修改页面区域
     * @return mixed
     */
    public function m_updateRegion(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '修改失败';
        $get_data = input();
        if (!empty($get_data) && !empty($get_data['_id']) && !empty($get_data['SiteID']) && !empty($get_data['PageID']) && !empty($get_data['AreaName'])){
            $area_name = $get_data['AreaName'];
            $page_id = (int)$get_data['PageID'];
            $_id = (int)$get_data['_id'];
            $where = ['_id'=>$_id];
            $update_data = [
                "UpdateBy"=>session('username'),
                "UpdateTime"=>time(),
                "SiteID"=>(int)$get_data['SiteID'],
                "PageID"=>$page_id,
                "AreaName"=>$area_name
            ];
            $model = new AdvertisementManage();
            $page_data = $model->getRegionData(['AreaName'=>$area_name, "PageID"=>$page_id, "_id"=>['<>',$_id]]);
            if(empty($page_data)){
                $res = $model->updateRegionData($where, $update_data);
                if ($res){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '更新失败，请重试';
                }
            }else{
                $rtn['msg'] = '该页面下的区域已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 根据站点ID获取页面数据
     * @return \think\response\Json
     */
    public function m_getPages(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $get_data = input();
        if (!empty($get_data) && !empty($get_data['SiteID'])){
            $model = new AdvertisementManage();
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $model->getPageData(['SiteID'=>(int)$get_data['SiteID']]);
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 根据页面ID获取页面区域数据
     * @return \think\response\Json
     */
    public function m_getRegion(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $get_data = input();
        if (!empty($get_data) && !empty($get_data['PageID'])){
            $model = new AdvertisementManage();
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $model->getRegionData(['PageID'=>(int)$get_data['PageID']]);
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 根据区域ID获取对应区域编号
     * @return \think\response\Json
     */
    public function m_getRegionNumber(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '获取数据失败';
        $get_data = input();
        if (!empty($get_data) && !empty($get_data['AreaID'])){
            $model = new AdvertisementManage();
            $rtn['code'] = 0;
            $rtn['msg'] = 'success';
            $rtn['data'] = $model->getRegionLayoutData(['AreaID'=>(int)$get_data['AreaID']]);
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 增加区域编码数据
     * @return \think\response\Json
     */
    public function m_saveRegionNumber(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '新增失败';
        $get_data = input();
        if (
            !empty($get_data['SiteID'])
            && !empty($get_data['PageID'])
            && !empty($get_data['AreaID'])
            && !empty($get_data['AreasLayoutName'])
            && !empty($get_data['ContentTypeID'])
        ){
            $IsMoreImage = false;
            if (!empty($get_data['IsMoreImage']) && $get_data['IsMoreImage'] == 1){
                $IsMoreImage = true;
            }
            $model = new AdvertisementManage();
            $data = [
                'CreateBy'=>session('username'),
                'CreateTime'=>time(),
                "UpdateBy"=>'',
                "UpdateTime"=>'',
                'SiteID'=>(int)$get_data['SiteID'],
                'PageID'=>(int)$get_data['PageID'],
                'AreaID'=>(int)$get_data['AreaID'],
                'ContentTypeID'=>(int)$get_data['ContentTypeID'],
                'AreasLayoutName'=>$get_data['AreasLayoutName'],
                'IsMoreImage'=>$IsMoreImage,
            ];
            if (empty($model->getRegionLayoutData([
                'SiteID'=>(int)$get_data['SiteID'],
                'PageID'=>(int)$get_data['PageID'],
                'AreaID'=>(int)$get_data['AreaID'],
                'ContentTypeID'=>(int)$get_data['ContentTypeID'],
                'AreasLayoutName'=>$get_data['AreasLayoutName'],
            ]))){
                if ($model->insertRegionLayoutData($data)){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                }else{
                    $rtn['msg'] = '新增失败，请重试';
                }
            }else{
                $rtn['msg'] = '区域编码已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 修改区域编码数据
     * @return \think\response\Json
     */
    public function m_editorRegionNumber(){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '修改失败';
        $get_data = input();
        if (
            !empty($get_data['id'])
            && !empty($get_data['SiteID'])
            && !empty($get_data['PageID'])
            && !empty($get_data['AreaID'])
            && !empty($get_data['AreasLayoutName'])
            && !empty($get_data['ContentTypeID'])
        ){
            $id = (int)$get_data['id'];
            $IsMoreImage = false;
            if (!empty($get_data['IsMoreImage']) && $get_data['IsMoreImage'] == 1){
                $IsMoreImage = true;
            }
            $model = new AdvertisementManage();
            $data = [
                "UpdateBy"=>session('username'),
                "UpdateTime"=>time(),
                'SiteID'=>(int)$get_data['SiteID'],
                'PageID'=>(int)$get_data['PageID'],
                'AreaID'=>(int)$get_data['AreaID'],
                'ContentTypeID'=>(int)$get_data['ContentTypeID'],
                'AreasLayoutName'=>$get_data['AreasLayoutName'],
                'IsMoreImage'=>$IsMoreImage,
            ];
            if (empty($model->getRegionLayoutData([
                '_id'=>['<>', $id],
                'SiteID'=>(int)$get_data['SiteID'],
                'PageID'=>(int)$get_data['PageID'],
                'AreaID'=>(int)$get_data['AreaID'],
                'ContentTypeID'=>(int)$get_data['ContentTypeID'],
                'AreasLayoutName'=>$get_data['AreasLayoutName'],
            ]))){
                if ($model->updateRegionLayoutData(['_id'=>$id],$data)){
                    $rtn['code'] = 0;
                    $rtn['msg'] = 'success';
                    $rtn['url'] = url('Advertisement/regionNumber');
                }else{
                    $rtn['msg'] = '修改失败，请重试';
                }
            }else{
                $rtn['msg'] = '区域编码已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return json($rtn);
    }

    /**
     * 保存广告信息
     * @return mixed
     */
    public function m_saveActivity($data_array = array()){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        if(!empty($data_array)){
              $get_data = $data_array;
        }else{
              $get_data = json_decode(htmlspecialchars_decode(input('post.data')), true);
        }
        if (
            !empty($get_data['SiteID'])
            && !empty($get_data['PageID'])
            && !empty($get_data['AreaID'])
            && !empty($get_data['AreasLayoutID'])
            && !empty($get_data['ContentTypeID'])
            && !empty($get_data['Key'])
            && AD::judgeActivityParam($get_data)
        ){
            $model = new AdvertisementManage();
            $key = $get_data['Key'];
            $activity_data = $model->getActivityData(['Key'=>$key]);
            if (empty($activity_data)){
                $get_data['CreateBy'] = session('username');
                $get_data['CreateTime'] = time();
                $get_data['UpdateBy'] = '';
                $get_data['UpdateTime'] = '';
                if ($model->insertActivityData($get_data)){
                    $rtn['code'] = 0;
                    $rtn['msg'] = '添加成功';
                    $rtn['url'] = url('Advertisement/index');
                }else{
                    $rtn['msg'] = '新增失败';
                }
            }else{
                $rtn['msg'] = '系统编码已存在';
            }
        }else{
            $rtn['msg'] = '缺少必传参数或参数错误';
        }
        return $rtn;
    }

    /**
     * 修改广告信息
     * @return mixed
     */
    public function m_editorActivity($data_array = array()){
        $rtn = config('ajax_return_data');
        $rtn['msg'] = '操作失败';
        if(!empty($data_array)){
              $get_data = $data_array;
        }else{
              $get_data = json_decode(htmlspecialchars_decode(input('post.data')), true);
        }
        // $get_data = json_decode(htmlspecialchars_decode(input('post.data')), true);
        Log::record('修改广告信息'.print_r($get_data, true));
        if (
            !empty($get_data['id'])
            &&!empty($get_data['SiteID'])
            && !empty($get_data['PageID'])
            && !empty($get_data['AreaID'])
            && !empty($get_data['AreasLayoutID'])
            && !empty($get_data['ContentTypeID'])
            && AD::judgeActivityParam($get_data)
        ){
            //数据拼装
            $where = ['_id'=>(int)$get_data['id']];
            unset($get_data['id']);
            //指定ID类型为int
            $get_data['SiteID'] = (int)$get_data['SiteID'];
            $get_data['PageID'] = (int)$get_data['PageID'];
            $get_data['AreaID'] = (int)$get_data['AreaID'];
            $get_data['AreasLayoutID'] = (int)$get_data['AreasLayoutID'];
            $get_data['ContentTypeID'] = (int)$get_data['ContentTypeID'];
            $get_data['UpdateBy'] = session('username');
            $get_data['UpdateTime'] = time();
            $model = new AdvertisementManage();
            if ($model->updateActivityData($where, $get_data)){
                $rtn['code'] = 0;
                $rtn['msg'] = '更新成功';
                $rtn['url'] = url('Advertisement/index');
            }else{
                $rtn['msg'] = '更新失败或无数据更新';
            }
        }else{
            $rtn['msg'] = '缺少必传参数';
        }
        return $rtn;
    }
    /**
    * 广告信息导入
    * @return mixed
    * @author: Wang addtime 2019-03-13
    */
    public function import_ads(){
        // echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>';
        vendor("PHPExcel.PHPExcel");
        $data = [];
        $objPHPExcel = new \PHPExcel();
        //获取表单上传文件
        $file = $_FILES['excel'];
        if($file && empty($file['error'])){
            $Filename = explode('.', $file["name"]);
            if(strtolower($Filename[count($Filename) - 1] != 'xlsx') && strtolower($Filename[count($Filename) - 1] != 'xls')){
                  $resultMsg = '请使用xlsx格式的文件导入;';
                  exit;
            }
            $objReader = \PHPExcel_IOFactory::createReader('Excel2007');
            $objPHPExcel = $objReader->load($file["tmp_name"],'utf-8');
            $sheet = $objPHPExcel->getSheet(0)->toArray();

            if(empty($sheet[1][0]) && empty($sheet[1][1]) && empty($sheet[1][2]) && empty($sheet[1][3]) && empty($sheet[1][4]) && empty($sheet[1][5])){
                 echo json_encode(array('code'=>100,'result'=>'第二行除了第一个外其他都是必填项')); exit;
            }
            $model = new AdvertisementManage();
            $data['SiteID'] = $sheet[1][1];
            $data['PageID'] = $sheet[1][2];
            $data['AreaID'] = $sheet[1][3];
            $data['AreasLayoutID'] = $sheet[1][4];
            $data['Key'] = $sheet[1][5];
            //布局编号详细信息
            $number_detail = $model->getRegionLayoutData(['_id'=>(int)$data['AreasLayoutID']])[0];
            if(empty($number_detail)){
                echo json_encode(array('code'=>100,'result'=>'获取不到布局信息')); exit;
            }
            $id = $sheet[1][0];
            $data['ContentTypeID'] = $number_detail["ContentTypeID"];
            unset($sheet[0],$sheet[1],$sheet[2]);
            if($number_detail["ContentTypeID"] == 1){
                $data["Banners"]["IsMoreImage"] = true;
                $data["Banners"]["BannerImages"]["IsContainsFont"] = true;
                $data["Banners"]["BannerImages"]["BannerFonts"] = $this->FormatCombination($sheet,$number_detail["ContentTypeID"]);
                $data["SKUs"] = array();
                $data["Keyworks"] = array();
            }else if($number_detail["ContentTypeID"] == 2){
                $data["Banners"] = array();
                $data["SKUs"] = array();
                $data["Keyworks"]["TextData"] = $this->FormatCombination($sheet,$number_detail["ContentTypeID"]);
            }else if($number_detail["ContentTypeID"] == 3){
                $data["Banners"] = array();
                $data["SKUs"]["SKUData"] = $this->FormatCombination($sheet,$number_detail["ContentTypeID"]);
                $data["Keyworks"] = array();
            }
            if(empty($data)){
                echo json_encode(array('code'=>100,'result'=>'不能导入空数据')); exit;
            }
            if(!empty($id)){
                $data['id'] = $id;
                $result = $this->m_editorActivity($data);
            }else{
                $result = $this->m_saveActivity($data);
            }
            if(isset($result['code']) && $result['code'] == 0){
                  echo json_encode(array('code'=>200,'result'=>'数据编辑成功')); exit;
            }else{
                  echo json_encode(array('code'=>100,'result'=>$result)); exit;
            }
        }else{
            return view();
        }
    }
    /**
     * 对导入信息进行格式组合
     * [FormatCombination description]
     * @author: Wang addtime 2019-03-13
     */
    public function FormatCombination($data = array(),$ContentTypeID=''){
         $data_array = [];
         $data_str = [];
         if($ContentTypeID == 1){
              foreach ($data as $k => $v) {
                 if(!empty($v[0])){
                     $data_array[$v[0]]["Language"] = strtolower($v[0]);
                     $data_array[$v[0]]["SKU"] = null;
                     $data_array[$v[0]]["ImageUrl"][] = $v[1]?$v[1]:'';
                     $data_array[$v[0]]["LinkUrl"][] = $v[2]?$v[2]:'';
                     $data_array[$v[0]]["MainText"][0] = $v[3]?$v[3]:'';
                     $data_array[$v[0]]["SubText"][0] = $v[4]?$v[4]:'';
                 }
              }
              if(!empty($data_array)){
                    foreach ($data_array as $ke=> $ve) {
                      $data_str[] = $ve;
                    }
              }
              return $data_str;
         }else if($ContentTypeID == 2){
              foreach ($data as $k => $v) {
                 if(!empty($v[0])){
                     $data_array[] = ["Language"=>strtolower($v[0]),"Value"=>$v[1]?$v[1]:''];
                 }
              }
         }else if($ContentTypeID == 3){
             foreach ($data as $k => $v) {
                 if(!empty($v[0])){
                      $data_array[] = ["Language"=>strtolower($v[0]),"SKU"=>$v[1]?(string)$v[1]:'',"LinkUrl"=>$v[2]?$v[2]:'',"MainText"=>$v[3]?$v[3]:'',"SubText"=>$v[4]?$v[4]:''];
                 }
             }
         }

         return $data_array;

    }







}
