<?php
namespace app\mallextend\model;

use app\common\helpers\RedisClusterBase;
use app\common\model\AutoIncrement;
use think\Log;
use think\Model;
use think\Db;
/**
 * Coupon 模型
 * @author
 * @version tinghu.liu 2018/5/11
 */
class CouponModel extends Model{
    protected $db;
    protected $table = 'dx_coupon';
    protected $table_code = 'dx_coupon_code';

    public function __construct()
    {
        parent::__construct();
        $this->db = Db::connect('db_mongodb');
    }

    /**
     * 新增coupon基本信息
     * @param array $data
     * @return int|string
     */
    public function addCouponData(array $data){
        /**
         * 获取自增ID
         */
        $auto_increment_model = new AutoIncrement();
        $auto_info = $auto_increment_model->getInfo();
        $_id = (int)$auto_info['CouponId'];
        $auto_id = $_id + 1;
        //拼装新增数据
        $data['CouponId'] = $auto_id;
        $data['SellerId'] = (int)$data['SellerId'];
        //1-初始，2-审核，3-开启，4-关闭，5-待审核（编辑）【返回修改事件触发后变为此状态】
        $data['CouponStatus'] = 1;
        /** 类型转换 start **/
        //优惠渠道
        if (isset($data['CouponChannels']) && !empty($data['CouponChannels'])){
            foreach ($data['CouponChannels'] as $c_key=>&$c){
                $data['CouponChannels'][$c_key] = (int)$c;
            }
        }
        //优惠券策略：1-手工活动、2-自动活动
        $data['CouponStrategy'] = (int)$data['CouponStrategy'];
        //活动策略：1-线上活动、2-线下活动
        $data['ActivityStrategy'] = isset($data['ActivityStrategy'])?(int)$data['ActivityStrategy']:1;

        $data['CouponRuleSetting']['CouponRuleType'] = (int)$data['CouponRuleSetting']['CouponRuleType'];
        //开始结束时间
        $data['CouponTime']['StartTime'] = (int)$data['CouponTime']['StartTime'];
        $data['CouponTime']['EndTime'] = (int)$data['CouponTime']['EndTime'];
        /** 类型转换 end **/
        /**
         * 更新自增ID
         */
        if (!$auto_increment_model->updateDataByWhere(['CouponId'=>$_id], ['CouponId'=>$auto_id])){
            return false;
        }
        //多语言文案翻译 add by zhongning
        if(!empty($data['CouponId'])){
            //task处理队列
            (new RedisClusterBase())->lPush('couponDescLangQueue',$data['CouponId']);
        }
        return $this->db->table($this->table)->insert($data);
    }

    /**
     * 新增coupon code信息
     * @param array $data
     * @return int|string
     */
    public function addCouponCodeData(array $data){
        /**
         * 获取自增ID
         */
        $auto_increment_model = new AutoIncrement();
        $auto_info = $auto_increment_model->getInfo();
        $_id = (int)$auto_info['CouponCodeId'];
        $auto_id = $_id + 1;
        //拼装新增数据
        $data['CouponCodeId'] = $auto_id;
        $data['SellerId'] = (int)$data['SellerId'];
        /**
         * 更新自增ID
         */
        if (!$auto_increment_model->updateDataByWhere(['CouponCodeId'=>$_id], ['CouponCodeId'=>$auto_id])){
            return false;
        }
        return $this->db->table($this->table_code)->insert($data);
    }

    /**
     * 批量新增coupon code
     * @param array $all_data 要新增的数据
     * @return int|string
     */
    public function addCouponCodeAllData(array $all_data){
        return $this->db->table($this->table_code)->insertAll($all_data);
    }

    /**
     * 根据条件获取coupon信息
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponByWhere(array $where){
        return $this->db->table($this->table)
            ->where($where)
            ->select();
    }

    /**
     * 根据coupon ID获取coupon信息
     * @param $counpon_id 条件
     * @param int $flag 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponInfoByCouponId($counpon_id, $flag=0){
        $data = $this->db->table($this->table)->where(['CouponId'=>(int)$counpon_id])->find();
        if ($flag == 0){
            //获取coupon code 数据
            $data['coupon_code_data'] = $this->getCouponCodeDataByWhere(['CouponId'=>(int)$counpon_id]);
        }
        return $data;
    }

    /**
     * 根据条件获取coupon code 数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponCodeDataByWhere(array $where){
        return $this->db->table($this->table_code)
            ->field('CouponCodeId,CouponId,coupon_code')
            ->where($where)
            ->select();
    }

    /**
     * 根据条件获取coupon code 数据（领取）
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponCodeDataByWhereForGet(array $where){
        $query = $this->db->table($this->table_code);
        if (isset($where['CouponId']) && !empty($where['CouponId'])){
            $query->where('CouponId', '=', (int)$where['CouponId']);
        }
        //code状态：0-未领取，1-已领取
        if (isset($where['Status']) && !empty($where['Status'])){
            $query->where('Status', '=', (int)$where['Status']);
        }
        return $query->field('CouponCodeId,CouponId,coupon_code,Status')->select();
    }

    /**
     * 根据条件获取coupon 数据
     * @param array $where 条件
     * @return false|\PDOStatement|string|\think\Collection
     */
    public function getCouponDataByWhere(array $where){
        return $this->db->table($this->table)
            //->field('CouponId,SellerId,coupon_code,Name,CouponTime,CouponChannels')
            ->where($where)
            ->column('CouponId,SellerId,coupon_code,Name,CouponTime,CouponChannels,DiscountType,Description,CouponStatus','CouponId');
    }

    /**
     * 获取coupon数据【分页】
     * @param array $params 参数
     * @return array
     */
    public function getCouponList(array $params){
        //seller ID
        $where = isset($params['SellerId'])?['SellerId'=>(int)$params['SellerId']]:[];
        $query = $this->db->table($this->table)->where($where);
        //状态
        if (isset($params['CouponStatus']) && !empty($params['CouponStatus'])){
            $query->where('CouponStatus', '=', (int)$params['CouponStatus']);
        }
        //优惠券策略
        if (isset($params['CouponStrategy']) && !empty($params['CouponStrategy'])){
            $query->where('CouponStrategy', '=', (int)$params['CouponStrategy']);
        }
        //使用渠道
        if (isset($params['CouponChannels']) && !empty($params['CouponChannels'])){
            $query->where('CouponChannels', 'in', [(int)$params['CouponChannels']]);
        }
        //优惠级别
        if (isset($params['DiscountLevel']) && !empty($params['DiscountLevel'])){
            $query->where('DiscountLevel', '=', $params['DiscountLevel']);
        }
        //优惠券名称
        if (isset($params['Name']) && !empty($params['Name'])){
            $query->where('Name', 'LIKE', $params['Name']);
        }
        //有效期
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
        ){
            $query->where('CouponTime.StartTime', '>', $params['create_on_start']);
        }
        if (
            isset($params['create_on_start']) && !empty($params['create_on_start'])
            && isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('CouponTime.StartTime', '>', $params['create_on_start']);
            $query->where('CouponTime.EndTime', '<', $params['create_on_end']);
        }
        if (
            isset($params['create_on_end']) && !empty($params['create_on_end'])
        ){
            $query->where('CouponTime.EndTime', '<', $params['create_on_end']);
        }
        //分页参数设置
        $page_size = isset($params['page_size']) ? (int)$params['page_size'] : 10;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $path = isset($params['path']) ? $params['path'] : null;
        //排序
        $query->order('CouponId','desc');
        $response =
            /*$this->db
                ->table($this->table)
                ->where($where)*/
            $query->paginate($page_size,false,['type' => 'Bootstrap', 'page' => $page,'path' => $path])
                ->each(function ($item, $key){
                    //优惠级别：1-单品级别优惠，2-订单级别优惠
                    $DiscountLevelStr = '-';
                    switch ($item['DiscountLevel']){
                        case 1:
                            $DiscountLevelStr = '单品';
                            break;
                        case 2:
                            $DiscountLevelStr = '订单';
                            break;
                    }
                    $item['DiscountLevelStr'] = $DiscountLevelStr;
                    //"CouponStrategy": 1,//优惠券策略：1-手工活动、2-自动活动
                    $CouponStrategyStr = '-';
                    switch ($item['CouponStrategy']){
                        case 1:
                            $CouponStrategyStr = '手工活动';
                            break;
                        case 2:
                            $CouponStrategyStr = '自动活动';
                            break;
                    }
                    $item['CouponStrategyStr'] = $CouponStrategyStr;
                    //优惠券类型：1-代金券、2-赠送券(CouponRuleType不能为3)、3-折扣券、4-指定售价
                    $DiscountTypeStr = '-';
                    switch ($item['CouponStrategy']){
                        case 1:
                            $DiscountTypeStr = '代金券';
                            break;
                        case 2:
                            $DiscountTypeStr = '赠送券';
                            break;
                        case 3:
                            $DiscountTypeStr = '折扣券';
                            break;
                        case 4:
                            $DiscountTypeStr = '指定售价';
                            break;
                    }
                    $item['DiscountTypeStr'] = $DiscountTypeStr;
                    //使用渠道"CouponChannels" ：1-全站、2-Web站、3-APP、4-移动端
                    $CouponChannelsArr = [];
                    foreach ($item['CouponChannels'] as $info){
                        switch ($info){
                            case 1:
                                $CouponChannelsStr = '全站';
                                break;
                            case 2:
                                $CouponChannelsStr = 'Web站';
                                break;
                            case 3:
                                $CouponChannelsStr = 'APP';
                                break;
                            case 4:
                                $CouponChannelsStr = '移动端';
                                break;
                        }
                        $CouponChannelsArr[] = $CouponChannelsStr;
                    }
                    $item['CouponChannelsStr'] = implode(',', $CouponChannelsArr);
                    //优惠券总量CouponNumLimit
                    $item['CouponNumLimitStr'] = $item['CouponNumLimit']['Type'] == 1?'不限':$item['CouponNumLimit']['Num'];
                    //Coupon状态：1-初始，2-审核，3-开启，4-关闭，5-待审核（编辑）【返回修改事件触发后变为此状态】
                    $CouponStatusStr = '-';
                    if (isset($item['CouponStatus'])){
                        switch ($item['CouponStatus']){
                            case 1:
                                $CouponStatusStr = '初始';
                                break;
                            case 2:
                                $CouponStatusStr = '审核';
                                break;
                            case 3:
                                $CouponStatusStr = '开启';
                                break;
                            case 4:
                                $CouponStatusStr = '关闭';
                                break;
                            case 5:
                                $CouponStatusStr = '待审核';
                                break;
                        }
                    }
                    $item['CouponStatusStr'] = $CouponStatusStr;

                    return $item;
                });
        $Page = $response->render();
        $data = $response->toArray();
        $data['Page'] = $Page;
        return $data;
    }

    /**
     * 获取coupon数据【分页】
     * @param array $params 参数
     * @return array
     */
    public function getExchangeCouponList(array $params){
        //分页参数设置
        $response = $this->db
                ->table($this->table)
                ->where($params)
                ->select();
        return $response;
    }

    /**
     * 根据条件更新coupon信息
     * @param array $params
     * @return bool|int|string
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function updateCouponDataByParams(array $params){
        $where = ['CouponId'=>(int)$params['CouponId']];
        $up_data = [];
        //更新标识：1-更新全部，2-更新coupon描述（含多语言），3-更新coupon状态
        $flag = $params['flag'];
        switch ($flag){
            case 1:
                //更新全部
                unset($params['CouponId']);
                /** 类型转换 start **/
                //优惠渠道
                if (isset($params['CouponChannels']) && !empty($params['CouponChannels'])){
                    foreach ($params['CouponChannels'] as $c_key=>&$c){
                        $params['CouponChannels'][$c_key] = (int)$c;
                    }
                }
                //优惠券策略：1-手工活动、2-自动活动
                $params['CouponStrategy'] = (int)$params['CouponStrategy'];
                //活动策略：1-线上活动、2-线下活动
                $params['ActivityStrategy'] = isset($params['ActivityStrategy'])?(int)$params['ActivityStrategy']:1;
                $params['CouponRuleSetting']['CouponRuleType'] = (int)$params['CouponRuleSetting']['CouponRuleType'];
                //开始结束时间
                $params['CouponTime']['StartTime'] = (int)$params['CouponTime']['StartTime'];
                $params['CouponTime']['EndTime'] = (int)$params['CouponTime']['EndTime'];
                /** 类型转换 end **/
                $up_data = $params;
                break;
            case 2:
                //更新coupon描述
                if (isset($params['Description']) && !empty($params['Description'])){
                    $up_data['Description'] = $params['Description'];
                }
                //task处理队列,多语言翻译 add by zhongning
                (new RedisClusterBase())->lPush('couponDescLangQueue',$params['CouponId']);
                break;
            case 3:
                //更新coupon状态，CouponStatus：1-初始，2-审核，3-开启，4-关闭，5-待审核（编辑）【返回修改事件触发后变为此状态】
                if (isset($params['CouponStatus']) && !empty($params['CouponStatus'])){
                    $up_data['CouponStatus'] = (int)$params['CouponStatus'];
                }
                break;
        }
        if (!empty($up_data)){
            $query = $this->db->table($this->table);
            return $query->where($where)->update($up_data);
        }else{
            return false;
        }
    }

    /**
     * 更新coupon code 数据
     * @param array $where
     * @param array $up_data
     * @return int|string
     */
    public function updateCouponCodeByWhere(array $where, array $up_data){
        if (isset($up_data['Status'])){
            $up_data['Status'] = (int)$up_data['Status'];
        }
        return $this->db->table($this->table_code)->where($where)->update($up_data);
    }

    /**
     * 删除coupon code
     * @param array $params
     * @return int
     */
    public function deleteCouponCodeByParams(array $params){
        $where['CouponId'] = (int)$params['CouponId'];
        $where['coupon_code'] = $params['CouponCode'];
        return $this->db->table($this->table_code)->where($where)->delete();
    }

}