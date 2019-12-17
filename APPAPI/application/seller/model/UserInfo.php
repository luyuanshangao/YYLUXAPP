<?php
namespace app\seller\model;
use think\Cache;
use think\Exception;
use think\Model;
use think\Db;

/**
 * 供应商模型
 * @author
 * @version  heng zhang 2018/3/30
 */
class UserInfo extends Model{

    public $page_size = 10;
    public $page = 1;
    protected $user = 'sl_seller';
    protected $user_extension = 'sl_seller_extension';
    protected $logistics = 'sl_logistics_management';
    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_seller');
    }

    /**
     * 供应商列表
     * @param $params
     * @return array
     */
    public function sellerLists($params){
        $page_size = isset($params['page_size']) ? $params['page_size'] : $this->page_size;
        $page = isset($params['page']) ? $params['page'] : $this->page;

        $query = $this->db->table($this->user)->join($this->user_extension,$this->user.'.id='.$this->user_extension.'.seller_id');

        //过滤字段
//        $query->field("$this->user".'.*'.",concat(first_name,' ',last_name) as true_name,"."$this->user_extension".'.*');
        //名称
        if(isset($params['true_name']) && $params['true_name']){
            $query->where([
                $this->user.'.true_name'=>['like','%'. $params['true_name'] . '%']
            ]);
        }

        if(isset($params['seller_code']) && $params['seller_code']){
            $query->where([
                $this->user . '.seller_code' => $params['seller_code']
            ]);
        }

        //电话号码
        if(isset($params['phone_num']) && $params['phone_num']){
            $query->where([
                $this->user . '.phone_num' => $params['phone_num']
            ]);
        }
        //email
        if(isset($params['email']) && $params['email']){
            $query->where([
                $this->user . '.email' => $params['email']
            ]);
        }
        //公司名称
        if(isset($params['company_name']) && $params['company_name']){
            $query->where([
                $this->user_extension.'.company_name'=>['like','%'. $params['company_name'] . '%']
            ]);
        }
        //法人姓名
        if(isset($params['corporation_name']) && $params['corporation_name']){
            $query->where([
                $this->user_extension.'.corporation_name'=>['like','%'. $params['corporation_name'] . '%']
            ]);
        }
        //公司联系人
        if(isset($params['company_contact']) && $params['company_contact']){
            $query->where([
                $this->user_extension.'.company_contact'=>['like','%'. $params['company_contact'] . '%']
            ]);
        }
        //公司联系人电话
        if(isset($params['company_contact_phone']) && $params['company_contact_phone']){
            $query->where([
                $this->user_extension . '.company_contact_phone' => $params['company_contact_phone']
            ]);
        }
        //经营模式
        if(isset($params['management_model']) && $params['management_model']){
            $query->where([
                $this->user_extension . '.management_model' => $params['management_model']
            ]);
        }
        //公司地址
        if(isset($params['company_address']) && $params['company_address']){
            $query->where([
                $this->user_extension.'.company_address'=>['like','%'. $params['company_address'] . '%']
            ]);
        }
        //社会信用代码
        if(isset($params['social_credit_code']) && $params['social_credit_code']){
            $query->where([
                $this->user_extension . '.social_credit_code' => $params['social_credit_code']
            ]);
        }
        //省
        if(isset($params['province']) && $params['province']){
            $query->where([
                $this->user . '.province' => $params['province']
            ]);
        }
        //市
        if(isset($params['city']) && $params['city']){
            $query->where([
                $this->user . '.city' => $params['city']
            ]);
        }
        //县城
        if(isset($params['country_town']) && $params['country_town']){
            $query->where([
                $this->user . '.country_town' => $params['country_town']
            ]);
        }
        //用户ID
        if(isset($params['user_id']) && $params['user_id']){
            $query->where([
                $this->user . '.id' => $params['user_id']
            ]);
        }
        //用户状态:0-未认证审核,1-已认证审核,2-冻结,3-禁用
        if(isset($params['status']) && $params['status']){
            $query->where([
                $this->user . '.status' => $params['status']
            ]);
        }
        //开始时间结束时间
        if(isset($params['startTime']) && $params['startTime']){
            $query->where([
                $this->user.'.addtime'=>['>=',date('YmdHis',strtotime($params['startTime']))]
            ]);
        }
        if(isset($params['endTime']) && $params['endTime']){
            $query->where([
                $this->user.'.addtime'=>['<=',date('YmdHis',strtotime($params['endTime']))]
            ]);
        }
        $query->where($this->user .'.is_delete','<>',(int)1);
        $query->order([$this->user .'.id'=>'desc']);
        $ret = $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page])->toArray();
        return $ret;
    }

    /**
     * 供应商详情
     * @param $params
     * @return array
     */
    public function getSeller($params){
        $where = [];
        if(isset($params['user_id'])){
            $where['id'] = $params['user_id'];
        }
        if(isset($params['true_name'])){
            $where['true_name'] = $params['true_name'];
        }
        $query = $this->db->table($this->user)->join($this->user_extension,$this->user.'.id='.$this->user_extension.'.seller_id');
        $query->where($where);
        return $query->find();
    }

    /*
  * 查询发送站内信用户
  * */
    public function getSendMessageSeller($where){
        return $this->db->table($this->user)->where($where)->field("id,true_name,email")->select();
    }

    /**
     * 根据条件获取数据
     * @param array $where
     * @param $field
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function getSellerByWhere(array $where, $field=null){
        $query = $this->db->table($this->user);
        if (isset($where['is_self_support']) && !empty($where['is_self_support'])){
            $query->where(['is_self_support' => $where['is_self_support']]);
        }
        if (!empty($field)){
            $query->field($field);
        }
        return $query->select();
    }

    /**
     * 供应商名称
     * @param $params
     * @return array
     */
    public function getSellerName($params){
        $query = $this->db->table($this->user);
        $query->where(['id' => ['in',$params['user_ids']]]);
        return $query->column("true_name","id");
    }

    /**
     * 供应商修改
     * @param $id
     * @param $params
     * @return bool
     */
    public function updateSeller($id,$params){
//        return apiReturn(['code'=>20000001, 'data'=>$params]);
        $this->db->startTrans();
        try{

            //是否存在
            $user = $this->db->table($this->user)->where(['id' => $id])->find();

            if(empty($user)){
                return apiReturn(['code'=>20000001, 'data'=>'供应商不存在']);
            }
            //修改数据
            $this->db->table($this->user)->where(['id' => $id])->update($params['user']);

//            return apiReturn(['code'=>20000001, 'data'=>$this->db->table($this->user)->getLastSql()]);
            $user_extension = $this->db->table($this->user_extension)->where(['seller_id' => $id])->find();
            if(empty($user_extension)){
                return apiReturn(['code'=>20000002, 'data'=>'供应商不存在']);
            }
            $this->db->table($this->user_extension)->where(['seller_id' => $id])->update($params['user_extension']);

            // 提交事务
            $this->db->commit();
            return apiReturn(['code'=>200, 'data'=>[]]);
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            return false;
        }
    }


    /**
     * 删除
     * @param $params
     * @return bool
     */
    public function delSeller($params){
        //是否存在
        $user = $this->db->table($this->user)->where(['id' => $params['user_id'],'is_delete'=>0])->find();
        if(empty($user)){
            return false;
        }
        //修改数据
        $this->db->table($this->user)->where(['id' => $params['user_id']])->update([
            'op_name' => $params['op_name'],
            'op_desc' => $params['op_desc'],
            'is_delete' => 1
        ]);
        return true;
    }

    /**
     * 重置密码
     * @param $params
     * @return bool
     */
    public function resetPassword($user_id,$params){
        //是否存在
        $user = $this->db->table($this->user)->where(['id' => $user_id,'is_delete'=>0])->find();
        if(empty($user)){
            return false;
        }
        //暂时是MD5加密
        //判断与原密码是否一致
//        if($user['password'] != md5($params['old_pwd'])){
//            return apiReturn(['code'=>2002222, 'data'=>'密码不一致']);
//        }
        $this->db->table($this->user)->where(['id' => $user_id,'is_delete'=>0])->update(['password' => md5($params['new_pwd'])]);

        return apiReturn(['code'=>200, 'data'=>'success']);
    }
    /**
    * 物流列表及分页  包括查询
    * auther Wang   2018-04-08
    */
    public static  function LogisticsList($data){
              $Logistics = Db::connect('db_seller')->table('sl_logistics_management');

              if(!empty($data['country'])){
// return $data;
                 $date['countryENName'] = array('like','%'.$data['country'].'%');

                 $Logistics_list = $Logistics->where($date)->where('status','neq',0)->order('add_time desc')->paginate($data['page_size']);
              }else{
                 $Logistics_list = $Logistics->where('status','neq',0)->order('add_time desc')->paginate($data['page_size']);
              }

              return array('Logistics_list'=>$Logistics_list->items() ,'page'=>$Logistics_list->render());

    }
    /**
    * 添加前对该物流信息进行判断
    * auther Wang   2018-04-08
    */
   public static function AddJudge($data){
        $AddLogistics = Db::connect('db_seller');
        $country_dada    = explode(",", $data["country"]);

        foreach ($country_dada as $key => $value) {
              $country = explode("-", $value);   //return trim($country[1]);
              foreach ($data['where'] as $k => $v) {
                  $result = $AddLogistics->table('sl_logistics_management')->where(['countryCode'=>trim($country[1]),'shippingServiceID'=>$v["shippingServiceID"]])->field('id')->find();
                  if($result){
                       return false;
                  }
              }
        }
        return true;
   }
    /**
    * 添加物流
    * auther Wang   2018-04-08
    */
    public static function AddLogistics($data){
            $AddLogistics = Db::connect('db_seller');
            $country_dada    = explode(",", $data["country"]);
            $val['add_time'] = time();
            $val['remarks']  = $data['remarks'];
            $val['add_author'] = $data['add_author'];
            // 启动事务
            Db::connect('db_seller')->table('sl_logistics_management')->startTrans();
            try{
                foreach ($country_dada as $key => $value) {
                     $country = explode("-", $value);   //return trim($country[1]);
                     $countries_find   =  Db::connect("db_mongodb")->name("region")->where(['Code'=>trim($country[1])])->field('Name,Code,zhAreaName')->find();
                     if($countries_find){
                         foreach ($data['where'] as $k => $v) {
                             $val["freight"]       = $v["freight"];
                             $val["time_slot"]     = $v["time_slot"];
                             $val["shippingServiceID"]   = $v["shippingServiceID"];
                             $val["shippingServiceText"] = $v["shippingServiceText"];
                             $val['countryENName'] = $countries_find['Name'];
                             $val['countryCode']   = $countries_find['Code'];
                             $val['areaName']      = $countries_find['zhAreaName'];
                             $result = $AddLogistics->table('sl_logistics_management')->insert($val);
                        }
                     }else{
                         return apiReturn(['code'=>100, 'data'=>'获取不到该'.$country[0].'信息']);
                     }
                }
                Db::connect('db_seller')->table('sl_logistics_management')->commit();// 提交事务
                if($result){
                  return apiReturn(['code'=>200, 'data'=>'提交成功']);
                }else{
                  return apiReturn(['code'=>100, 'data'=>'提交失败']);
                }
            } catch (\Exception $e) {
                // 回滚事务
                Db::connect('db_seller')->table('sl_logistics_management')->rollback();
                return apiReturn(['code'=>100, 'data'=>'数据添加失败']);
            }
    }
     /**修改物流
     * [EditLogistics description]
     * auther Wang   2018-04-09
     */
    public static function EditLogistics($data){
       $EditLogistics = Db::connect('db_seller');//dump($data);
       if((int)$data['type'] == 2){
                   $country_dada       = explode(",", $data["country"]);
                   $val['edit_author'] = $data['edit_author'];
                   $val['edit_time']   = time();
                   $val['remarks']     = $data['remarks'];
                   // 启动事务
                   Db::connect('db_seller')->table('sl_logistics_management')->startTrans();
                   try{
                        foreach ($country_dada as $key => $value) {
                             $country = explode("-", $value);
                             $countries_find   =  Db::connect("db_mongodb")->name("region")->where(['Code'=>trim($country[1])])->field('Name,Code,zhAreaName')->find();

                             if($countries_find){
                                 foreach ($data['where'] as $k => $v) {
                                     $val["freight"]       = $v["freight"];
                                     $val["time_slot"]     = $v["time_slot"];
                                     $val["shippingServiceID"]   = (int)$v["shippingServiceID"];
                                     $val["shippingServiceText"] = $v["shippingServiceText"];
                                     $val['countryENName'] = $countries_find['Name'];
                                     $val['countryCode']   = $countries_find['Code'];
                                     $countries =  $EditLogistics->table('sl_logistics_management')->where(['countryCode'=>$countries_find['Code'],'shippingServiceID'=>$v["shippingServiceID"]])->find();
                                     if($countries){
                                        $val['status']   = 1;
                                        $result = $EditLogistics->table('sl_logistics_management')->where(['countryCode'=>$val['countryCode'],'shippingServiceID'=>$v["shippingServiceID"]])->update($val);
                                        // echo $EditLogistics->getlastsql();
                                     }else{
                                        $val['add_author'] = $data['edit_author'];
                                        $val['add_time']   = time();
                                        unset($val['edit_time']);unset($val['edit_author']);
                                        $result = $EditLogistics->table('sl_logistics_management')->insert($val);
                                     }
                                }
                             }else{
                                 return apiReturn(['code'=>100, 'data'=>'获取不到该'.$country[0].'信息']);
                             }
                        }
                        Db::connect('db_seller')->table('sl_logistics_management')->commit();// 提交事务
                        if($result){
                          return apiReturn(['code'=>200, 'data'=>'提交成功']);
                        }else{
                          return apiReturn(['code'=>100, 'data'=>'提交失败']);
                        }
                    } catch (\Exception $e) {//dump($e);
                        // 回滚事务
                        Db::connect('db_seller')->table('sl_logistics_management')->rollback();
                        return apiReturn(['code'=>100, 'data'=>'提交失败']);
                    }
       }else if($data['type'] == 1){
            $LogisticsList = $EditLogistics->table('sl_logistics_management')->where(['countryCode'=>$data['countryCode']])->select();
            return $LogisticsList;
       }
    }
    /**物流删除
     * [EditLogistics description]
     * auther Wang   2018-04-09
     */
    public static  function deleteLogistics($data){
        $val['status'] = 0;
        $result = Db::connect('db_seller')->table('sl_logistics_management')->where(['id'=>$data['id']])->update($val);
        if($result){
            return apiReturn(['code'=>200, 'data'=>'删除成功']);
        }else{
            return apiReturn(['code'=>100, 'data'=>'删除失败']);
        }
    }
}