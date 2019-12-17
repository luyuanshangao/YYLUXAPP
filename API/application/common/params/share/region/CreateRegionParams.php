<?php
namespace app\common\params\share\region;

class CreateRegionParams
{
    public $Name;
    public $Code;
    public $CnName;
    public $ParentID;
    public $AreaID;
    public $AreaName;


    public function rules()
    {
        return[
            ['Name','require','Name不能为空'],
            ['Code','require','Code不能为空'],
            ['CnName','require','CnName不能为空'],
            ['ParentID','require|number','ParentID不能为空','ParentID必须是数字'],
            ['AreaID','require','AreaID不能为空'],
            ['AreaName','require','AreaName不能为空'],
        ];
    }
}