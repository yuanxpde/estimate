<?php
namespace app\admin\controller;

use think\Db;
use app\admin\model\user as u;

class User extends Common
{
    public function index()
    {
        $list =Db::table('estimate_admin_user')
            ->where('user_status',1)
            ->order('create_at desc')
            ->paginate(10,false,['query'=>request()->param()]);

        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 添加管理员页面
     * @return mixed
     */
    public function userAdd()
    {
        $id =input('id');
        $info =u::get(['id'=>$id]);
        //var_dump($info);exit;

        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 添加管理员
     * @return mixed
     */
    public function addUser(){
        if(request()->isPost()){
            $data=input('post.');
            $validate = validate('adminUser');
            if(!$validate->check($data)){
                $this->error($validate->getError());
            }
            if(!trim($data['username'])){
                $this->error('用户名称不能为空！', 'admin/user/index');
            }
            if(!trim($data['password'])){
                $this->error('用户名称不能为空！', 'admin/user/index');
            }
            $userData=array();
            $userData['id']=trim($data['id']);
            $userData['username']=trim($data['username']);
            $userData['password']=md5($data['password']);
            $userData['user_status']=1;
            $op = '';
            if(!empty($data['id'])){
                $userData['update_by']=$this->user['uid'];
                $userData['update_at']=time();
                $op = '修改';
                $add=db('admin_user')->update($userData);
            }else{
                $userData['create_by']=$this->user['uid'];
                $userData['create_at']=time();
                $op = '添加';
                $add=db('admin_user')->insert($userData);
            }
            if($add){
                $this->success($op.'成功！','admin/user/index');
            }else{
                $this->error($op.'失败！', 'admin/user/index');
            }
        }

    }

    public function add()
    {
        return $this->fetch();
    }
}
