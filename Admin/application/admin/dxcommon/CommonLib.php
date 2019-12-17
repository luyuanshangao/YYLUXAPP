<?php

namespace app\admin\dxcommon;

use app\common\redis\RedisClusterBase;
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
    public static function filterArrayByKey($input,$key,$val){
        $retArray = array_filter($input, function($t) use ($key,$val){
             return $t[$key] == $val;
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
        //过滤空格，制表符
        $search = array(" ","　");
        $replace = array("-","-");
        return str_replace($search, $replace, $string);
    }

    /**
     * 多维数组排序
     * @param $input
     * @param $key
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

    public static function getCategoryId($params){
        $id = (explode('-',$params));
        if(!empty($id)){
            $id = array_pop($id);
        }
        return $id;
    }

    public function sendMailServiceSoap($function_name, array $params){
        $wsdl_url_config = config('wsdl_url');
        $wsdl = $wsdl_url_config['send_mail_service_wsdl']['url'];
        $opts = $wsdl_url_config['send_mail_service_wsdl']['options'];
        $user_name = $wsdl_url_config['send_mail_service_wsdl']['user_name'];
        $password = $wsdl_url_config['send_mail_service_wsdl']['password'];
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }

    /**
     * 清除缓存
     * @return string
     */
    public static function clearRedisCache($redis_key , $is_like=true){
        ini_set('max_execution_time', '0');
        if(!empty($redis_key)){
            $redis = new RedisClusterBase();
            if($is_like){
                /* With Redis::SCAN_RETRY enabled */
                $redis->setOption(\RedisCluster::OPT_SCAN, \RedisCluster::SCAN_RETRY);

                //Return all redis master nodes
                foreach ($redis->_masters() as $master) {
                    $it = null;
                    while ($keys = $redis->scan($it, $master,$redis_key.'*')) {
                        foreach ($keys as $key) {
                            $redis->rm($key);
                        }
                    }
                }
            }else{
                $redis->rm($redis_key);
            }
            return true;
        }else{
            return false;
        }
    }

    /**
     * 风控处理服务
     * @param $function_name
     * @param array $params
     * @return array|string
     */
    public function riskProcessService($function_name, array $params){
        $wsdl_url_config = config('wsdl_url');
        $wsdl = $wsdl_url_config['risk_process_service_wsdl']['url'];
        $opts = $wsdl_url_config['risk_process_service_wsdl']['options'];
        $user_name = $wsdl_url_config['risk_process_service_wsdl']['user_name'];
        $password = $wsdl_url_config['risk_process_service_wsdl']['password'];
        try {
            libxml_disable_entity_loader(false);
            $streamContext = stream_context_create($opts);
            $options['stream_context'] = $streamContext;
            $xml = '
                <wsse:Security xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
                    <wsse:UsernameToken>
                        <wsse:Username>'.$user_name.'</wsse:Username>
                        <wsse:Password>'.$password.'</wsse:Password>
                    </wsse:UsernameToken>
                </wsse:Security>';
            $client = new \SoapClient($wsdl, $options);
            $header = new \SoapHeader($wsdl, 'CallbackHandler', new \SoapVar($xml, XSD_ANYXML), TRUE);
            $client->__setSoapHeaders(array($header));
            $result = $client->__soapCall($function_name, $params);
            return (array)$result;
        }catch (\Exception $e){
            return $e->getMessage();
        }
    }
}