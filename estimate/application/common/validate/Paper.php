<?php
 namespace app\common\validate;
 use think\Validate;
 class  Paper extends Validate{
   //每个字段对应一个规则，这是第一层
     protected $rule=[
         ["paper_name","require","试卷名称不能为空"],
      /*  ["id","number","必须是数字"],
       ["status","number|in:1,0,-1","必须是数字|必须是是0,-1,1"],*/
     ];

 }