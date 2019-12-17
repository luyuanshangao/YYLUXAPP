<?php
namespace app\index\model;
use think\Db;
use think\Log;
use think\Model;

/**
 * Created by tinghu.liu
 * Date: 2018/3/15
 * Time: 14:08
 */

class UserModel extends Model{
    // 设置当前模型对应的完整数据表名称
    protected $table = 'sl_seller';
    protected $table_extension = 'sl_seller_extension';
    protected $auth_role_seller_relation = 'sl_auth_role_seller_relation';

    /**
     * 新增user表数据
     * @param $data
     * @return bool|string
     */
    public function insertIntoUserAndExtension($data){
        $user_id = 0;
        // start
        Db::startTrans();
        try{
            //写入user表
            Db::table($this->table)->insert($data);
            $user_id = Db::table($this->table)->getLastInsID();//返回新增数据的自增主键
            //写入user扩展表
            $data_ex = [
                'seller_id'=>$user_id,
                'addtime'=>$data['addtime']
            ];
            Db::table($this->table_extension)->insert($data_ex);
            // submit
            Db::commit();
        } catch (\Exception $e) {
            $user_id = 0;
            Log::record('执行新增user表、user扩展表事务出错');
            // roll
            Db::rollback();
        }
        return $user_id;
    }

    /**
     * 根据用户ID获取单条数据
     * @param $user_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoById($user_id){
        $where = [
            'id'=>$user_id
        ];
        $data = Db::table($this->table)->where($where)->find();
        $data['extension'] = $this->getExInfoBySellerId($user_id);
        return $data;
    }

    /**
     * 根据用户ID获取单条数据(seller提交审核信息)
     * @param $user_id 用户ID
     * @param string $company_name 公司名称
     * @param string $social_credit_code 社会信用代码
     * @param string $company_contact_phone 公司联系人电话
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoByIdForSubmitSellerInfo($user_id, $company_name='', $social_credit_code='', $company_contact_phone=''){
        $query = Db::table($this->table_extension);
        $query->where('seller_id', '<>', $user_id);
        if (!empty($company_name)){
            $query->where('company_name', '=', $company_name);
        }
        if (!empty($social_credit_code)){
            $query->where('social_credit_code', '=', $social_credit_code);
        }
        if (!empty($company_contact_phone)){
            $query->where('company_contact_phone', '=', $company_contact_phone);
        }
        return $query->find();
    }

    /**
     * 根据用户ID获取扩展数据
     * @param $seller_id
     * @return array|false|\PDOStatement|string|Model
     */
    public function getExInfoBySellerId($seller_id){
        $where = [
            'seller_id'=>$seller_id
        ];
        return Db::table($this->table_extension)->where($where)->find();
    }

    /**
     * 根据用户邮箱获取单条数据
     * @param $email
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoByEmail($email){
        $where = [
            'email'=>$email
        ];
        return Db::table($this->table)->where($where)->find();
    }

    /**
     * 根据seller编码获取单条数据
     * @param $seller_code
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoBySellerCode($seller_code){
        $where = [
            'seller_code'=>$seller_code
        ];
        return Db::table($this->table)->where($where)->find();
    }

    /**
     * 根据用户真名名称获取单条数据
     * @param $true_name
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoByTrueName($true_name){
        $where = [
            'true_name'=>$true_name
        ];
        return Db::table($this->table)->where($where)->find();
    }

    /**
     * 根据手机号称获取单条数据
     * @param $phone_num
     * @return array|false|\PDOStatement|string|Model
     */
    public function getInfoByPhoneNum($phone_num){
        $where = [
            'phone_num'=>$phone_num
        ];
        return Db::table($this->table)->where($where)->find();
    }

    /**
     * 根据seller_id更新用户主表、扩展表
     * @param $seller_id
     * @param $data
     * @return int|string
     */
    public function updateUserAndExBySeller_id($seller_id, $data, $data_ex = array()){
        $rtn = false;
        // start
        Db::startTrans();
        try{
            if (!empty($data)){
                //更新user表
                Db::table($this->table)->where(['id'=>$seller_id])->update($data);
            }
            if (!empty($data_ex)){
                //更新user扩展表
                Db::table($this->table_extension)->where(['seller_id'=>$seller_id])->update($data_ex);
            }
            $rtn = true;
            // submit
            Db::commit();
        } catch (\Exception $e) {
            Log::record('执行更新user表、user扩展表事务出错 '.$e->getMessage());
            $rtn = false;
            // roll
            Db::rollback();
        }
        return $rtn;
    }

    /**
     * 提交seller信息审核
     * @param array $data 数据
     * @return bool
     */
    public function submitSellerInfo(array $data){
        $rtn = true;
        //类型：1-个人，2-企业
        $flag = $data['flag'];
        $seller_id = $data['seller_id'];
        Db::startTrans();
        try{
            //个人
            if ($flag == 1){
                //flag=1&true_name=&idcard_num=&idcard_facade=&idcard_reverse=
                $seller_data = [
                    'true_name'=>$data['true_name'],
                    'op_name'=>$data['op_name'],
                    'op_desc'=>$data['op_desc'],
                    'op_time'=>$data['op_time']
                ];
                $seller_data_ex = [
                    'idcard_num'=>$data['idcard_num'],
                    'idcard_facade'=>$data['idcard_facade'],
                    'idcard_reverse'=>$data['idcard_reverse'],
                    'op_name'=>$data['op_name'],
                    'op_time'=>$data['op_time']
                ];
                if (!$this->updateUserAndExBySeller_id($seller_id, $seller_data, $seller_data_ex)){
                    $rtn = false;
                }
            }else{ //企业
                $seller_data_ex = [
                    'company_name'=>$data['company_name'],
                    'social_credit_code'=>$data['social_credit_code'],
                    'business_license_pic'=>$data['business_license_pic'],
                    'company_address'=>$data['company_address'],
                    'operation_scope'=>$data['operation_scope'],
                    'registered_capital'=>$data['registered_capital'],
                    'company_phone'=>$data['company_phone'],
                    'company_contact'=>$data['company_contact'],
                    'company_contact_phone'=>$data['company_contact_phone'],
                    'corporation_name'=>$data['corporation_name'],
                    'corporation_idcard_facade'=>$data['corporation_idcard_facade'],
                    'corporation_idcard_reverse'=>$data['corporation_idcard_reverse']
                ];
                if(!$this->updateUserAndExBySeller_id($seller_id, null, $seller_data_ex)){
                    $rtn = false;
                }
            }
            Db::commit();
        } catch (\Exception $e) {
            Log::record('执行submitSellerInfo事务出错 '.$e->getMessage());
            $rtn = false;
            Db::rollback();
        }
        return $rtn;
    }

    /**
     * 增加子账号
     * @param array $data
     * @return bool
     */
    public function addChildAcct(array $data){
        $rtn = true;
        Db::startTrans();
        try{
            /** 1、增加子账号数据 **/
            $time = time();
            $insert_data['parent_id'] = $data['parent_id'];
            $insert_data['email'] = $data['email'];
            $insert_data['password'] = get_seller_password($data['password']);
            $insert_data['true_name'] = $data['true_name'];
            $insert_data['phone_num'] = $data['phone_num'];
            $insert_data['sex'] = $data['sex'];
            $insert_data['status'] = 1; //用户状态:0-未认证审核,1-已认证审核,2-冻结,3-禁用
            $insert_data['addtime'] = $time;
            //写入user表
            Db::table($this->table)->insert($insert_data);
            $user_id = Db::table($this->table)->getLastInsID();//返回新增数据的自增主键
            Db::table($this->table)->where(['id'=>$user_id])->update(['seller_code'=>get_seller_code($data['management_model'], $user_id)]);
            //写入user扩展表
            $data_ex = [
                'seller_id'=>$user_id,
                'addtime'=>$time
            ];
            Db::table($this->table_extension)->insert($data_ex);
            /** 2、 添加子账号权限分组，默认角色ID是1（制作员）**/
            Db::table($this->auth_role_seller_relation)->insert([
                'role_id'=>1,
                'seller_id'=>$user_id,
                'addtime'=>$time,
            ]);
            Db::commit();
        } catch (\Exception $e) {
            Log::record('执行addChildAcct事务出错 '.$e->getMessage());
            $rtn = 'Exception:'.$e->getMessage();
            Db::rollback();
        }
        return $rtn;
    }

    /**
     * 获取子账号信息【分页】
     * @param $parent_seller_id 父级用户ID（seller）
     * @param $page_size 每页大小
     * @param $is_delete 是否删除：0-未删除，1-已删除
     * @return $this
     * @throws \think\exception\DbException
     */
    public function getChildAcctDataPagenate($parent_seller_id, $page_size=10, $is_delete=0){
        $res = Db::table($this->table)->where(['parent_id'=>$parent_seller_id, 'is_delete'=>$is_delete])->order(['addtime'=>'desc'])->paginate($page_size)->each(function($item, $key){
            return $item;
        });
        $data = $res->toArray();
        $data['page'] = $res->render();
        return $data;
    }

    /**
     * 验证用户密码
     * @param $true_name
     * @return array|false|\PDOStatement|string|Model
     */
    public function checkPassword($where,$password){
        $where['password'] = $password;
        $check = Db::table($this->table)->where($where)->count();
        if($check){
            return true;
        }else{
            return false;
        }
    }

    /**
     * 修改用户信息
     * @param $true_name
     * @return array|false|\PDOStatement|string|Model
     */
    public function updateseller($where,$update_data){
        return Db::table($this->table)->where($where)->update($update_data);
    }

}