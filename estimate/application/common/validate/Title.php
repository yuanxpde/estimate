<?php
 namespace app\common\validate;
 use think\Validate;
 class  Title extends Validate{
   //每个字段对应一个规则，这是第一层
     protected $rule=[
         ["p_id","require","必须关联试卷"],
         ["title_number","require","题号不能为空"],
         ["stem","require","题干不能为空"],
      /*  ["id","number","必须是数字"],
       ["status","number|in:1,0,-1","必须是数字|必须是是0,-1,1"],*/
     ];

 }