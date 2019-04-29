<?php
 namespace app\common\validate;
 use think\Validate;
 class  User extends Validate{
   //每个字段对应一个规则，这是第一层
     protected $rule=[
         ["user_name","require|max:10","姓名不能为空|姓名不能超过10个字符"],
         ["mobile_phone","require|number|max:11","必须为数字"],
      /*  ["id","number","必须是数字"],
       ["status","number|in:1,0,-1","必须是数字|必须是是0,-1,1"],*/
     ];

 }