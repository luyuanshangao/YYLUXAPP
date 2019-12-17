<?php
namespace app\common\controller;
use app\admin\model\ProductClass as product_class_model;

/**
 * 类别
 * @author
 * @version tinghu.liu 2018/3/20
 */
class BaseFunc {
    /**
     * curl实现post【json格式】
     * @param $url
     * @param $json_data
     * @return array
     */
    public static function http_post_json($url, $json_data=null) {
        $url .= '?access_token=dx123';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_TIMEOUT,60);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);

        if (!empty($json_data)){
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    "Content-Type: application/json; charset=utf-8",
                    "Content-Length: " . strlen($json_data))
            );
        }
        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();
        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //return array($return_code, $return_content);
        return $return_content;
    }

    /**
     * curl进行地址请求
     * @param $url 访问的URL
     * @param string $post post数据(不填则为GET)
     * @param string $cookie 提交的$cookies
     * @param int $returnCookie 是否返回$cookies
     * @return mixed|string
     */
    public static function curl_request($url,$post='',$cookie='', $returnCookie=0){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)');
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_REFERER, "http://XXX");
        if($post) {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        if($cookie) {
            curl_setopt($curl, CURLOPT_COOKIE, $cookie);
        }
        curl_setopt($curl, CURLOPT_HEADER, $returnCookie);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10*60);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_error($curl);
        }
        curl_close($curl);
        if($returnCookie){
            list($header, $body) = explode("\r\n\r\n", $data, 2);
            preg_match_all("/Set\-Cookie:([^;]*);/", $header, $matches);
            $info['cookie']  = substr($matches[1][0], 1);
            $info['content'] = $body;
            return $info;
        }else{
            return $data;
        }
    }

    /**
     * 根据分类ID获取相应完整路径
     * @param $category_id 分类ID
     * @param array $data
     * @return array
     */
    public static function getCategoryStrWithID($category_id, $data=array('title_en_str'=>'','title_cn_str'=>'')){
        $product_class_model = new product_class_model();
        $cinfo = $product_class_model->getInfoWithId($category_id);
        $pid = $cinfo['pid'];
        $data['title_cn_str'] .= $cinfo['title_cn'].'>>';
        $data['title_en_str'] .= $cinfo['title_en'].'>>';
        //是否有父级
        if ($pid != 0){ //有父级
            return self::getCategoryStrWithID($pid, $data);
        }
        //将类别按照大类往低类排序
        $data['title_cn_str'] = self::handleCategoryData($data['title_cn_str'],'>>');
        $data['title_en_str'] = self::handleCategoryData($data['title_en_str'],'>>');
        return $data;
    }

    /**
     * 处理类别数据【将类别按照大类往低类排序】
     * @param $data
     * @return string
     */
    public static function handleCategoryData($data,$delimiter){
        $title = explode($delimiter, $data);
        foreach ($title as $key=>$cninfo){//去掉为空的数据
            if (empty($title[$key])) {
                unset($title[$key]);
            }
        }
        for ($i=count($title)-1;$i>=0;$i--){//数组倒叙
            $rtn[] =  $title[$i];
        }
        //只返回四级
        $rtn_new = array();
        foreach ($rtn as $key=>$val){
            if ($key <= 3){
                $rtn_new[] = $val;
            }
        }
        return implode('>>', $rtn_new);
    }

    /**
     * 根据分类ID获取相应分类信息[根据子级获取完整类别]
     * @param $category_id 子级分类ID
     * @return array
     */
    public static function getCategoryInfoWithID($category_id, $data=[]){
        $product_class_model = new product_class_model();
        $cinfo = $product_class_model->getInfoWithId($category_id);
        $pid = $cinfo['pid'];
        $parent_data = $product_class_model->getInfoWithIdForPID($pid);
        //标识所属级别
        foreach ($parent_data as &$info){
            if($info['id'] == $category_id){
                $info['is_select'] = 1;
            }else{
                $info['is_select'] = 0;
            }
            //是否是末级
            $is_children = false;
            $pdata = $product_class_model->getInfoWithIdForPID($info['id']);
            if (empty($pdata)){
                $is_children = true;
            }
            $info['is_children'] = $is_children;

        }
        $data[] = $parent_data;
        if ($pid !== 0){//不是顶级则继续递归
            return self::getCategoryInfoWithID($pid, $data);
        }
        return $data;
    }


    /**
     * 根据分类ID获取下一个子级
     * @param $pid 父级ID
     */
    public static function getCategoryNextInfoWithID($pid){
        $product_class_model = new product_class_model();
        $data = $product_class_model->getInfoWithIdForPID($pid);
        //判断级别是否为末级
        $is_children = false;
        foreach ($data as &$info){
            $id = $info['id'];
            $pdata = $product_class_model->getInfoWithIdForPID($id);
            if (empty($pdata)){
                $is_children = true;
            }
            $info['is_children'] = $is_children;
        }
        return $data;
    }



}