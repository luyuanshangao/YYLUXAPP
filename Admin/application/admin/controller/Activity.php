<?php
namespace app\admin\controller;

use app\admin\model\EDMActivityModel;
use app\common\helpers\CommonLib;
use think\Exception;
use think\View;
use think\Controller;
use think\Db;
use think\Session;
use think\Cookie;
use app\admin\dxcommon\FTPUpload;
use app\admin\dxcommon\BaseApi;
use think\Log;


class Activity extends Action
{
	public function __construct(){
       Action::__construct();
    }

    /* 活动列表页 */
	public function index()
	{
        $params = input();
        $model = new EDMActivityModel();
        $this->assign('lang_data', $model::$langCode);
        //列表信息
        $where = [];
        if (!empty($params['title'])){
            $where['ActivityTitle'] = array('like', '%' . $params['title'] . '%');;
        }
        $page_size = config('paginate.list_rows');
        $list = (new EDMActivityModel())->getActivityDataPaginate($where,$page_size,['title' => isset($params['title']) ? $params['title'] : '']);
        $this->assign('list', $list);
        $this->assign('params', $params);
        $this->assign('live_url', '//www.dx.com/lp/');
        return view();
    }

    /* 活动编辑页 */
    public function saveActivity(){
        $input = input();
        $model = new EDMActivityModel();
        //语种
        $this->assign('lang_data', $model::$langCode);
        //币种
        $this->assign('currency_data', $model::$currencyCode);
        $this->assign('admin_edm_url_config', config('admin_edm_url_config'));
        //是否编辑
        if(!empty($input['id'])){
            $activity = $model->getEdmActivity($input['id']);
            if(!empty($activity['TemplateID'])){
                $temp = $model->getEdmActivityTemplate($activity['TemplateID']);
                $activity['temp_url'] = config('admin_edm_url_config').$temp['PreviewImage'];
                $activity['temp_title'] = $temp['Title'];
            }
            if(!empty($activity['Banners'])){
                $activity['Banners'] = json_decode($activity['Banners'],true);
            }
            $template = $this->templateList($activity['LangCode']);
            $this->assign('template', $template);
            $this->assign('activity', $activity);
        }
        if(!empty($input['data'])){
            $params = $input['data'];

            $data['ActivityTitle'] = !empty($params['TaskTitle']) ?  $params['TaskTitle'] : '';
            $data['PageTitle'] = !empty($params['PageTitle']) ?  $params['PageTitle'] : '';
            $data['CurrencyCode'] = !empty($params['CurrencyCode']) ?  $params['CurrencyCode'] : '';
            $data['LangCode'] = !empty($params['LangCode']) ?  $params['LangCode'] : '';
            $data['Banner'] = !empty($params['Banner']) ?  $params['Banner'] : '';
            $data['BannerUrl'] = !empty($params['BannerUrl']) ?  $params['BannerUrl'] : '';
            $data['Banner2nd'] = !empty($params['Banner2nd']) ?  $params['Banner2nd'] : '';
            $data['Banner2Url'] = !empty($params['Banner2Url']) ?  $params['Banner2Url'] : '';
            $data['SPUText'] = !empty($params['SKUText']) ?  $params['SKUText'] : '';
            $data['OtherSPUText'] = !empty($params['OtherSKUText']) ?  $params['OtherSKUText'] : '';
            $data['TemplateID'] = !empty($params['TemplateID']) ?  $params['TemplateID'] : '';
            $data['GACode'] = !empty($params['GACode']) ?  $params['GACode'] : '';
            $data['Remark'] = !empty($params['Remark']) ?  $params['Remark'] : '';
            $data['Enabled'] = !empty($params['Status']) ?  $params['Status'] : 2;
            $data['UrlSnippet'] = !empty($params['UrlSnippet']) ?  $params['UrlSnippet'] : '';
            $data['EmailSubject'] = !empty($params['EmailTitle']) ?  $params['EmailTitle'] : '';
            if(!empty($params['Banners'])){
                $data['Banners'] = json_encode($params['Banners']);
            }
            if(!empty($params['id'])){
                $data['IsHistory'] = 0;
                $ret = $model->updateEdmActivity($params['id'],$data);
//                $this->viewSource($params['id']);die;
                return json(['code' => 200,'msg' => '修改成功','url'=> '/activity/index']);
            }else{
                $ret = $model->createEdmActivity($data);
                if($ret > 0){
//                    $this->viewSource($ret);
                    return json(['code' => 200,'msg' => '新增成功','url'=> '/activity/index']);
                }
            }
            return json(['code' => 1001,'msg' => '操作失败']);
        }
        return view();
    }


     /* 邮件模板列表页 */
	public function templateIndex()
	{
        $params = input();
        $model = new EDMActivityModel();
        $this->assign('lang_data', $model::$langCode);
        $this->assign('template_type', $model::$templateType);
        $this->assign('img_url',config('admin_edm_url_config'));

        //列表信息
        $where = [];
        if (!empty($params['title'])){
            $where['Title'] = array('like', '%' . $params['title'] . '%');;
        }
        $page_size = config('paginate.list_rows');
        $list = (new EDMActivityModel())->getActivityTemplatePaginate($where,$page_size);
        $this->assign('params', $params);
        $this->assign('list', $list);
        return view();
    }


    /**
     * 编辑邮件模板页
     * @return View
     */
    public function saveTemplate(){
        $input = input();
        $model = new EDMActivityModel();
        $this->assign('lang_data', $model::$langCode);
        $this->assign('template_type', $model::$templateType);
        $this->assign('admin_edm_url_config', config('admin_edm_url_config'));
        //是否编辑
        if(!empty($input['id'])){
            $activity = $model->getEdmActivityTemplate($input['id']);
            $this->assign('activity', $activity);
        }
        if(!empty($input['data'])){
            $params = $input['data'];
            $data['Title'] = !empty($params['Title']) ?  $params['Title'] : '';
            $data['TemplateType'] = !empty($params['TemplateType']) ?  $params['TemplateType'] : '';
            $data['PreviewImage'] = !empty($params['Thumb']) ?  $params['Thumb'] : '';
            $data['LangCode'] = !empty($params['LangCode']) ?  $params['LangCode'] : '';
            $data['Content'] = !empty($params['Content']) ?  htmlspecialchars_decode($params['Content']) : '';
            $data['Enabled'] = !empty($params['Status']) ?  $params['Status'] : 2;
            $data['Sort'] = !empty($params['OrderID']) ?  $params['OrderID'] : '';
            if(!empty($params['id'])){
                $model->updateEdmActivityTemplate($params['id'],$data);
                return json(['code' => 200,'msg' => '修改成功','url'=> '/activity/templateIndex']);
            }else{
                $ret = $model->createEdmActivityTemplate($data);
                if($ret > 0){
                    return json(['code' => 200,'msg' => '新增成功','url'=> '/activity/templateIndex']);
                }
            }
            return json(['code' => 1001,'msg' => '操作失败']);
        }
        return view();
    }

    /**
     * 启用,禁用,删除
     */
    public function operating(){
        $input = input();
        if(!empty($input['data'])) {
            $model = new EDMActivityModel();
            $params = $input['data'];
            if(empty($params['id'])){
                return json(['code' => 1001,'msg' => '数据有误']);
            }
            switch ($params['type']) {
                case 1:
                    $ret = $model->updateEdmActivity(['in',$params['id']],['Enabled' => 1]);
                    if($ret > 0){
                        return json(['code' => 200,'msg' => '启用成功','url'=> '/activity/index']);
                    }
                    break;
                case 2:
                    $ret = $model->updateEdmActivity(['in',$params['id']],['Enabled' => 2]);
                    if($ret > 0){
                        return json(['code' => 200,'msg' => '禁用成功','url'=> '/activity/index']);
                    }
                    break;
                case 3:
                    $ret = $model->del(['in',$params['id']]);
                    if($ret > 0){
                        return json(['code' => 200,'msg' => '删除成功','url'=> '/activity/index']);
                    }
                    break;
                default:
                    break;
            }
            return json(['code' => 1001,'msg' => '操作失败']);
        }
    }

    /**
     * 启用,禁用,删除
     */
    public function templateOperating(){
        $input = input();
        if(!empty($input['data'])) {
            $model = new EDMActivityModel();
            $params = $input['data'];
            if(empty($params['id'])){
                return json(['code' => 1001,'msg' => '数据有误']);
            }
            switch ($params['type']) {
                case 1:
                    $ret = $model->updateEdmActivityTemplate(['in',$params['id']],['Enabled' => 1]);
                    if($ret > 0){
                        return json(['code' => 200,'msg' => '启用成功','url'=> '/activity/templateIndex']);
                    }
                    break;
                case 2:
                    $ret = $model->updateEdmActivityTemplate(['in',$params['id']],['Enabled' => 2]);
                    if($ret > 0){
                        return json(['code' => 200,'msg' => '禁用成功','url'=> '/activity/templateIndex']);
                    }
                    break;
                case 3:
                    $ret = $model->delTemplate(['in',$params['id']]);
                    if($ret > 0){
                        return json(['code' => 200,'msg' => '删除成功','url'=> '/activity/templateIndex']);
                    }
                    break;
                default:
                    break;
            }
            return json(['code' => 1001,'msg' => '操作失败']);
        }
    }
    /*
    * 本地上传图片
    * */
    public function imgUpload(){
        $localres = localUpload(false);
        if($localres['code']==200){
            $remotePath = '/diy/affiliateimages/'.date("Ymd");
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
                $res['msg'] = "上传成功";
//                $res['url'] = $remotePath.'/'.$localres['FileName'];
                $res['url'] = DX_FTP_ACCESS_URL.'diy/affiliateimages/'.date("Ymd").'/'.$localres['FileName'];
                $res['FileName'] = $localres['FileName'];
            }else{
                $res['code'] = 100;
                $res['msg'] = "上传远程服务器失败";
            }
        }else{
            $res['code'] = 100;
            $res['msg'] = "保存本地图片失败";
        }
        return json($res);

        /*
        // 获取表单上传文件 例如上传了001.jpg
        $file = request()->file('file');
        // 移动到框架应用根目录/public/uploads/ 目录下
        if($file){
            $path = "public".DS . 'uploads'.DS.'edm'. DS .'Attachments'. DS .date('Ymd'). DS;
            $upload_path = ROOT_PATH . $path;
            $info = $file->move($upload_path,false);
            if($info){
                $file_name = explode('.',$info->getFilename());
                $lase_nmae = strtolower(end($file_name));
                $img_type = ['jpg', 'jpeg', 'png'];
                if(!in_array($lase_nmae,$img_type)){
                    $res['code'] = 100;
                    $res['msg'] = "The format of uploaded pictures is incorrect";
                }else{
                    $file_path= 'uploads'. DS .'edm'. DS .'Attachments'. DS .date('Ymd'). DS .$info->getSaveName();
                    $res['code'] = 200;
                    $res['msg'] = "上传成功";
                    $res['url'] = DS .'Attachments'. DS .date('Ymd'). DS .$info->getSaveName();
                    $res['FileName'] = $info->getFilename();
                    processingPictures($file_path,$lase_nmae);
                }
            }else{
                // 上传失败获取错误信息
                $res['code'] = 100;
                $res['msg'] = $file->getError();
            }
        }else{
            $res['code'] = 100;
            $res['msg'] = "上传图片超过尺寸";
        }
        return json($res);
        */
    }

    /**
     * 获取模板，用于新增活动选择语种，选择模板
     */
    public function getTemplateList(){
        $input = input();
        $lang = !empty($input['LangCode']) ? $input['LangCode'] : 'en';
        $arr = $this->templateList($lang);
        return json($arr);
    }

    /**
     * 模板列表
     */
    private function templateList($lang){
        $arr = array();
        $img_url = config('admin_edm_url_config');
        $model = new EDMActivityModel();
        $list = $model->selectActivityTemplatePaginate(['LangCode' => $lang]);
        if(!empty($list)){
            foreach($list as $key => $lists){
                $arr[$key]['id'] = $lists['id'];
                $arr[$key]['lang'] = $lists['LangCode'];
                $arr[$key]['title'] = $lists['Title'];
                if(strstr($lists['PreviewImage'],'http://') !== false || strstr($lists['PreviewImage'],'https://') !== false){
                    $arr[$key]['thumb'] = $lists['PreviewImage'];
                }else{
                    $arr[$key]['thumb'] = $img_url . str_replace('\\','/',$lists['PreviewImage']);
                }
                $arr[$key]['json'] = json_encode($arr[$key],JSON_UNESCAPED_UNICODE);
            }
        }
        return $arr;
    }

    /* 活动列表页 */
    public function test()
    {

        $productArr = array();
        $path = ROOT_PATH.DS.'public'.DS.'static'.DS.'template'.DS.'template.html';
        $img_url = config('admin_edm_url_config');
        $model = new EDMActivityModel();
        $list = $model->getEdmActivityTemplate(11);
        $page_title = !empty($activity['PageTitle']) ? $activity['PageTitle'] : 'aaaaaaaaaaaaaaaaa';
        $banner = $img_url.'/Attachments/Admin/20190610135714_vkbu.jpg';
        $banner2nd = '/Attachments/Admin/20190611152226_h496.jpg';

        $bannerurl = 'https://c.dx.com/collection/201906/20190605/html/en.html';
        $ga_code = 'utm_source=dx&utm_medium=edm&utm_campaign=en20190611umia5pro&Utm_rid=12787464';
        $bannerJson = '[
        {"MainTitle":"1221312","SubTitle":"312312","ImageUrl":"/Attachments/Admin/20190611152226_h496.jpg","SPUList":"2057508+2067904"},
        {"MainTitle":"33333333","SubTitle":"4444","ImageUrl":"555","SPUList":"2067904"}
        ]';
        $categoryArray = [
            'Phone',
            'Phone222'
        ];
        $url = config("api_base_url")."mall/product/getEdmActivityProductListBySpus";
        $products = accessTokenToCurl($url,null,[
            'spus'=> '2046215,2046221,2067910',
        ],true);
        if(isset($products['code']) && $products['code'] == 200 && !empty($products['data'])){
            $this->assign('currencyCode',$products['data']['currencyCode']);
            $this->assign('currency_flag',$products['data']['currencyCodeSymbol']);
            unset($products['data']['currencyCode'],$products['data']['currencyCodeSymbol']);
            $productArr = $products['data'];
        }
        $bannerArr = json_decode($bannerJson,true);
        if(!empty($bannerArr)){
            foreach($bannerArr as $val){
                $str[] = explode('+',$val['SPUList'])[0];
            }
            $products = accessTokenToCurl($url,null,[
                'spus'=> implode(',',$str)
            ],true);
            if(isset($products['code']) && $products['code'] == 200 && !empty($products['data'])){
                $this->assign('currencyCode',$products['data']['currencyCode']);
                $this->assign('currency_flag',$products['data']['currencyCodeSymbol']);
                unset($products['data']['currencyCode'],$products['data']['currencyCodeSymbol']);
                $bannerItem = $products['data'];
            }

            if(!empty($bannerItem)){
                foreach($bannerItem as $key => $val){
                    $findData = CommonLib::filterArrayByKey($bannerArr,'SPUList',$val['id']);
                    $bannerItem[$key]['bannerUrl'] = !empty($findData['ImageUrl']) ? $img_url.$findData['ImageUrl'] : '';
                }
            }
        }
        $bannerT = isset($bannerArr[0]) ? $bannerArr[0] : array();
        $topbg = empty($bannerT['ImageUrl']) ? 'http://c.dx.com/edm/201802/20180228/images/edmBanner.jpg' : $img_url.$bannerT['ImageUrl'];
        $spulist = array();
        //banner spu推荐
        if(!empty($bannerT['SPUList'])){
            $spulist = explode('+',$bannerT['SPUList']);
            //只要2个
            if(count($spulist) != 2){
                $spulist = array();
            }else{
                $products = accessTokenToCurl($url,null,[
                    'spus'=> "2046221,2057508",
                ],true);
                if(isset($products['code']) && $products['code'] == 200 && !empty($products['data'])){
                    unset($products['data']['currencyCode'],$products['data']['currencyCodeSymbol']);
                    $bannerProductList = $products['data'];
                    $this->assign('bannerProductList',$bannerProductList);
                }
            }
        }
        $cont = $list['Content'];
        $products = accessTokenToCurl($url,null,[
            'spus'=> '2067904,2067905,2067906,2067907,2067909,2067910,2067913,2067914,2067915,2619662',
        ],true);
        if(isset($products['code']) && $products['code'] == 200 && !empty($products['data'])){
            unset($products['data']['currencyCode'],$products['data']['currencyCodeSymbol']);
            $product = $products['data'];
            $search_ids = array_column($product, 'id');
        }
//        pr($product);die;
        $cate = [
            'Phone'=>[
                2067904,2067909
            ],
            'Phone222'=>[
                2067909,2067910,2067913,2067914,2067915
            ],
                        'Phone2223333'=>[
        2619662
    ]
        ];
//        $found_key = array_search(40489, array_column($userdb, 'uid'));
        $arr = array();
        foreach($cate as $key => $val){
            foreach($val as $k => $v){
                $index = array_search($v, $search_ids);
                $arr[$key][$index] = $product[$index];
            }
//            pr($arr);die;
            if(!empty($arr[$key])){
                array_values($arr[$key]);
                //将数组分割3个为一个数组
//                $arr[$key] = array_chunk($arr[$key],3);
            }
        }
//        $fp = fopen($path, 'w+');
//        fwrite($fp, $cont);
//        fclose($fp);

        $this->assign('page_title',$page_title);
        $this->assign('topbg',$topbg);
        $this->assign('bannerT',$bannerT);
        $this->assign('bannerItem',$bannerItem);
        $this->assign('banner',$banner);
        $this->assign('banner2nd',$banner2nd);
        $this->assign('ga_code',$ga_code);
        $this->assign('recommend_products',$productArr);
        $this->assign('products',$arr);
        $this->assign('bannerurl',$bannerurl);
        $this->assign('categoryArray',$categoryArray);
        $this->assign('video_products',array());
        echo $this->fetch($path);die;
        $contents = ob_get_contents();

        //$model->updateEdmActivity(8,['PreviewHtml'=>$contents]);

        die;
        return view();
    }

    /**
     * 活动页面预览
     * @return \think\response\Json|View
     */
    public function preview(){
        $input = input();
//        $path = APP_PATH.'admin'.DS.'view'.DS.'activity'.DS.'preview.html';
//        $model = new EDMActivityModel();
        if(empty($input['id'])){
            return json(['code'=>1001,'msg'=>'id 不存在']);
        }
        $content = $this->viewSource($input['id']);
        echo $content;
//        $activity = $model->getEdmActivity($input['id']);
//        return $activity['PreviewHtml'];
    }

    /**
     * @param $id
     * @param $country
     * @return int
     * 生成活动页面源码
     */
    public function viewSource($id,$country='US'){
        try {
            if(empty($id)){
                return false;
            }
            $model = new EDMActivityModel();
            $content = $banner = $gifspu = $order_spu_string = $recommend_spu_string = $banner2nd = '';
            $spuText = $spus = $otherSpu = $recommend_products = $other_products = $spulist = $bannerProductList = $bannerItem= array();
            $key = 'category';
            $path = ROOT_PATH . DS . 'public' . DS . 'static' . DS . 'template' . DS . 'template.html';

            $img_url = config('admin_edm_url_config');
            $activity = $model->getEdmActivity($id);
            if (!empty($activity['TemplateID'])) {
                $template = $model->getEdmActivityTemplate($activity['TemplateID']);
                $templateType = !empty($template['TemplateType']) ? $template['TemplateType'] : 'landing_pg';
                if (!empty($template['Content'])) {
                    //html模板
                    $content = $template['Content'];
                }else{
                    pr('该活动模板的ID没有同步！');
                    die;
                }
            }
            $page_title = !empty($activity['PageTitle']) ? $activity['PageTitle'] : '';
            //GA跟踪代码
            $ga_code = !empty($activity['GACode']) ? $activity['GACode'] : '';
            //链接地址
            $bannerurl = !empty($activity['BannerUrl']) ? $activity['BannerUrl'] : '';
            //banner图片地址
            if (!empty($activity['Banner'])) {
                if(strstr($activity['Banner'],'http://') !== false || strstr($activity['Banner'],'https://') !== false){
                    $banner = $activity['Banner'];
                }else{
                    $banner = $img_url . str_replace('\\','/',$activity['Banner']);
                }
            }
            //banner2图片地址
            if (!empty($activity['Banner2nd'])) {
                if(strstr($activity['Banner2nd'],'http://') !== false || strstr($activity['Banner2nd'],'https://') !== false){
                    $banner2nd = $activity['Banner2nd'];
                }else{
                    $banner2nd = $img_url . str_replace('\\','/',$activity['Banner2nd']);
                }
            }

            $bannerArray = !empty($activity['Banners']) ? json_decode($activity['Banners'], true) : array();
            $bannerT = isset($bannerArray[0]) ? $bannerArray[0] : array();
            $topbg = 'http://c.dx.com/edm/201802/20180228/images/edmBanner.jpg';
            if(!empty($bannerT['ImageUrl'])) {
                if (strstr($bannerT['ImageUrl'], 'http://') !== false || strstr($bannerT['ImageUrl'], 'https://') !== false) {
                    $topbg = str_replace('\\','/',$bannerT['ImageUrl']);
                }else{
                    $topbg = $img_url. str_replace('\\','/',$bannerT['ImageUrl']);
                }
            }
            if(!empty($bannerT['SPUList'])){
                $spulist = explode('+',$bannerT['SPUList']);
                //只要2个，规则规定要spu+赠送spu，如果不是，那么这个模板展示就会有问题
                if(count($spulist) != 2){
                    $spulist = array();
                }
            }
            //banner 轮播图，需要点击的时候要跳转产品链接，标题信息，所以需要获取产品信息
            if(!empty($bannerArray)){
                foreach($bannerArray as $val){
                    if(!empty($val['SPUList'])){
                        //每个banner，只取一个产品就可以了，原来他们就是这样的逻辑
                        $bannerProductArr[] =  explode('+',$val['SPUList'])[0];
                    }
                }
            }

            //产品主推的spu列表
            if(!empty($activity['SPUText'])){
                if($activity['IsHistory'] == 1){
                    $spuText = explode("\r\n", trim($activity['SPUText']));
                }else{
                    $spuText = explode("\n", trim($activity['SPUText']));
                }
            }

            //其他产品 + 类别信息
            if(!empty($activity['OtherSPUText'])){
                if($activity['IsHistory'] == 1){
                    $otherSpuText = explode("\r\n", trim($activity['OtherSPUText']));
                }else{
                    $otherSpuText = explode("\n", trim($activity['OtherSPUText']));
                }
                foreach ($otherSpuText as $val) {
                    //第一个肯定是类别，规则规定
                    if (!preg_match("/^\d*$/", $val)) {
                        $key = $val;
                    } else {
                        $otherSpu[$key][] = $val;
                        $spus[] = $val;
                    }
                }
            }

            //给前端页面传值，异步加载价格数据
            $order_spu_string = implode(',',$spus);
            $spus = array_merge($spus, $spuText);
            //banner 推荐产品，spu+赠品活动显示，而且只有一组显示
            if(!empty($spulist)){
                $gifspu = implode(',',$spulist);
                $spus = array_merge($spus, $spulist);
            }
            //banner 推荐产品
            if(!empty($bannerProductArr)){
                $spus = array_merge($spus, $bannerProductArr);
            }
            $apiParams = [
                'spus' => implode(',', $spus),
                'lang' => !empty($activity['LangCode']) ? $activity['LangCode'] : 'en',
                'currency' => !empty($activity['CurrencyCode']) ? $activity['CurrencyCode'] : 'USD',
                'country' => $country,
                'templateType'=>$templateType
            ];
            //调用产品接口
            $products = accessTokenToCurl(config("api_base_url") . "mall/product/getEdmActivityProductListBySpus", null,$apiParams , true);
            if (isset($products['code']) && $products['code'] == 200 && !empty($products['data'])) {
                //币种符号
                $this->assign('currencyCode', $products['data']['currencyCode']);
                $this->assign('currency_flag', $products['data']['currencyCodeSymbol']);
                unset($products['data']['currencyCode'], $products['data']['currencyCodeSymbol']);

                $product = $products['data'];
                // 查询，组装数据
                $search_ids = array_column($product, 'id');
            }

            if (empty($product)) {
                pr('地址：'.config("api_base_url") . "mall/product/getEdmActivityProductListBySpus");
                pr('参数：'.json_encode($apiParams));
                pr('未找到产品数据');
                die;
            }
            //banner 推荐产品 (spu+spu(价格为0))
            if (!empty($spulist)) {
                foreach ($spulist as $key => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (isset($product[$index]) && $index !== false) {
                        $bannerProductList[$key] = $product[$index];
                    }
                }
                if(count($bannerProductList) == 2){
                    $this->assign('bannerProductList',$bannerProductList);
                }
            }

            //主推产品
            if (!empty($spuText)) {
                $recommend_spu_string = implode(',',$spuText);
                foreach ($spuText as $key => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (!empty($product[$index]) && $index !== false) {
                        $recommend_products[$key] = $product[$index];
                    }
                }
            }

            //banner 轮播图，产品路径跳转
            if (!empty($bannerProductArr)) {
                foreach ($bannerProductArr as $key => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (!empty($product[$index]) && $index !== false) {
                        $findData = CommonLib::filterArrayByKey($bannerArray,'SPUList',$product[$index]['id']);
                        $bannerItem[$key] = $product[$index];
                        $bannerItem[$key]['bannerUrl'] = '';
                        if(!empty($findData['ImageUrl'])) {
                            if (strstr($findData['ImageUrl'], 'http://') !== false || strstr($findData['ImageUrl'], 'https://') !== false) {
                                $bannerItem[$key]['bannerUrl'] = str_replace('\\','/',$findData['ImageUrl']);
                            }else{
                                $bannerItem[$key]['bannerUrl'] = $img_url. str_replace('\\','/',$findData['ImageUrl']);
                            }
                        }
                    }
                }
            }

            //其他产品列表
            foreach ($otherSpu as $category => $products) {
                foreach ($products as $pkey => $pid) {
                    $index = array_search($pid, $search_ids);
                    if (isset($product[$index]) && $index !== false) {
                        $other_products[$category][$pkey] = $product[$index];
                    }
                }
                if (!empty($other_products[$category])) {
                    array_values($other_products[$category]);
                }
            }

            $this->assign('lang', $activity['LangCode']);
            $this->assign('currency', !empty($activity['CurrencyCode']) ? $activity['CurrencyCode'] : 'USD');
            $this->assign('recommend_spu_string', $recommend_spu_string);
            $this->assign('gifspu', $gifspu);
            //判断是LP还是EDM
            if(!empty($template['TemplateType']) && ($template['TemplateType'] == 'landing_pg' || $template['TemplateType'] == 'm_landing_pg')){
                $this->assign('order_spu_string', json_encode(array_values($otherSpu)));
            }else{
                $this->assign('order_spu_string', $order_spu_string);
            }
            $this->assign('page_title', $page_title);
            $this->assign('banner2nd',$banner2nd);
            $this->assign('topbg', $topbg);
            $this->assign('bannerT', $bannerT);
            $this->assign('banner', $banner);
            $this->assign('ga_code', $ga_code);
            $this->assign('recommend_products', $recommend_products);
            $this->assign('products', $other_products);
            $this->assign('bannerurl', $bannerurl);
            $this->assign('video_products', array());
            $this->assign('bannerItem',$bannerItem);

            //写入内容到模板
            if (!empty($content)) {
                $fp = fopen($path, 'w+');
                fwrite($fp, $content);
                fclose($fp);
            }
            return $this->fetch($path);
//        $contents = ob_get_contents();
//        $ret = $model->updateEdmActivity($id,['PreviewHtml'=>htmlspecialchars_decode($contents)]);
//        return $ret;
        }catch (Exception $e){
            pr($e->getMessage());die;
            return '活动数据有误,请检查';
        }
    }

    public function syncOldEdm(){
        $params = input();
        $page = isset($params['page']) ? $params['page'] : 1;
        $pagesize = isset($params['pagesize']) ? $params['pagesize'] : 1000;
        $list = (new EDMActivityModel())->getEdmBak($page,$pagesize);
        if(!empty($list['data'])){
            foreach($list['data'] as $key => $val){
                $insertAll[$key]['id'] = $val['ID'];
                $insertAll[$key]['ActivityTitle'] = $val['TaskTitle'];
                $insertAll[$key]['PageTitle'] = trim($val['PageTitle']);
                $insertAll[$key]['CurrencyCode'] = $val['CurrencyCode'];
                $insertAll[$key]['LangCode'] = $val['LangCode'];
                //图片要处理
                //http://ewp.dx.com/admin/20190621162059_qxcc.jpg
                $insertAll[$key]['Banner'] = $val['Banner'];
                if(!empty($val['Banner'])){
                    $insertAll[$key]['Banner'] = str_replace("/Attachments/Admin/","http://ewp.dx.com/admin/",$val['Banner']);
                }
                $insertAll[$key]['BannerUrl'] = $val['BannerUrl'];
                $insertAll[$key]['Banner2nd'] = $val['Banner2nd'];
                if(!empty($val['Banner2nd'])){
                    $insertAll[$key]['Banner2nd'] = str_replace("/Attachments/Admin/","http://ewp.dx.com/admin/",$val['Banner2nd']);
                }
                $insertAll[$key]['Banner2Url'] = $val['Banner2Url'];
                $insertAll[$key]['SPUText'] = $val['SKUText'];
                $insertAll[$key]['SPUTextCustom'] = $val['SKUTextCustom'];
                $insertAll[$key]['OtherSPUText'] = $val['OtherSKUText'];
                $insertAll[$key]['VideoSKUText'] = $val['VideoSKUText'];
                $insertAll[$key]['BrandSKUText'] = $val['BrandSKUText'];
                $insertAll[$key]['TemplateID'] = $val['TemplateID'];
                $insertAll[$key]['GACode'] = $val['GACode'];
                $insertAll[$key]['Remark'] = $val['Remark'];
                $insertAll[$key]['Enabled'] = $val['Enabled'];
                $insertAll[$key]['UrlSnippet'] = $val['UrlSnippet'];
                $insertAll[$key]['Banners'] = $val['Banners'];
                $insertAll[$key]['EmailSubject'] = trim($val['PageTitle']);//邮件标题
                $insertAll[$key]['CreateTime'] = $val['AddedTime'];
                $insertAll[$key]['IsHistory'] = 1;
            }
            $list = (new EDMActivityModel())->insertAllEdmBak($insertAll);
            pr($list);
        }
    }
}