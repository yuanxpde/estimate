<?php
 namespace app\common\validate;
 use think\Validate;
 class  Material extends Validate{
   //每个字段对应一个规则，这是第一层
     protected $rule=[
         ["material_name","require|max:100","材料不能为空|材料不能超过10个字符"],
      /*  ["id","number","必须是数字"],
       ["status","number|in:1,0,-1","必须是数字|必须是是0,-1,1"],*/
     ];

 }