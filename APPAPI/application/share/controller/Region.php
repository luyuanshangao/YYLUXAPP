<?php
namespace app\share\controller;

use app\common\controller\Base;
use app\common\helpers\CommonLib;
use app\common\helpers\RedisClusterBase;
use app\common\params\share\region\CreateRegionParams;
use app\demo\controller\Auth;
use app\share\model\DxRegion as DxRegion;
use think\Controller;
use think\Exception;
use think\Monlog;
use think\Validate;

/**
 * 国家区域接口
 */
class Region extends Base
{
    public $regionModel;
    public $redis;
    public function __construct()
    {
        parent::__construct();
        $this->regionModel = new DxRegion();
        $this->redis = new RedisClusterBase();
    }

    /**
     * 国家新增
     * @return mixed
     */
    public function Create(){
        $paramData = request()->post();
        $validate = $this->validate($paramData,(new CreateRegionParams())->rules());
        if(true !== $validate){
            return apiReturn(['code'=>1002, 'data'=>$validate]);
        }
        //过滤不必要的参数
        $serverParams = CommonLib::handleForm((new CreateRegionParams()),$paramData);
        $res = $this->regionModel->createRegion($serverParams);
        if($res){
            $ret = apiReturn(['code'=>200, 'data'=>'success']);
        }else{
            $ret = apiReturn(['code'=>1002, 'data'=>'error']);
        }
        return $ret;
    }


    /**
     * 修改区域
     * @return mixed
     */
    public function updateArea(){
        $paramData = request()->post();
        try{
            $data = $this->regionModel->updateRegionArea($paramData);
            $ret = apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            $ret = apiReturn(['code'=>1002, 'data'=>'error']);
        }
        return $ret;
    }

	/**
	 * 获取国家信息
	 * @return json
	 */
	public function getRegion(){
		$paramData = input();
        try{
            $data = $this->regionModel->getRegion($paramData);
            $ret = apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            $ret = apiReturn(['code'=>1002, 'data'=>'error']);
        }
		return $ret;
	}


    /**
     * 获取国家信息列表
     * @return json
     */
    public function getRegionList(){
        $CountryCode = trim(input("CountryCode"));
        $id = trim(input("id",''));
        $Code = trim(input("Code",''));
        $Name = trim(input("Name",''));
        $ParentID = trim(input("ParentID",0));
        $data = "";
        /*如果没有传参*/
        if(empty($Code) && empty($Name) && empty($id)){
            /*调用获取数据接口*/
            $res1 = $this->regionModel->getRegion(['ParentID'=>$ParentID]);
            if(is_array($res1)){
                $data = array();
                foreach ($res1 as $key=>$value){
                    $data[$key]['ID'] = $value['_id'];
                    $data[$key]['Text'] = $value['Name'];
                    //if($ParentID!= 528651 && $ParentID!=486788){
                    if(empty($value['Code'])){
                        $data[$key]['Value'] = $value['Name'];
                    }else{
                        $data[$key]['Value'] = $value['Code'];
                    }
                    $value['HasChildren'] = isset($value['HasChildren'])?$value['HasChildren']:0;
                    $data[$key]['NeedChildren'] = $value['HasChildren']>0?true:false;
                }
            }
        }else{/*如果有传参*/
            /*调用获取数据接口*/
            if($CountryCode){
                $Country = $this->regionModel->getRegion(['Code'=>trim($CountryCode)]);
                $res = $this->regionModel->getRegion(['Code'=>trim($Code),'Name'=>trim($Name),'id'=>$id,'ParentID'=>$Country[0]['_id']]);
            }else{
                $res = $this->regionModel->getRegion(['Code'=>trim($Code),'Name'=>trim($Name),'id'=>$id],0);
            }
            if($res){
                if(isset($res[0]['_id'])){
                    $ParentID = $res[0]['_id'];
                    /*调用获取数据接口*/
                    $res1 = $this->regionModel->getRegion(['ParentID'=>$ParentID]);
                    if(is_array($res1)){
                        $data = array();
                        foreach ($res1 as $key=>$value){
                            $data[$key]['ID'] = $value['_id'];
                            $data[$key]['Text'] = $value['Name'];
                            if(empty($value['Code'])){
                                $data[$key]['Value'] = $value['Name'];
                            }else{
                                $data[$key]['Value'] = $value['Code'];
                            }
                            $value['HasChildren'] = isset($value['HasChildren'])?$value['HasChildren']:0;
                            $data[$key]['NeedChildren'] = $value['HasChildren']>0?true:false;
                        }
                    }
                }
            }
        }
        return $data;
    }
    /**
     * 获取商城头部国家数据
     */
    public function getHeaderCountry(){
        $params = request()->post();
        try{
            $result = (new DxRegion())->getHeaderCountry($params);
            return apiReturn(['code'=>200,'data'=>$result]);
        }catch (Exception $e){
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
        }
    }

    /**
     * 查找单个国家
     */
    public function find(){
        $params = input();
        $result = array();
        try{
            if(config('cache_switch_on')){
                $result = $this->redis->get(COUNTRY_BY_.$params['Code']);
            }
            if(empty($result)){
                $result = (new DxRegion())->getCountry($params);
                if(!empty($result)){
                    $this->redis->set(COUNTRY_BY_.$params['Code'],$result,CACHE_DAY*5);
                }
            }
            return apiReturn(['code'=>200,'data'=>$result]);
        }catch (Exception $e){
            return apiReturn(['code'=>1002, 'msg'=>$e->getMessage()]);
        }
    }

    /*修改地址简码*/
    public function  updateCode(){
        $ParentID = 487385;
        $region_data = [
            ['name'=>'Acre','code'=>'AC'],
            ['name'=>'Alagoas','code'=>'AL'],
            ['name'=>'Amazonas','code'=>'AM'],
            ['name'=>'Amapa','code'=>'AP'],
            ['name'=>'Bahia','code'=>'BA'],
            ['name'=>'Ceara','code'=>'CE'],
            ['name'=>'Distrito Federal','code'=>'DF'],
            ['name'=>'Espirito Santo','code'=>'ES'],
            ['name'=>'Goias','code'=>'GO'],
            ['name'=>'Maranhao','code'=>'MA'],
            ['name'=>'Mato Grosso','code'=>'MG'],
            ['name'=>'Mato Grosso do Sul','code'=>'MS'],
            ['name'=>'Minas Gerais','code'=>'MT'],
            ['name'=>'Para','code'=>'PA'],
            ['name'=>'Paraiba','code'=>'PB'],
            ['name'=>'Pernambuco','code'=>'PE'],
            ['name'=>'Piaui','code'=>'PI'],
            ['name'=>'Parana','code'=>'PR'],
            ['name'=>'Rio de Janeiro','code'=>'RJ'],
            ['name'=>'Rio Grande do Norte','code'=>'RN'],
            ['name'=>'Rondonia','code'=>'RO'],
            ['name'=>'Roraima','code'=>'RR'],
            ['name'=>'Rio Grande do Sul','code'=>'RS'],
            ['name'=>'Santa Catarina','code'=>'SC'],
            ['name'=>'Sergipe','code'=>'SE'],
            ['name'=>'Sao Paulo','code'=>'SP'],
            ['name'=>'Tocantins','code'=>'TO'],
        ];
        $data['i'] = 0;
        $data['y'] = 0;
        foreach ($region_data as $key=>$value){
           $res = $this->regionModel->updateCode($ParentID,$value['name'],$value['code']);
           if($res){
               $data['i']++;
           }else{
               $data['y']++;
           }
        }
        return $data;
    }

    /**
     * 获取重点国家列表
     * @return json
     */
    public function getFocusRegion(){
        try{
            $data = $this->regionModel->getFocusCountry();
            $ret = apiReturn(['code'=>200, 'data'=>$data]);
        }catch (Exception $e){
            Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
        }
        return $ret;
    }

}
