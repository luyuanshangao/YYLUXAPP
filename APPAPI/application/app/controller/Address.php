<?php
/**
 * Created by PhpStorm.
 * User: pc
 * Date: 2019/11/6
 * Time: 11:24
 */
namespace app\app\controller;

use app\common\controller\AppBase;
use app\app\dxcommon\BaseApi;
use think\Log;

class Address extends AppBase
{
    public $baseApi;
    public function __construct()
    {
        parent::__construct();
        $this->baseApi = new BaseApi();
    }

    public function getAddress()
    {
        $data = input();
        $res = $this->baseApi->getAddress($data);
        if(empty($res)||(!empty($res['code'])&&$res['code']!=200)){
            Log::record('getAddress'.json_encode($data).'res'.json_encode($res),'error');
        }else{
            if(!empty($res['data'])&&is_array($res)){
                foreach($res['data'] as &$va){
                    $va['CPF']=!empty($va['CPF'])?$va['CPF']:'';
                }
            }
        }
        return $res;
    }

    public function saveAddress()
    {
        $data = input();
        if(!empty($data['Country'])&&($data['Country']=='Brasil')){
            $data['Country']=='Brazil';
        }
        $res = $this->baseApi->saveAddress($data);

        if(empty($res)||(!empty($res['code'])&&$res['code']!=200)){
            Log::record('saveAddress'.json_encode($data).'res'.json_encode($res),'error');
        }
        return apiReturn($res);
    }

    public function delAddress()
    {
        $data = input();
        $res = $this->baseApi->delAddress($data);
        return $res;
    }

    public function setDefault()
    {
        $data = input();
        $res = $this->baseApi->setDefault($data);
        return $res;
    }

    public function getDefaultAddres()
    {
        $data = input();
        $res = $this->baseApi->getDefaultAddres($data);
        if(!empty($res['data'])&&is_array($res)){
            $va['CPF']=!empty($va['CPF'])?$va['CPF']:'';
        }
        return $res;
    }
}