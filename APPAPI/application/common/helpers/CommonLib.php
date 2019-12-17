<?php

namespace app\common\helpers;

use app\app\model\SysConfigModel;
use app\mallextend\model\CouponModel;
use app\mallextend\model\ProductModel;
use think;


class CommonLib
{

    /**
     * 替换掉数组中的emoji表情
     * @param $arrayString
     * @param string $replaceTo
     * @return mixed|string
     */
    public static function filterEmojiDeep($arrayString, $replaceTo = '?')
    {
        if (is_string($arrayString)) {
            return self::filterEmoji($arrayString, $replaceTo);
        } else if (is_array($arrayString)) {
            foreach ($arrayString as &$array) {
                if (is_array($array) || is_string($array)) {
                    $array = self::filterEmojiDeep($array, $replaceTo);
                } else {
                    $array = $array;
                }
            }
        }
        return $arrayString;
    }

    /**
     * 替换掉emoji表情
     * @param $text
     * @param string $replaceTo
     * @return mixed|string
     */
    public static function filterEmoji($text, $replaceTo = '?')
    {
        $clean_text = "";
        // Match Emoticons
        $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
        $clean_text = preg_replace($regexEmoticons, $replaceTo, $text);
        // Match Miscellaneous Symbols and Pictographs
        $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
        $clean_text = preg_replace($regexSymbols, $replaceTo, $clean_text);
        // Match Transport And Map Symbols
        $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
        $clean_text = preg_replace($regexTransport, $replaceTo, $clean_text);
        // Match Miscellaneous Symbols
        $regexMisc = '/[\x{2600}-\x{26FF}]/u';
        $clean_text = preg_replace($regexMisc, $replaceTo, $clean_text);
        // Match Dingbats
        $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
        $clean_text = preg_replace($regexDingbats, $replaceTo, $clean_text);
        return $clean_text;
    }

    /**
     * 生成guid
     * @param $cut 是否删除中杠
     * @return string
     */
    public static function createGuid($cut = false)
    {
        $charid = strtoupper(md5(uniqid(mt_rand(), true)));
        $hyphen = chr(45);// "-"
        $uuid = ''
            . substr($charid, 0, 8) . $hyphen
            . substr($charid, 8, 4) . $hyphen
            . substr($charid, 12, 4) . $hyphen
            . substr($charid, 16, 4) . $hyphen
            . substr($charid, 20, 12);
        if ($cut) {
            return str_replace('-', '', strtolower($uuid));
        }
        return strtolower($uuid);
    }

    /**
     * 生成随机码
     * @param $type
     * @param $len
     * @return string
     */
    public static function createRandomStrByType($type = 'number', $len = 6)
    {
        switch ($type) {
            case 'number':
                $no = '123456789012345678901234567890123456789';
                $rand = substr(str_shuffle($no), 0, $len);
                break;
            case 'letter':
                $no = 'QWERTYUIOPASDFGHJKLZXCVBNMqwertyuiopasdfghjklzxcvbnm';
                $rand = substr(str_shuffle($no), 0, $len);
                break;
            case 'mix':
                $no = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                $rand = substr(str_shuffle($no), 0, $len);
                break;
            default:
                $no = '123456789012345678901234567890123456789';
                $rand = substr(str_shuffle($no), 0, $len);
                break;
        }
        return $rand;
    }

    /**
     * params 参数处理
     * @param $form
     * @param array $replaceParams
     * @return mixed
     */
    public static function handleForm($form, $replaceParams = array())
    {
        $formData = self::objecttoarray($form);
        self::replaceParams($formData, $replaceParams);
        self::filterNullValue($formData);
        return $formData;
    }

    public static  function objecttoarray($object) {
        if (is_object($object)) {
            foreach ($object as $key => $value) {
                $array[$key] = $value;
            }
        }
        else {
            $array = $object;
        }
        return $array;
    }

    /**
     * 表单校验去除NULL值
     * @param $form
     */
    public static function replaceParams(&$form, $replaceParams)
    {
        if (is_array($replaceParams) && count($replaceParams)) {
            $newFormData = array();
            foreach ($form as $key => $val) {
                if (array_key_exists($key, $replaceParams)) {
                    $newFormData[$key] = $replaceParams[$key];
                }
            }
            $form = $newFormData;
        }
    }

    /**
     * 数组去除NULL值
     * @param $arr
     */
    public static function filterNullValue(&$arr)
    {
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                self::filterNullValue($arr[$key]);
            } elseif (is_null($val)) {
                unset($arr[$key]);
            }elseif ( $val== "") {
                unset($arr[$key]);
            }
        }
    }

    /**
     * 返回二维数组中的某列
     * @param string $key
     * @param array $inputs
     * @return array
     */
    public static function getColumn($key, $inputs)
    {
        $res = [];
        if (function_exists('array_column')) {
            $res = array_column($inputs, $key);
        } else {
            foreach ($inputs as $input) {
                $res[] = $input[$key];
            }
        }
        return $res;
    }

    /**
     * 获取数组指定的key
     * @param $data
     * @return array
     */
    public static function filterSpecialKey(array $data, $key = 'id')
    {
        $ret = [];
        if ($key) {
            $key = explode('.', $key);
            foreach ($data as $value) {
                $item = self::findKeyExist($value, $key, 0);
                if ($item !== false) {
                    $ret[] = $item;
                }
            }
        }
        return $ret;
    }

    /**
     * 用于递归查找key是否存在
     * @param $value
     * @param $key
     * @param $currentCount
     * @return bool
     */
    private static function findKeyExist($value, $key, $currentCount)
    {
        if ((count($key) == ($currentCount + 1)) && isset($value[$key[$currentCount]])) {
            return $value[$key[$currentCount]];
        } elseif ((count($key) > ($currentCount + 1)) && isset($value[$key[$currentCount]])) {
            return self::findKeyExist($value[$key[$currentCount]], $key, ++$currentCount);
        } else {
            return false;
        }

    }

    /**
     * 在数组里面查找某个键的值是否存在
     * @param $arr 要查询的数组
     * @param $key 要查询的键
     * @param $value 要查询的键值
     * @return bool
     */
    public static function findKeyValueExist($arr, $key, $value)
    {
        if (empty($arr))
            return false;
        foreach ($arr as $row) {
            if ($row[$key] == $value) {
                return $row;
            }
        }
        return false;
    }

    /**
     * 数字格式化
     * @param $number
     * @param int $decimals
     * @param string $dec_point
     * @param string $thousands_sep
     * @return string
     */
    public static function numberFormat($number, $decimals = 2, $dec_point = '.', $thousands_sep = '')
    {
        return number_format($number, $decimals, $dec_point, $thousands_sep);
    }

    /**
     * md5后转大写后按方向截取制定长度
     * @param string $string 需加密的串
     * @param int $length 需返回的长度
     * @param int $type 1.从右边，2.从左边
     * @return string
     */
    public static function md5Sub($string, $length, $type = 1)
    {
        if ($type == 1) {
            return substr(strtoupper(md5($string)), -$length);
        } else {
            return substr(strtoupper(md5($string)), $length);
        }
    }


    /**
     * 两个日期这间的间隔天数
     * @param $date1
     * @param $date2
     * @return float
     */
    public static function daysBetweenDates($date1, $date2)
    {
        $days = floor(abs($date1 - $date2) / 86400);
        return $days;
    }

    /**
     * 获取两个时间间隔之间的所有日期
     * @param string|int $minDay like "2015-05-05" or unix_timestamp
     * @param string|int $maxDay like "2015-06-05" or unix_timestamp
     * @return array  ['2015-050-05','2015-05-06',...]
     */
    public static function getAllDays($minDay, $maxDay)
    {
        $minTime = is_numeric($minDay) ? intval($minDay) : strtotime($minDay);
        $maxTime = is_numeric($maxDay) ? intval($maxDay) : strtotime($maxDay);
        $res = [];
        for ($current = $minTime; $current <= $maxTime; $current = strtotime(date("Y-m-d", $current) . " +1 day")) {
            $res[] = date("Y-m-d", $current);
        }
        return $res;
    }

    /**
     * 获取两个时间间隔之间的所有星期 (当年的第几周)
     * @param string|int $minDay like "2015-05-05" or unix_timestamp
     * @param string|int $maxDay like "2015-06-05" or unix_timestamp
     * @return array  ['2015 05','2015 06',...]
     */
    public static function getAllWeeks($minDay, $maxDay)
    {
        $minTime = is_numeric($minDay) ? intval($minDay) : strtotime($minDay);
        $maxTime = is_numeric($maxDay) ? intval($maxDay) : strtotime($maxDay);
        $year = date("Y", $minTime);
        $week = intval(date("N", $minTime));
        $startTime = $minTime - ($week - 1) * 86400;
        $startYear = date("Y", $startTime);
        if ($startYear != $year) {
            $startTime = strtotime("{$year}-01-01");
        }
        $endWeek = intval(date("N", $maxTime));
        $endTime = $maxTime + (7 - $endWeek) * 86400;
        $res = [];
        for ($current = $startTime; $current <= $endTime; $current = strtotime(date("Y-m-d", $current) . " +1 week")) {
            $res[] = date("Y W", $current);
        }
        return $res;
    }

    /**
     * 获取两个时间间隔之间的所有星期 (第几周)
     * @param string|int $minDay like "2015-05-05" or unix_timestamp
     * @param string|int $maxDay like "2015-06-05" or unix_timestamp
     * @return array  ['2015-05','2015-06',...]
     */
    public static function getAllMonth($minDay, $maxDay)
    {
        $minTime = is_numeric($minDay) ? intval($minDay) : strtotime($minDay);
        $maxTime = is_numeric($maxDay) ? intval($maxDay) : strtotime($maxDay);
        $startTime = strtotime(date("Y-m-01", $minTime));
        $res = [];
        for ($current = $startTime; $current <= $maxTime; $current = strtotime(date("Y-m-d", $current) . " +1 month")) {
            $res[] = date("Y-m", $current);
        }
        return $res;
    }

    /**
     * 生成指定长度数字/字母/数字字母混合串
     * @param $length
     * @param int $type 1/2/3
     * @return string
     */
    public static function randomStr($length, $type = 1)
    {
        if (!$length) {
            return '';
        }
        $strNum = '0123456789';
        $strLetter = 'abcdefghijklmnopqrst';
        $ret = '';
        $randStr = '';
        switch ($type) {
            case 1:
                $randStr = $strNum;
                break;
            case 2:
                $randStr = $strLetter;
                break;
            case 3:
                $randStr = $strNum . $strLetter;
                break;
        }
        $strLength = strlen($randStr);
        for ($i = 0; $i < $length; $i++) {
            $ret .= $randStr[mt_rand(0, $strLength - 1)];
        }
        return $ret;
    }

    /**
     * 将数组某列元素转化为key值，用作map
     */
    public static function columnToKey($column_name, $inputs = array())
    {
        $result = array();
        if (!empty($inputs)) {
            $count = count($inputs);
            for ($i = 0; $i < $count; $i++) {
                $result[$inputs[$i][$column_name]] = $inputs[$i];
            }
        }
        return $result;
    }

    /**
     * 获取mysql的datetime (保留6位小数)
     * @return string
     */
    public static function getMysqlDatetime()
    {
        list($micro, $sec) = explode(" ", microtime());
        return date("Y-m-d H:i:s", $sec) . '.' . str_pad(substr($micro, 2, 6), 6, "0", STR_PAD_RIGHT);
    }

    /**
     * 去掉空值
     * @param $data
     * @return array
     */
    public static function filterNull($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        $res = [];
        foreach ($data as $key => $value) {
            if (!is_null($value)) {
                if (is_array($value)) {
                    $res[$key] = self::filterNull($value);
                } else {
                    $res[$key] = $value;
                }
            }
        }
        return $res;
    }

    /**
     * 截取中文字符加省略号
     * @param $string
     * @param $start
     * @param null $length
     * @param string $suffix
     * @return bool|string
     */
    public static function mbSubString($string, $start, $length = null, $suffix = '...')
    {
        if ($length !== null) {
            $substr = self::utf8Substr($string, $start, $length);
            $str_length = self::absLength($string);
            if ($str_length > $length) {
                $substr .= $suffix;
            }
        } else {
            $substr = self::utf8Substr($string, $start);
        }
        return $substr;
    }


    /**
     * utf-8编码下截取中文字符串,参数可以参照substr函数
     * @param $str 要进行截取的字符串
     * @param int $start 要进行截取的开始位置，负数为反向截取
     * @param $end 要进行截取的长度
     * @return bool|string
     */
    public static function utf8Substr($str, $start = 0, $end)
    {
        if (empty($str)) {
            return false;
        }
        if (function_exists('mb_substr')) {
            if (func_num_args() >= 3) {
                $end = func_get_arg(2);
                return mb_substr($str, $start, $end, 'utf-8');
            } else {
                mb_internal_encoding("UTF-8");
                return mb_substr($str, $start);
            }

        } else {
            $null = "";
            preg_match_all("/./u", $str, $ar);
            if (func_num_args() >= 3) {
                $end = func_get_arg(2);
                return join($null, array_slice($ar[0], $start, $end));
            } else {
                return join($null, array_slice($ar[0], $start));
            }
        }
    }

    /**
     * 可以统计中文字符串长度的函数
     * @param $str 要计算长度的字符串,一个中文算一个字符
     * @return int
     */
    public static function absLength($str)
    {
        if (empty($str)) {
            return 0;
        }
        if (function_exists('mb_strlen')) {
            return mb_strlen($str, 'utf-8');
        } else {
            preg_match_all("/./u", $str, $ar);
            return count($ar[0]);
        }
    }

    /**
     * 把金额由元转为分
     */
    public static function amountToFen($amount)
    {
        $amount = $amount * 100;
        if (strpos($amount, '.') !== false) {
            return ceil($amount);
        }
        return $amount;
    }


    /**
     *特殊字符过滤方法
     * @param $strParam 数组或者参数
     * @return array|mixed
     */
    public static function replaceSpecialChar($strParam){
        if (!is_array($strParam)) {
            return self::_SpecialChar($strParam);
        }
        $res = [];
        foreach ($strParam as $key => $value) {
            if (is_array($value)) {
                $res[$key] = self::replaceSpecialChar($value);
            } else {
                $res[$key] = self::_SpecialChar($value);
            }
        }
        return $res;

    }

    /**
     * 特殊字符过滤方法
     * @param $strParam
     * @return mixed
     */
    public static function _SpecialChar($strParam){
        //过滤特殊字符
        $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
        $string = preg_replace($regex,"",$strParam);
        //过滤空格，制表符
        $search = array(" ","　","\n","\r","\t");
        $replace = array("","","","","");
        return str_replace($search, $replace, $string);
    }

    public static function getMsecTime() {
        $timeArray = explode(' ', microtime());
        return (float)sprintf('%.0f', (floatval($timeArray[0]) + floatval($timeArray[1])) * 1000);
    }

    /**
     * 过滤掉敏感信息
     * @param array $sensitive
     * @param $arr
     */
    public static function removeSensitive($sensitive = [], &$arr)
    {
        foreach ($sensitive as $sensitiveVal) {
            if (isset($arr[$sensitiveVal])) {
                unset($arr[$sensitiveVal]);
            }
        }
        foreach ($arr as $key => $val) {
            foreach ($sensitive as $sensitiveVal) {
                if (isset($val[$sensitiveVal])) {
                    unset($val[$sensitiveVal]);
                }
            }
            if (is_array($val)) {
                self::removeSensitive($sensitive, $arr[$key]);
            }
        }
    }

    //去重
    public static function array_unset_repeat($arr,$key){
        $res = array();
        foreach ($arr as $k=>$value) {
            if(isset($res[$value[$key]])){
                unset($value[$key]);
            }
            else{
                $res[$value[$key]] = $value;
            }
        }
        return $res;
    }

    //字符串转整型
    public static function array_string_int($arrs,$key=null){
        $ids = array();
        if(is_null($key)){
            foreach ($arrs as $arr) {
                $ids[] = (int)$arr;
            }
        }else{
            foreach($arrs as $arr){
                $ids[] = (int)$arr[$key];
            }
        }
        return $ids;
    }

    /**
     * 数据组装
     * where['key']=>['in'=>[1,2]]
     * @param $params
     * @param null $key
     * @return array|int
     */
    public static function supportArray($params,$key=null){
        $where = [];
        $ids= [];
        if(is_null($key)){
            if(is_array($params)) {
                foreach ($params as $arr) {
                    //过滤数组0的数据
                    if($arr == 0){
                        continue;
                    }
                    $ids[] = (int)$arr;
                }
                $where = ['in',$ids];
            }else{
                $where = (int)$params;
            }
        }else{
            if(is_array($params[$key])){
                foreach($params[$key] as $arr){
                    $ids[] = (int)$arr;
                }
                $where = ['in',$ids];
            }else{
                $where = is_array($params) ? $params[$key] : (int)$params;
            }
        }
        return $where;
    }
    /**
     * 数据组装数组字符串类型
     * where['key']=>['in'=>['1','2']]
     * @param $params
     * @param null $key
     * @return array|string
     */
    public static function supportArrayString($params,$key=null){
        $where = [];
        if(is_null($key)){
            if(is_array($params)) {
                foreach ($params as $arr) {
                    $ids[] = (string)$arr;
                }
                $where = ['in',$ids];
            }
        }else{
            if(is_array($params[$key])){
                foreach($params[$key] as $arr){
                    $ids[] = (string)$arr;
                }
                $where = ['in',$ids];
            }else{
                $where = is_array($params) ? $params[$key] : $params;
            }
        }
        return $where;
    }

    /**
     * 数组随机抽取个数，重新组合
     * @param array $arr 数组
     * @param int $count 随机个数
     * @return array 返回随机取数数组
     */
    public static function getRandArray($arr,$count = 0){
        $newArray = [];
        if(is_array($arr)){
            if(count($arr) > $count){
                $rand_keys  = array_rand($arr,$count);
                if(is_array($rand_keys)){
                    foreach($rand_keys as $key){
                        $newArray[] = $arr[$key];
                    }
                    return $newArray;
                }
                return $arr[$rand_keys];
            }else{
                return $arr;
            }
        }
        return $newArray;
    }

    /**
     * 过滤查找
     */
    public static function filterArrayByKey($input,$key,$val,$key1=null,$val1=null){
        $retArray = array_filter($input, function($t) use ($key,$val,$key1,$val1){
            if($t[$key] == $val){
                if(!is_null($key1)){
                    return $t[$key1] == $val1;
                }
                return $t[$key] == $val;
            }
        });
        if(count($retArray)== count($retArray, 1)){
            return $retArray;
        }else{
            return array_shift($retArray);
        }
    }


    /**
     * 处理标题，大写转小写，加横杆
     */
    public static function filterTitle($strParam){
        //过滤特殊字符
        $regex = "/\/|\~|\!|\@|\&|\#|\\$|\%|\^|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\’|\`|\-|\=|\\\|\|/";
        $string = preg_replace($regex,"",strtolower($strParam));
        $string = preg_replace ( "/\s(?=\s)/","\\1", $string );//多个连续空格
        //空格替换横线
        $search = array(" ","　");
        $replace = array("-","-");
        return str_replace($search, $replace, $string);
    }

    /**
     * 获取订单状态
     * @param int $code 订单状态code
     * @return array|mixed
     */
    public static function getOrderStatus($code = -1){
        $rtn = config('order_status');
        if ($code !== -1 && is_numeric($code)){
            $tem = [];
            foreach ($rtn as $key=>$val) {
                if ($val['code'] == $code){
                    $tem = $rtn[$key];
                    continue;
                }
            }
            $rtn = $tem;
        }
        return $rtn;
    }

    /**
     * 订单号生成规则
     * 1804 0012 5523658925
     * 年月日去掉前两位，4位站点数字，10位随机字符
     */
    public static function createOrderNumner(){
        $_time = substr(date("Ymd"),2,4);
        $_machine_id = config("machine_id");

        $_rand = rand(intval(pow(10,(10-1))),intval(pow(10,10)-1));

        return $_time.$_machine_id.$_rand;
    }

    /**
     * 根据规则获取对应的coupon code
     * @param $code_num 要生成的code数量（最短长度为6位；最长长度为20位。）
     * @param $rules 要生成的code规则：$表示生成随机数字；&表示生成随机字母；*表示随机数字和字母；其他则直接输出。
     * @return array|mixed
     */
    public static function getCouponCodeData($code_num, $rules){
        $rules = htmlspecialchars_decode(htmlspecialchars_decode(htmlspecialchars_decode($rules)));
        $data = [];
        //生成的coupon code的个数
        //$code_num = $paramData['code_num'];
        //规则，格式：$&*AFC。$：标识随机数字；&：表示随机字母；*：表示随机数字和字母；其他直接输出
        //$rules = htmlspecialchars_decode($paramData['rules']);
        $rules_lenght = strlen($rules);
        if ($rules_lenght < 6 || $rules_lenght > 20){
            return apiReturn(['code'=>200, 'msg'=>'rules长度必须在6-20之间']);
        }
        //获取规则详情
        for ($i=1; $i<= $rules_lenght; $i++){
            $rules_arr[] = substr($rules , $i-1 , 1);
        }
        $model = new CouponModel();
        for ($j = 0; $j< $code_num; $j++) {
            //生成coupon code
            $coupon_code = '';
            foreach ($rules_arr as $rules_info) {
                //根据规则拼装单个字符
                $str = '';
                switch ($rules_info) {
                    case '$'://随机数字
                        $str = rand(1, 9);
                        break;
                    case '&'://随机字母
                        $str = get_random_key(1);
                        break;
                    case '*'://随机数字和字母
                        $flag = rand(1, 2);
                        if ($flag == 1) {
                            $str = rand(1, 9);
                        } else {
                            $str = get_random_key(1);
                        }
                        break;
                    default://直接输出
                        $str = $rules_info;
                        break;
                }
                $coupon_code .= $str;
            }
            $coupon_code = strtoupper($coupon_code);
            //$coupon_code 做唯一性校验
            $c_data = $model->getCouponCodeDataByWhere(['coupon_code' => $coupon_code]);
            if (!empty($c_data) || in_array($coupon_code, $data)) {
                $j--;
            } else {
                $data[] = $coupon_code;
            }
        }
        return $data;
    }

    /**
     * 多维数组排序
     * @param $input
     * @param $key
     * @param $sort
     * @return array|mixed
     */
    public static function multiArraySort($input,$key,$sort){
        $sort = array(
            'direction' => $sort, //排序顺序标志 SORT_DESC 降序；SORT_ASC 升序
            'field'     => $key,       //排序字段
        );
        $arrSort = array();
        foreach($input AS $uniqid => $row){
            foreach($row AS $key=>$value){
                $arrSort[$key][$uniqid] = $value;
            }
        }
        if($sort['direction']){
            array_multisort($arrSort[$sort['field']], constant($sort['direction']), $input);
        }
        return $input;
    }

    /**
     * 过滤空值，用在修改产品
     * @param $arr
     */
    public static function filterEmptyData(&$arr){
        foreach ($arr as $key => $val) {
            if ($val==="" ) {
                unset($arr[$key]);
            }elseif($val == null){
                unset($arr[$key]);
            }
        }
    }

    /**
     * 产品变更缓存
     * @param $product_id
     * @param $data
     * @param $IsSync
     */
    public static function productHistories($product_id,$data,$IsSync = false){
        //只有自营店铺商品同步到变更历史
        if(!empty($data) && is_array($data)){
            $data = json_encode($data);
        }
        $redis = new RedisClusterBase();
        $redis->hSet('productHistories',$product_id,$data);
    }

    /**
     * 删除产品详情缓存
     */
    public static function rmProductCache($product_id){
        $redis = new RedisClusterBase();
        //产品详情缓存
        $redisKey = $redis->getKey(PRODUCT_INFO_ . $product_id.'*');
        if(!empty($redisKey)){
            foreach($redisKey as $k => $v){
                $redis->rm($v);
            }
        }
        //产品描述
        $description_key = $redis->getKey(PRODUCT_DESCRIPTION_ . $product_id.'*');
        if(!empty($description_key)){
            foreach($description_key as $k => $v){
                $redis->rm($v);
            }
        }
    }

    /**
     * 生成缓存的key
     * @param $params
     * @return string
     */
    public static function getCacheKey($params)
    {
        $params['_timestamp'] = date('YmdH');
        //签名步骤一：按字典序排序参数
        ksort($params);
        $string = self::toUrlParams($params);
        //签名步骤三：MD5加密
        $result = md5($string);
        //所有字符转为小写
        $sign = strtolower($result);

        return $sign;
    }

    /**
     * @return string
     * @internal param array $params
     */
    public static function toUrlParams($params)
    {
        $buff = "";
        foreach ($params as $k => $v) {
//            if ($k != "sign" && !is_null($v) && $k != '_url' && $k != '_file') {
            $buff .= $k . "=" . (is_array($v) ? json_encode($v) : $v) . "&";
//            }
        }
        $buff = trim($buff, "&");

        return $buff;
    }

    /**
     * 特殊字符过滤方法
     * @param $strParam
     * @return mixed
     */
    public static function filterSpecialChar($strParam){
        //过滤特殊字符
        $regex = "/\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
        $string = preg_replace($regex,"",$strParam);
        return $string;
    }

    /**
     * 去除数组重复key数据
     * @param array $arr
     * @return array
     */
    public static function unsetRepeatArrKey(array $arr){
        if (empty($arr) || !is_array($arr)){
            return $arr;
        }
        $rtn = [];
        $all_key = [];
        foreach ($arr as $k=>$v){
            $all_key[] = $k;
        }
        $all_key = array_unique($all_key);
        foreach ($all_key as $k1=>$v1){
            $rtn[$v1] = $arr[$v1];
        }
        return $rtn;
    }

    /**
     * curl实现post【json格式】
     * @param $url
     * @param $data
     * @return array
     */


    public static function doLogCurl($url,$data = null,$options = null,$isPost = true,$header = null,$from = null,$user_agent = null){
        try{
            if (is_array($data)) {
                $data = json_encode($data);
            }
            $ch = curl_init();
            if (!empty($options)) {
                $url .= (stripos($url, '?') === null ? '&' : '?') . http_build_query($options);
            }

            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT,1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST,0);
            curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);
            if (is_null($header)) {
                $header = array(
                    "Content-type:application/json"
                );
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header); //设置头信息的地方
            if ($isPost) {
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            curl_exec($ch);
            curl_close($ch);

            return true;
        }catch (\think\Exception $e){
            return true;
        }

    }

    /**
     * 计算市场价
     * @param $lowPrice
     * @param $highListPrice
     * @param $discount
     * @return mixed
     */
    public static function countListPrice($lowPrice,$highListPrice,$discount = 0){
        $update['LowListPrice'] = 0;
        $update['HighListPrice'] = 0;
        $update['ListPriceDiscount'] = 0;
        try {
//            if($discount == 0){
//                $listPriceDiscount = (new SysConfigModel())->getSysCofig('ListPriceDiscount');
//                $randData = json_decode($listPriceDiscount['ConfigValue'], true);
//                $randkey = array_rand($randData);
//                $discount = $randData[$randkey];
//            }
            if ($discount != 0) {
                if (!empty($lowPrice)) {
                    $update['LowListPrice'] = (double)round($lowPrice / (1 - $discount), 2);
                }
                if (!empty($highListPrice)) {
                    $update['HighListPrice'] = (double)round($highListPrice / (1 - $discount), 2);
                }
            }
            $update['ListPriceDiscount'] = (double)$discount;
            return $update;
        }catch(Exception $e){
            think\Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            return $update;
        }
    }

    /**
     * 获取市场价，随机折扣
     * @return int
     */
    public static function getListPriceDiscount(){
        try {
            $listPriceDiscount = (new SysConfigModel())->getSysCofig('ListPriceDiscount');
            $randData = json_decode($listPriceDiscount['ConfigValue'], true);
            $randkey = array_rand($randData);
            return $randData[$randkey];
        }catch(Exception $e){
            think\Monlog::write(LOGS_MALL_API,'error',__METHOD__,__FUNCTION__,null,null,$e->getMessage());
            return 0;
        }
    }

    /**
     * 去掉日志敏感信息（邮箱、电话、卡号、卡密、sc密码等）
     * 支付相关
     * @param $params
     * @param integer $flag 来源标识：
     *                              1-前端提交支付下单时的$_params
     *                              2-地址相关的$_params
     *                              3-Ideal的$_params
     *                              4-调用payment相关$_params
     *                              5-订单子单数组相关$_params
     *                              6-来至OrderService下的checkCart方法的$_params
     *                              7-NOCNOC询价参数里面$_params
     *                              8-新PayMent支付请求$_params
     * @return array
     */
    public static function removeSensitiveInfoForLog($params, $flag=1){
        $rtn = $params;
        if (!is_array($rtn) || empty($rtn)){
            return $rtn;
        }
        switch ($flag){
            case 1:
                //前端提交支付下单时的$_params
                unset(
                    $rtn['BillingAddress']['Email'],
                    $rtn['BillingAddress']['Mobile'],
                    $rtn['BillingAddress']['Phone'],
                    $rtn['CardInfo'],//里面包含：CVVCode、CardHolder、CardNumber、ExpireMonth、ExpireYear、psPaymentMethodId
                    $rtn['CVVCode'],
                    $rtn['sc_password'],
                    $rtn['cpf'],
                    $rtn['card_bank'],
                    $rtn['card_type'],
                    $rtn['psToken'],
                    $rtn['save_card']
                );
                break;
            case 2:
                //地址相关的$_params
                unset(
                    $rtn['Mobile'],
                    $rtn['phone'],
                    $rtn['PhoneNumber'],
                    $rtn['PhoneCountryCode'],
                    $rtn['Email'],
                    $rtn['email'],
                    $rtn['mobile'],
                    $rtn['phone_number']
                );
                break;
            case 3:
                //Ideal的$_params
                unset(
                    $rtn['BillingAddress']['Email'],
                    $rtn['BillingAddress']['Mobile'],
                    $rtn['BillingAddress']['Phone'],
                    $rtn['CardInfo'],//里面包含：CVVCode、CardHolder、CardNumber、ExpireMonth、ExpireYear、psPaymentMethodId
                    $rtn['CVVCode'],
                    $rtn['sc_password'],
                    $rtn['cpf'],
                    $rtn['card_bank'],
                    $rtn['card_type'],
                    $rtn['psToken'],
                    $rtn['save_card']
                );
                if (isset($rtn['orderInfo']['slave']) && !empty($rtn['orderInfo']['slave'])){
                    foreach ($rtn['orderInfo']['slave'] as $k1=>$v1){
                        unset(
                            $rtn['orderInfo']['slave'][$k1]['shipping_address']['phone_number'],
                            $rtn['orderInfo']['slave'][$k1]['shipping_address']['mobile'],
                            $rtn['orderInfo']['slave'][$k1]['shipping_address']['email']
                        );
                    }
                }
                break;
            case 4:
                //调用payment相关$_params
                unset(
                    $rtn['ShippingAddress']['Email'],
                    $rtn['ShippingAddress']['Mobile'],
                    $rtn['ShippingAddress']['Phone'],
                    $rtn['CardInfo'],
                    $rtn['BillingAddress']['Email'],
                    $rtn['BillingAddress']['Mobile'],
                    $rtn['BillingAddress']['Phone']
                );
                break;
            case 5:
                //订单子单数组相关$_params
                if (isset($rtn['slave']) && !empty($rtn['slave'])){
                    foreach ($rtn['slave'] as $k1=>$v1){
                        unset(
                            $rtn['slave'][$k1]['shipping_address']['phone_number'],
                            $rtn['slave'][$k1]['shipping_address']['mobile'],
                            $rtn['slave'][$k1]['shipping_address']['email']
                        );
                    }
                }
                break;
            case 6:
                //来至OrderService下的checkCart方法的$_params
                unset(
                    $rtn['BillingAddress']['Email'],
                    $rtn['BillingAddress']['Mobile'],
                    $rtn['BillingAddress']['Phone'],
                    $rtn['cpf'],
                    $rtn['card_bank']
                );
                break;
            case 7:
                //NOCNOC询价参数里面$_params
                unset(
                    $rtn['address']['phone']
                );
                break;
            case 8:
                //新PayMent支付请求$_params
                unset(
                    $rtn['CustomerEmail'],
                    $rtn['ShippingAddress']['Email'],
                    $rtn['ShippingAddress']['PhoneNumber'],
                    $rtn['ShippingAddress']['ZipPostal'],
                    $rtn['ShippingAddress']['CpfNo'],
                    $rtn['ShippingAddress']['PhoneCountryCode'],
                    $rtn['CardInfo'],
                    $rtn['BillingAddress']['Email'],
                    $rtn['BillingAddress']['PhoneNumber'],
                    $rtn['BillingAddress']['ZipPostal'],
                    $rtn['BillingAddress']['PhoneCountryCode']
                );
                break;
            default:break;
        }
        return $rtn;
    }
}