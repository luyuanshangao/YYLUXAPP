<?php
namespace app\admin\dxcommon;

use think\Log;
use think\Db;

/**
 * 后台公共方法类
 * @author zhangheng
 * @date 2018-06-29
 * @package app\admin\dxCommon
 */
class Common
{
	/**
	 * 输出SelectHtml
	 * @param array $dict
	 * @param string $selectedValue
	 * @return string select选择器的HTML
	 */
	public static function outSelectHtml(array $dict,$selectName,$selectedValue){
		$outHtml ='<select name="'.$selectName.'" id="'.$selectName.'" class="form-control input-small inline">';
		$outHtml .='<option value="">请选择</option>';
		if(!empty($dict)){
			foreach ($dict as $key => $value){
				if(count($value) ==2){
					$isSelected='';
					if($value[0] == $selectedValue){
						$isSelected =' selected = "selected" ';
					}
					$outHtml .='<option '.$isSelected . ' value="'.$value[0].'">'.$value[1].'</option>';
				}
			}
		}
		$outHtml .='</select>';
		return $outHtml;
	}

    //字典数据的获取
    public static function dictionariesQuery($val){
        $PayemtMethod = Db::connect("db_mongo")->name(S_CONFIG)->where(['ConfigName'=>$val])->find();
        $data = explode(";",htmlspecialchars_decode($PayemtMethod['ConfigValue']));
        foreach ($data as $key => $value) {
            if(!empty($value)){
                $list[] = explode(":",htmlspecialchars_decode($value));
            }

        }
        return $list;
    }

    /*缩放图片，防止木马*/
    function processingPictures($file_path,$type){
        /*
       步骤：
        1.打开图片源文件资源
        2.获得源文件的宽高
        3.使用固定的公式计算新的宽高
        4.生成目标图像资源
        5.进行缩放
        6.保存图像
        7.释放资源
        */
        //1.打开图片源文件资源
        switch($type)
        {
            case "png":
                $im=imagecreatefrompng($file_path);
                break;

            case "jpeg":
                $im=imagecreatefromjpeg($file_path);
                break;

            case "jpg":
                $im=imagecreatefromjpeg($file_path);
                break;
        }
        //$im = imagecreatefromjpeg($file_path);

        //2.获得源文件的宽高
        $fx = imagesx($im); // 获取宽度
        $fy = imagesy($im); // 获取高度


        //3.使用固定的公式计算新的宽高
        $sx = $fx;
        $sy = $fy;
        //4.生成目标图像资源
        $small = imagecreatetruecolor($sx,$sy);
        //5.进行缩放
        imagecopyresampled($small,$im,0,0,0,0,$sx,$sy,$fx,$fy);

        //6.保存图像
        if(imagejpeg($small,$file_path)) {
            //7.释放资源
            imagedestroy($im);
            imagedestroy($small);
            return true;
        } else {
            //7.释放资源
            imagedestroy($im);
            imagedestroy($small);
            return false;
        }
    }

}
