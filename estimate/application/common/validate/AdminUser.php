<?php
 namespace app\common\validate;
 use think\Validate;
 class  AdminUser extends Validate{
   //每个字段对应一个规则，这是第一层
     protected $rule=[
         ["username","require|max:10","用户名不能为空|姓名不能超过10个字符"],
         ["password","require","密码不能为空"],
      /*  ["id","number","必须是数字"],
       ["status","number|in:1,0,-1","必须是数字|必须是是0,-1,1"],*/
     ];

 }