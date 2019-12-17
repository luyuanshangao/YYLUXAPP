<?php
namespace app\windcontrol\services;

use think\Log;

/**
 * 订单接口处理逻辑
 * @author Hai.Ouyang
 * 2019-07-03
 */
class WindControlService{
    //线下支付方式
    private $offline = array(

        TRANSACTION_CHANNEL_PAGSMILE => array(
            TRANSACTION_TYPE_PAGSMILE_BOLETO,
            TRANSACTION_TYPE_PAGSMILE_LOTTERY,
            TRANSACTION_TYPE_PAGSMILE_FLASHPAY,
        ),
        
        TRANSACTION_CHANNEL_ASTROPAY => array(
            TRANSACTION_TYPE_ASTROPAY_ONLINE,
            TRANSACTION_TYPE_ASTROPAY_CASH,
        ),

        TRANSACTION_CHANNEL_DLOCAL => array(
            TRANSACTION_TYPE_DLOCAL_ONLINE,
            TRANSACTION_TYPE_DLOCAL_CASH,
        ),

        TRANSACTION_CHANNEL_IDEAL => array(
            TRANSACTION_TYPE_IDEAL_IDEAL
        ),

        TRANSACTION_CHANNEL_SC => array(
            TRANSACTION_TYPE_SC_SC
        ),

        TRANSACTION_CHANNEL_EGP => array(),
        TRANSACTION_TYPE_PAYPAL_PAYPAL => array(),
        TRANSACTION_CHANNEL_MERCADOPAGO => array(),
        TRANSACTION_CHANNEL_ASIABILL => array(),

        TRANSACTION_CHANNEL_PAYSSION => array(
        ),
        
    );

    const RETURN_CODE_SUCESS    = 200;//通过
    const RETURN_CODE_WHITE     = 201;//通过（白名单中）
    const RETURN_CODE_OFFLINE   = 202;//通过（线下支付）
    const RETURN_CODE_MISS      = 1000;//不通过（参数错误等payment那边的错误）
    const RETURN_CODE_FAILED    = 1001;//不通过（不通过或在黑名单中或进入以色列风控）
    const RETURN_CODE_EXCEPT    = 1002;//不通过（异常，风控这边的错误）

    const US_CURRENCY_CODE      = 'USD';

    //判断是否为线下支付
    public function isOffline($channel,$type){
       
        if( isset($this->offline[$channel]) && in_array($type, $this->offline[$channel]) ){
            return true;
        }
        return false;
    }

    /**
     * 获取白名单或黑名单数据 
     * @param type 类型；1为获取白名单，2为获取黑名单
     * @return int
     */
    public function isBlackOrWihte($data){
        $value = [$data['CustomerID'],$data['ShippingAddress']['Email']];
        if( !empty($data['BillingAddress']['Email']) ) $value[] = $data['BillingAddress']['Email'];
        if( !empty($data['CreditCard']['IssuingBank']) ) $value[] = $data['CreditCard']['IssuingBank'];
        if( !empty($data['CustomerIP']) ) $value[] = $data['CustomerIP'];

        $value = implode(',', $value);

        $where = array(
            'status' => 1,//1为使用中，2为废弃
            'type'   => 1,//2白名单 1黑名单
            'value'  => array('in',$value),
        );

        $listRes = model("SpecialListModel")->BlackWhiteList($where);
        
        if( empty($listRes) ){
            return 0;
        }

        $listRes = array_column($listRes, 'type');

        //如果有黑名单标志，则为黑名单
        if(in_array(RISK_BLACK_FLAG, $listRes)){
            return RISK_BLACK_FLAG;
        }
        return RISK_WHITE_FLAG;
    }

    /**
     * 事前操作记录
     * [BeforehandLog description]
     * @param array   $data   接收原始数据
     * @param string  $result 检测结果
     * @param integer $status 状态1、检查为正常，2、为白名单，3、黑名单，4、为线下免测，5为异常
     */
    public function addBeforehandInfo($data,$result='',$status=1){
        $time = time();
        //$data = modSensitiveData($data);//敏感数据脱敏a
        //dx_wind_control_special_beforehand table
        $whereBefore = array(
            'OrderNumber'       => $data['OrderNumber'],
            'AddTime'           => $time,
            'TransactionID'     => $data['TransactionID'],
            'TransactionChannel'=> $data['TransactionChannel'],
            'SiteID'            => $data['SiteID'],
            'CustomerID'        => $data['CustomerID'],
            'CurrencyCode'      => $data['CurrencyCode'],
            'Amount'            => $data['Amount'],
            'OrderType'         => !empty($data['OrderType'])?$data['OrderType']:'',
            'CardNumber'        => '',
            'Bin'               => !empty($data['Bin'])?$data['Bin']:'',
            'CustomerIP'        => $data['CustomerIP'],

            'ShippingEmail'     => $data['ShippingAddress']['Email'],
            'ShippingCity'      => $data['ShippingAddress']['City'],
            'ShippingStreet1'   => $data['ShippingAddress']['Street1'],
            'ShippingStreet2'   => !empty($data['ShippingAddress']['Street2'])?$data['ShippingAddress']['Street2']:'',

            'BillingEmail'      => !empty($data['BillingAddress']['Email'])?$data['BillingAddress']['Email']:'',
            'BillingCity'       => !empty($data['BillingAddress']['City'])?$data['BillingAddress']['City']:'',
            'BillingStreet1'    => !empty($data['BillingAddress']['Street1'])?$data['BillingAddress']['Street1']:'',
            'BillingStreet2'    => !empty($data['BillingAddress']['Street2'])?$data['BillingAddress']['Street2']:'',

            'RawData'           => json_encode($data),
            'Result'            => $result,
            'Status'            => $status,
        );

        //dx_wind_control_special_sku table
        $whereSku = [];
        foreach ($data['SkuInfos'] as $SkuInfos) {
            $whereSku[] = array(
                'BeforehandId'=> '',
                'OrderNumber' => $data['OrderNumber'],
                'ProductId'   => $SkuInfos['ProductId'],
                'SkuId'       => $SkuInfos['SkuId'],
                'SkuCode'     => $SkuInfos['SkuCode'],
                'Name'        => $SkuInfos['Name'],
                'UnitPrice'   => $SkuInfos['UnitPrice'],
                'Count'       => $SkuInfos['Count'],
                'AddTime'     => $time,
            );
        }

        //dx_wind_control_special_Card table
        $whereCard = [];

        
        //不能记录卡信息
        if( !empty($data['CreditCard']['Holder']) && !empty($data['CreditCard']['BinCode']) && !empty($data['CreditCard']['Last4Dig']) ){
            $whereCard = array(
                'BeforehandId'  => '',
                'Holder'        => $data['CreditCard']['Holder'],
                'BinCode'       => $data['CreditCard']['BinCode'],
                'LastFourDigit' => $data['CreditCard']['Last4Dig'],
                'Length'        => !empty($data['CreditCard']['Length'])?$data['CreditCard']['Length']:'',
                'AddTime'       => $time,
                'OrderNumber'   => $data['OrderNumber'],
            );
        }

        return model("SpecialListModel")->addBeforeLog($whereBefore,$whereSku,$whereCard);

    }

    public function inBlackAddress($data){
        //判断地址是否在黑名单,只判断小地址，国家，城市不用加入判断
        $address = [];

        if( !empty($data['BillingAddress']['Street1']) ) 
            $address[] = $data['BillingAddress']['Street1'];

        if( !empty($data['BillingAddress']['Street2']) && !in_array($data['BillingAddress']['Street2'], $address) )
            $address[] = $data['BillingAddress']['Street2'];

        if( !empty($data['ShippingAddress']['Street1']) && !in_array($data['ShippingAddress']['Street1'], $address) )
            $address[] = $data['ShippingAddress']['Street1'];

        if( !empty($data['ShippingAddress']['Street2']) && !in_array($data['ShippingAddress']['Street2'], $address) )
            $address[] = $data['ShippingAddress']['Street2'];

        if( !empty($data['ShippingAddress']['Street1']) && !empty($data['ShippingAddress']['Street2']) )
            $address[] = $data['ShippingAddress']['Street1'].','.$data['ShippingAddress']['Street2'];        

        $address = implode(',', $address);

        $where = array(
            'status' => 1,//1为使用中，2为废弃
            'type'   => 2,//1白名单 2黑名单
            'street'  => array('in',$address),
        );

        $AddressResult = model("SpecialListModel")->WindControlSpecialAddress($where);

        return $AddressResult;
    }

    public function addAfterhandInfo($data,$status=0,$msg=''){
        //$data = modSensitiveData($data);
        $time = time();
        $after = array(
            'OrderNumber'               => $data['OrderNumber'],
            'TransactionID'             => $data['TransactionID'],
            'SiteID'                    => $data['SiteID'],
            'CustomerID'                => $data['CustomerID'],
            'CustomerIP'                => $data['CustomerIP'],
            'CurrencyCode'              => $data['CurrencyCode'],
            'Amount'                    => $data['Amount'],
            'ExchangeRate'              => $data['ExchangeRate'],
            'AmountUsd'                 => $data['AmountUsd'],
            'OrderType'                 => $data['OrderType'],
            'PaymentChannel'            => $data['PaymentChannel'],
            'PaymentMethod'             => $data['PaymentMethod'],
            'ThirdPartyTxnID'           => $data['ThirdPartyTxnID'],
            'TxnResult'                 => $data['TxnResult'],
            'IsMvp'                     => $data['IsMvp'],
            'ThirdPartyRiskStatus'      => $data['ThirdPartyRiskStatus'],
            'ThidPartyRiskResult'       => $data['ThidPartyRiskResult'],
            'ShippAddressCountry'       => $data['ShippingAddress']['Country'],
            'ShippAddressState'         => $data['ShippingAddress']['State'],
            'ShippAddressCity'          => $data['ShippingAddress']['City'],
            'ShippAddressCountryName'   => $data['ShippingAddress']['CountryName'],
            'ShippAddressEmail'         => $data['ShippingAddress']['Email'],
            'ShippAddressFirstName'     => $data['ShippingAddress']['FirstName'],
            'ShippAddressLastName'      => $data['ShippingAddress']['LastName'],
            'ShippAddressPhone'         => $data['ShippingAddress']['Phone'],
            'ShippAddressZipCode'       => $data['ShippingAddress']['ZipCode'],
            'ShippAddressStreet1'       => $data['ShippingAddress']['Street1'],
            'ShippAddressStreet2'       => $data['ShippingAddress']['Street2'],
            'ShippAddressRate'          => $data['ShippingAddress']['Rate'],
            'BillingAddressCountry'     => $data['BillingAddress']['Country'],
            'BillingAddressState'       => $data['BillingAddress']['State'],
            'BillingAddressCity'        => $data['BillingAddress']['City'],
            'BillingAddressCountryName' => $data['BillingAddress']['CountryName'],
            'BillingAddressEmail'       => $data['BillingAddress']['Email'],
            'BillingAddressFirstName'   => $data['BillingAddress']['FirstName'],
            'BillingAddressLastName'    => $data['BillingAddress']['LastName'],
            'BillingAddressPhone'       => $data['BillingAddress']['Phone'],
            'BillingAddressZipCode'     => $data['BillingAddress']['ZipCode'],
            'BillingAddressStreet1'     => $data['BillingAddress']['Street1'],
            'BillingAddressStreet2'     => $data['BillingAddress']['Street2'],
            'BillingAddressRate'        => $data['BillingAddress']['Rate'],
            'AddTime'                   => $time,
            'DealWithStatus'            => $status,
            'Msg'                       => $msg,
        );
        
        $child = [];
        $list = $data['ChildOrderList'];

        if( !is_array($data['ChildOrderList']) ){
            $list = json_decode(htmlspecialchars_decode($data['ChildOrderList']),true);
        }
        
        foreach ($list as $value) {
            $child[] = array(
                'OrderNumber'        =>$value,
                'OrderMasterNumber'=>$data['OrderNumber'],
                'CustomerID'         =>$data['CustomerID'],
                'AddTime'           =>$time,
            );    
        }

        return model("SpecialListModel")->addAfterInfo($after,$child);
    }

    public function riskVerify($data,$afterId){
        $riskRes = ['code'=>self::RETURN_CODE_SUCESS,'msg'=>'该支付渠道无需进行事后风控校验'];

        $result = '';

        switch ($data['PaymentChannel']) {
            case TRANSACTION_CHANNEL_PAYPAL:
                $result = $this->paypalRisk($data,$afterId);
                break;
            case TRANSACTION_CHANNEL_EGP:
                $result = $this->egpRisk($data,$afterId);
                break;
            case TRANSACTION_CHANNEL_ASIABILL:
                $result = $this->asiabillRisk($data,$afterId);
                break;
            case TRANSACTION_CHANNEL_ASTROPAY:
            case TRANSACTION_CHANNEL_DLOCAL:
            case TRANSACTION_CHANNEL_PAGSMILE:
            case TRANSACTION_CHANNEL_MERCADOPAGO:
                $result = $this->otherRisk($data,$afterId);
                break;
            default:
                return $riskRes;
                break;
        }
        
        $time = time();

        //1.增加以色列风控请求结果记录 dx_wind_control_special_third_party_results
        $isr_data = [];
        if( !empty($result['isreali']) ){
            $res = $result['isreali']['content']['resource'][0];
            $isr_data = array(
                'OrderNumber'    => $data['OrderNumber'],
                'PaymentChannel' => $data['PaymentChannel'],
                'AfterwardsId'   => $afterId,
                'Decision'       => $res['Decision'],
                'Recommendation' => $res['recomm_code'],
                'DecisionDesc'   => $res['Decision_Desc'].'  '.$res['Decision_Desc_2'].'  '.$res['Decision_Desc_3'],
                'RawData'        => json_encode($result['isreali']),
                'AddTime'       => $time,
            );
        }

        $msg = '检测通过';
        if( !empty($result['msg']) ){
            $msg = implode(';', $result['msg']);
        }
        //修改事后风控记录 dx_wind_control_special_afterwards
        $after_data = array(
            'Code'  => $result['code'],
            'Msg'   => $msg,
        );

        $update_res = model("SpecialListModel")->updateAfterInfo($afterId,$after_data,$isr_data);
        if( !$update_res ){
            riskLog('error',__FILE__,__LINE__,'事后风控结果保存失败,afterId:'.$afterId,'事后风控结果保存失败,afterId:'.$afterId);
        }
        $riskRes = ['code'=>$result['code'],'msg'=>$msg];
        return $riskRes;
    }

    /*
     * paypal 有四种校验，而且都必须校验，记录四种校验结果，供业务参考
     * 1.大于300美元，进入人工审核
     * 2.大于等于10美元，进入以色列风控
     * 3.小于10美金，且是乌克兰国家，当天第二单进入人工；5天内第三单进入人工
     */
    public function paypalRisk($data,$afterId){
        $riskRes = array(
            'code' => self::RETURN_CODE_SUCESS,
            'msg'  => [],
            'isreali' => '',
        );

        $config = config("payment_channel_config.".$data['PaymentChannel']);
        if( empty($config) || empty($config['max_amount']) || empty($config['israeli_max_amount']) || empty($config['is_check_isaeli']) || empty($config['special_country']) ){
            riskLog('error',__FILE__,__LINE__,'事后风控配置为空',$data['PaymentChannel']);
            $riskRes['code'] = self::RETURN_CODE_EXCEPT;
            $riskRes['msg'][] = $data['PaymentChannel'].'事后风控配置为空';
            return $riskRes;
        }
        //非美元转换成美元
        $amount = $data['Amount'];
        if( $data['CurrencyCode'] != self::US_CURRENCY_CODE ){
            $amount = sprintf("%.2f",$data['Amount']/$data['ExchangeRate']);
        }

        //1.大于300美元，进入人工审核
        if( $amount > $config['max_amount'] ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = '金额大于'.$config['max_amount'].'美元';
        }

        //2.大于等于10美元，进入以色列风控
        if( $amount >= $config['israeli_max_amount'] && $config['is_check_isaeli'] ){
            $israeliRes = $this->IsraeliRiskControl($data,$afterId);
            if( !empty($israeliRes) ){
                $code = $israeliRes['content']['resource'][0]['recomm_code'];
                $msg  = $israeliRes['content']['resource'][0]['recommendation'];
                if( $code != '0' ){
                    $riskRes['code'] = self::RETURN_CODE_FAILED;
                    $riskRes['msg'][] = '以色列风控建议：'.$msg;
                }
                $riskRes['isreali'] = $israeliRes;
            }else{
                $riskRes['code'] = self::RETURN_CODE_FAILED;
                $riskRes['msg'][] = '以色列风控请求失败';
            }
        }

        //3.特殊国家的特殊校验
        $country = $data['ShippingAddress']['Country'];
        if( empty($config['special_country']) || empty($config['special_country'][$country]) ){
            return $riskRes;
        }

        $country_conf = $config['special_country'][$country];

        $days = $country_conf['other_day'];
        $time = strtotime(date('Y-m-d'));

        $time = $time-$days*24*3600;
        $where = ['CustomerID' => $data['CustomerID']];

        //获取$time天内的订单数量
        $res = model("SpecialListModel")->getOrderCount($where,$time);
        if( empty($res) ){
            return $riskRes;
        }

        $res = array_column($res,"AddTime");

        //本次事后风控请求已经录入数据库，不能忽略此条记录，所以要减少一条查询记录数量
        if( count($res)>$country_conf['other_day_span_max']+1 ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = $days.'天内订单量大于阈值'.$country_conf['other_day_span_max'];
        }else{
            $num = 0;//当天订单数量
            foreach ($res as $v) {
                $tonight = $time+24*3600;
                if( $v>$time && $v<$tonight ){
                    $num++;
                }
            }
            //本次事后风控请求已经录入数据库，不能忽略此条记录，所以要减少一条查询记录数量
            if( $num > $country_conf['one_day_span_max']+1 ){
                $riskRes['code'] = self::RETURN_CODE_FAILED;
                $riskRes['msg'][] = '1天内订单量大于阈值'.$country_conf['one_day_span_max'];
            }
        }

        return $riskRes;
    }

    /*
     * 1. 判断第三方是否进入风控
     * 2. 0<x<=40 && 高危国家或城市
     */
    public function egpRisk($data,$afterId){
        $riskRes = array(
            'code' => self::RETURN_CODE_SUCESS,
            'msg'  => [],
        );

        //1.如果第三方进入风控
        if( $data['ThirdPartyRiskStatus'] == '2' ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = '进入第三方风控:'.$data['ThidPartyRiskResult'];
        }

        $config = config("payment_channel_config.".$data['PaymentChannel']);
        if( empty($config) || empty($config['max_amount']) || empty($config['order_num_day_max']) || empty($config['max_risk_amount']) ){
            riskLog('error',__FILE__,__LINE__,'事后风控配置为空',$data['PaymentChannel']);
            $riskRes['code'] = self::RETURN_CODE_EXCEPT;
            $riskRes['msg'][] = $data['PaymentChannel'].'事后风控配置为空';
            return $riskRes;
        }
        //非美元转换成美元
        $amount = $data['Amount'];
        if( $data['CurrencyCode'] != self::US_CURRENCY_CODE ){
            $amount = sprintf("%.2f",$data['Amount']/$data['ExchangeRate']);
        }
        //2. 0<x<=40 && 高危国家或城市
        if( $amount < $config['max_amount'] ){
            //将城市转换成小写
            $city = preg_replace_callback('/[A-Z]/',function($matches){
                return strtolower($matches[0]);
            },$data['ShippingAddress']['City']);

            $country = $data['ShippingAddress']['Country'];

            $res = model("SpecialListModel")->getRiskCountryOrCity($country,$city);
            if($res){
                $riskRes['code'] = self::RETURN_CODE_FAILED;
                $riskRes['msg'][] = "高危城市或国家：{$country}-{$city}";
            }
        }

        //3.获取一天内订单数量
        $time = strtotime(date('Y-m-d'));
        $where = ['CustomerID' => $data['CustomerID']];
        $res = model("SpecialListModel")->getOrderCount($where,$time,'OrderNumber');
        if( empty($res) ){
            return $riskRes;
        }
        if( count($res)>$config['order_num_day_max']+1 ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = "一天订单量大于阈值：{$config['order_num_day_max']}";
        }

        //4.大于100美金，进入人工审核
        if( $amount > $config['max_risk_amount'] ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = "金额大于：".$config['max_risk_amount'].'美金，高风险交易';
        }

        return $riskRes;
    }

    public function asiabillRisk($data,$afterId){
        //目前和egp是一样的校验规则
        return $this->egpRisk($data,$afterId);
    }

    /**
     * astropay,pagsmile,mercadopago 事后风控校验
     * 1. 订单总数和交易总数校验
     * 2. 0<x<=60 && 高危国家或城市
     * 3. 金额大于100美金的 进入人工审核
     */
    public function otherRisk($data,$afterId){
        $riskRes = array(
            'code' => self::RETURN_CODE_SUCESS,
            'msg'  => [],
        );

        $config = config("payment_channel_config.".$data['PaymentChannel']);
        if( empty($config) ||empty($config['same_num_day_max']) ||empty($config['order_num_day_max']) || empty($config['max_amount']) || empty($config['max_risk_amount']) ){
            riskLog('error',__FILE__,__LINE__,'事后风控配置为空',$data['PaymentChannel']);
            $riskRes['code'] = self::RETURN_CODE_EXCEPT;
            $riskRes['msg'][] = $data['PaymentChannel'].'事后风控配置为空';
            return $riskRes;
        }

        //获取一天内订单数量
        $time = strtotime(date('Y-m-d'));
        $where = ['CustomerID' => $data['CustomerID']];
        $res = model("SpecialListModel")->getOrderCount($where,$time,'OrderNumber');
        if( empty($res) ){
            return $riskRes;
        }

        $res = array_column($res, 'OrderNumber');

        $max = 0;//记录最大交易数
        $nums = [];//记录个订单交易总数
        foreach ($res as $value) {
            if( !isset($nums[$value]) )  {
                $nums[$value]=1;
            }else{
                $nums[$value]++;
            }
            if( $nums[$value]>$max ) $max = $nums[$value];
        }
        //1.一天内最大交易次数
        //本次事后风控请求已经录入数据库，不能忽略此条记录，所以要减少一条查询记录数量
        if( $max>$config['same_num_day_max']+1 ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = '1天内同一订单次数大于阈值'.$config['same_num_day_max'];
        }else if( count($nums)>$config['order_num_day_max']+1 ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = '1天内订单数大于阈值'.$config['order_num_day_max'];
        }

        //非美元转换成美元
        $amount = $data['Amount'];
        if( $data['CurrencyCode'] != self::US_CURRENCY_CODE ){
            $amount = sprintf("%.2f",$data['Amount']/$data['ExchangeRate']);
        }
        //2. 0<x<=60 && 高危国家或城市
        if( $amount < $config['max_amount'] ){
            //将城市转换成小写
            $city = preg_replace_callback('/[A-Z]/',function($matches){
                return strtolower($matches[0]);
            },$data['ShippingAddress']['City']);

            $country = $data['ShippingAddress']['Country'];

            $res = model("SpecialListModel")->getRiskCountryOrCity($country,$city);
            if($res){
                $riskRes['code'] = self::RETURN_CODE_FAILED;
                $riskRes['msg'][] = "高危城市或国家：{$country}-{$city}";
            }
        }
        //3.大于100美金，进入人工审核
        if( $amount > $config['max_risk_amount'] ){
            $riskRes['code'] = self::RETURN_CODE_FAILED;
            $riskRes['msg'][] = "金额大于：".$config['max_risk_amount'].'美金，高风险交易';
        }

        return $riskRes;
    }

    /**
     * 根据不同渠道进行检查
     * 1:Paypal;2:EGP;3:Asiabill;4:Astropay;6:Pagsmile;
     * 
     * 1.检查付款频率 付款频率库包含Bin，Ip Address，CardHolder，Email，Billing Address，Shipping Address，分别检查当前付款信息与历史付款频率信息之和是否达到限制值，若达到则拒绝此次付款。（历史付款频率信息来自每一次成功付款的累计，以天为单位）
     * 2.检查付款金额阈值
     * 3.检查ip-账单国家、bin-账单国家、账单国家-收货国家 等信息是否匹配
     * 4.检查高风险国家：账单地址、收货地址
     * 5.检查email有效性
     * 6.检查address有效性。目前只有美国，加拿大，英国可检查
     * 7.检查邮编的有效性
     * 8.检查第三方支付投诉及退款频率
     * 9.检查货品重寄频率
     * 10.用户下单购买美元（或欧元、墨西哥币）商品，金额折算后超过100美金，使用Paypal支付的订单进入cybs检测
     */
    public function WindControlPaymentMethodRule($data){
        //查询支付频率


        //根据配置文件获取对应渠道
        $config = config('WindControlPaymentMethodRule.'.$data['PaymentChannel']);

        //判断城市是否为高风险城市
        $sa_city = model("SpecialListModel")->HighRiskCity($data['ShippingAddress']['City']);
        if(!empty($sa_city)){
            return ['code'=>1001, 'msg'=>'城市'.$data['sa_city'].'为高风险城市'];
        }
        
        //判断是否需要判断最低价格
        if(isset($config['SetHighRiskAmountMin']) && $config['SetHighRiskAmountMin']>=$data['Amount']){
           return ['code'=>1001, 'msg'=>'当前价格低于或等'.$config['SetHighRiskAmountMin'].'进入风控'];
        }
        //判断是否需要判断最最大价格
        if(isset($config['SetHighRiskAmountMax']) && $config['SetHighRiskAmountMax']<=$data['Amount']){
           return ['code'=>1001, 'msg'=>'当前价格大于或等'.$config['SetHighRiskAmountMax'].'进入风控'];
        }

        if( empty($config['IsCheckOrdersCount']) ){
            return ['code'=>200,'msg'=>'验证通过'];
        }
        
        /*
        'Status'=>1,
        'SetCountMax'   =>6,    //在某一时间最大下单量
        'SetAmountMax'  =>1.5,  //订单金额小于此参数
        'SetHourSpanMax'=>1,    //几个小时内限制下单
        'SetTxnCountMax'=>10,   //支付次数
        'SeCountryName' =>"TR", //限制的国家
        'SetDaySpanMax' =>1,    //在SetDaySpanMax天数内，客户端单个订单的最大交易数量
        */
        /*
        foreach($config['IsCheckOrdersCount'] as $k => $v) {
            if( empty($v['Status']) || $v['Status'] !=1 ){
               continue;
            }
            if( !empty($v['SeCountryName']) && $v['SeCountryName'] != $data['ShippingAddress']['Country'] ){
               continue;
            }


            $SetDaySpanMax = '';
            $SetHourSpanMax = '';
            $SetCountMax = 0;

            $where = [];
            $where['CustomerID'] = $data['CustomerID'];
            $where['CurrencyCode'] = $data['CurrencyCode'];
            

            //判断价格是否大于限定最小价格，达到进入判断
            if( !empty($v['SetAmountMax']) && $v['SetAmountMax'] > $data['Amount'] ){
                return ['code'=>1001, 'msg'=>'当前价格小于或等于'.$v['SetAmountMax'].'进入风控'];
            }

            //多少小时
            if(!empty($v['SetHourSpanMax'])){
              // date_default_timezone_set('PRC');
              $SetHourSpanMax = strtotime("-1 hour");
              $where['add_time']   = ['egt',$SetHourSpanMax];
              pr(date("Y-m-d H:i:s", strtotime("-1 hour")));
              pr(date("Y-m-d H:i:s", time()));
            }
            if(!empty($v['SetCountMax'])){
               $SetCountMax = $v['SetCountMax'];
            }
            
            if(!empty($v['SetDaySpanMax'])){
               $SetDaySpanMax = $v['SetDaySpanMax'];
               //等于1为当前天
               if($SetDaySpanMax == 1){
                   $where['add_time']   = ['egt',strtotime("0 day 00:00:00")];
               }else{
                   $where['add_time']   = ['egt',strtotime(-$SetDaySpanMax." day 00:00:00")];
               }
            }

            $result = model("SpecialListModel")->WindControlPaymentMethodRule($where,$data,$v);

            if($result['code'] == 1001){
               return $result;
            }
        }
        */
        /*
        $israeliRes = null;
        if( $data['PaymentChannel'] == TRANSACTION_CHANNEL_PAYPAL ){
            $israeliRes = $this->IsraeliRiskControl($data);
        }
        */
    }

    /**
     * 以色列风控检查
     */
    public function IsraeliRiskControl($data,$afterId){
        $config  = config('israeli_risk_config');
        $api_key = $config['api_key'];
        $ship_id = $config['shop_id'];

        $header = array(
            "Content-type:application/json",
            "X-DreamFactory-Api-Key:".$api_key,
        );

        $where = array(
            'ID'                => $data["TransactionID"],
            'Shop_id'           => $ship_id,
            'p_attempt'         => 10,
            'created_at'        => date('Y-m-d H:i:s'),
            'p_status'          => 1,//PaymentStatus–1Success,2Fail
            /*Hasthecustomer(after being requested by us or in dependently)provided additional identification
            1-IdentityCard
            2-Passport
            3-SMS/3dsecure
            4-Creditcardphoto
            */
            'id_verification_code' => "0",
            'email'             => $data['ShippingAddress']['Email'],
            'total_price_usd'   => sprintf("%.2f",$data['Amount']/$data['ExchangeRate']),//总价格以美元计算
            'currency'          => $data['CurrencyCode'],//交易货币
            'browser_ip'        => $data['CustomerIP'],//用户的IP地址

            //'userid'            => $data['ShippingAddress']['CpfNo'],//证件号码。用户（如果有）
            'ba_first_name'     => $data['BillingAddress']['FirstName'],//名字
            'ba_last_name'      => $data['BillingAddress']['LastName'],//姓氏
            'ba_address1'       => $data['BillingAddress']['Street1'],//地址一
            'ba_address2'       => $data['BillingAddress']['Street2'],//地址二
            'ba_address_state'  => $data['BillingAddress']['State'],//省
            'ba_city'           => $data['BillingAddress']['City'],//市
            'ba_country'        => $data['BillingAddress']['CountryName'],//国家/地区
            'ba_phone'          => $data['BillingAddress']['Phone'],//电话
            'ba_zip_code'       => $data['BillingAddress']['ZipCode'],//帐单邮寄地址
            
            'sa_first_name'     => $data['ShippingAddress']['FirstName'],//送货地址，名字
            'sa_last_name'      => $data['ShippingAddress']['LastName'],//送货地址，姓氏
            'sa_address1'       => $data['ShippingAddress']['Street1'],//送货地址，地址1
            'sa_address2'       => $data['ShippingAddress']['Street2'],//送货地址，地址2
            'sa_address_state'  => $data['ShippingAddress']['State'],//送货地址，州
            'sa_city'           => $data['ShippingAddress']['City'],//送货地址，城市
            'sa_country'        => $data['ShippingAddress']['CountryName'],//送货地址，国家
            'sa_phone'          => $data['ShippingAddress']['Phone'],//送货地址，电话
            'sa_zip_code'       => $data['ShippingAddress']['ZipCode'],//送货地址，邮政编码
            'sa_email'          => $data['ShippingAddress']['Email'],//送货地址，电子邮件
          
        );
        $data = array('resource'=>array($where));

        riskLog('info',__FILE__,__LINE__,'israeli request data,afterId:'.$afterId,$data);        

        $result = doCurl($config['url'],$data,null,true,$header);

        if( !isset($result['status_code']) ){
            riskLog('error',__FILE__,__LINE__,'israeli request error,afterId:'.$afterId,'israeli request error');
            return [];
        }

        riskLog('info',__FILE__,__LINE__,'israeli request result,afterId:'.$afterId,$result);

        if( $result['status_code'] != 200 || !isset($result['content']['resource'][0]['recomm_code']) ){
            return [];
        }
        
        return $result;
        
    }
}
