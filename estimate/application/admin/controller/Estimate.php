<?php
namespace app\admin\controller;

//use think\Controller;
use app\index\model\User;
use redis\RedisPackage;
use think\Db;

use app\admin\model\paper;
use app\admin\model\material;
use app\admin\model\title;

class Estimate extends Common
{
    /**
     * 试卷列表
     * @return mixed
     */
    public function index()
    {
        $list =Db::table('estimate_paper')
            ->where('paper_status',1)
            ->order('create_at desc')
            ->paginate(10,false,['query'=>request()->param()]);

        $this->assign('list', $list);
        return $this->fetch();
    }

/**
 * 添加试卷页面
 * @return mixed
 */
    public function paperAdd()
    {
        $id =input('id');
        $info =Paper::get(['id'=>$id]);

        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 添加试卷提交
     * @return mixed
     */
    public function addPaper(){
        if(request()->isPost()){
            $data=input('post.');
            $validate = validate('Paper');
            if(!$validate->check($data)){
                $this->error($validate->getError());
            }
            if(!trim($data['paper_name'])){
                $this->error('试卷名称不能为空！', 'admin/estimate/paperAdd');
            }
            $paperData=array();
            $paperData['id']=trim($data['id']);
            $paperData['paper_name']=trim($data['paper_name']);
            $paperData['is_city']=!empty($data['is_city']) ? $data['is_city'] : 0;
            $paperData['paper_status']=1;
            $op = '';
            if(!empty($data['id'])){
                $paperData['update_by']=$this->user['uid'];
                $paperData['update_at']=time();
                $op = '修改';
                $add=db('paper')->update($paperData);
            }else{
                $paperData['create_by']=$this->user['uid'];
                $paperData['create_at']=time();
                $op = '添加';
                $add=db('paper')->insertGetId($paperData);
            }
            if($add){
                if(!empty($data['id'])){
                    $id = $data['id'];
                }else{
                    $id = $add;
                }
                $user =Paper::get(['id'=>$id]);
                $this->assign('paper', $user);
                $this->buildHtml($id,'public/html/',APP_PATH.'index/view/index/index.html');
                $this->success($op.'成功！','admin/estimate/index');
            }else{
                $this->error($op.'失败！', 'admin/estimate/index');
            }
        }
    }

    /**
     * 生成静态考试页面
     * @return mixed
     */
    public function paperHtml(){
        $get=input('get.');
        $info =Paper::get(['id'=>$get['pid']]);
        $title = Db::table('estimate_title')->where('p_id',$get['pid'])->order('title_number asc')->select();
        $total = count($title);

        $title_num = [];
        foreach ($title as $k=>$v){
            $title_num[] = $v['title_number'];
        }

        $where=[];
        $where['title_status'] = 1;
        if(!empty($get['pid'])){
            $where['p_id'] = $get['pid'];
        }

        $list =Db::table('estimate_title')
            ->where($where)
            ->order('title_number asc')
            ->paginate(200,false,['query'=>request()->param()])
            ->each(function($item, $key){
                static $m = [];
                if($item['material_id'] && !in_array($item['material_id'],$m)){
                    $m[] = $item['material_id'];
                    $mate =Db::table('estimate_material')
                        ->where('id',$item['material_id'])
                        ->column('content');
                    $material = $mate[0];
                }else{
                    $material = '';
                }
                $item['material'] = $material;
                $item['option'] = json_decode($item['option'],true);
                foreach ($item['option'] as $k=>$v){
                    $image = $v['image'];
                    $item['option'][$k]['image'] = !empty($image) ? "<img src=$image>" : "";
                    if($v['id']=="A"){
                        $item['option'][$k]['num'] = 1;
                    }elseif ($v['id']=="B"){
                        $item['option'][$k]['num'] = 2;
                    }elseif ($v['id']=="C"){
                        $item['option'][$k]['num'] = 3;
                    }elseif ($v['id']=="D"){
                        $item['option'][$k]['num'] = 4;
                    }

                }
                return $item;
            });
        //生成缓存
        $this->delCache($list->items(),$get['pid']);
        $this->buildCache($list->items(),$get['pid']);

        $this->assign('title_num', json_encode($title_num));
        $this->assign('id', $get['pid']);
        $this->assign('info', $info);
        $this->assign('total', $total);
        $this->assign('list', $list);
        $this->buildHtml($get['pid'],'public/paper/',APP_PATH.'index/view/index/paper.html');
        $this->buildHtml($get['pid'],'public/parsing/',APP_PATH.'index/view/index/parsing.html');
        $this->success('生成成功！','admin/estimate/index');
    }

    /**
     * 生成缓存
     * @return mixed
     */
    public function buildCache($data,$pid)
    {
        $redis= new RedisPackage();
        if(!empty($data) && !empty($pid)){
            foreach ($data as $k=>$v){
                $redis::hMset('title_'.$v['id'], $v);
                $redis::zAdd('paper_'.$pid,$v['id'],$v['id']);
            }
        }
        /*
        $arr = $redis::ZRANGE("paper_".$pid,1,5);
        //var_dump($data);exit;
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("title_".$value);
            $info[$title['title_number']] = $title;
        }
        var_dump($info);exit;*/
        return true;
    }

    /**
     * 删除缓存
     * @return mixed
     */
    public function delCache($data,$pid)
    {
        if(!empty($data) && !empty($pid)){
            $redis= new RedisPackage();
            foreach ($data as $k=>$v){
                $redis::del('title_'.$v['id']);
                $redis::zRem('paper_'.$pid,$v['id']);
            }
        }
        return true;
    }

    /**
     * 试题列表页面
     * @return mixed
     */
    public function titleList(){
        $post=input('post.');
        $query = [
            'p_id'=>!empty($post['p_id']) ? $post['p_id'] : '',
            'title_number'=>!empty($post['title_number']) ? $post['title_number'] : '',
        ];
        $where=[];
        $where['title_status'] = 1;
        $order = "create_at desc";
        if(!empty($query['p_id'])){
            $where['p_id'] = $query['p_id'];
            $order = "title_number asc";
        }
        if(!empty($query['title_number'])){
            $where['title_number'] = $query['title_number'];
        }
        $list =Db::table('estimate_title')
            ->where($where)
            ->order($order)
            ->paginate(20,false,['query'=>request()->param()]);
        $paper =Db::table('estimate_paper')
            ->where('paper_status',1)
            ->column('id,paper_name,paper_status','id');
        $this->assign('list', $list);
        $this->assign('paper', $paper);
        $this->assign('query', $query);
        return $this->fetch();
    }

    /**
     * 试题添加页面
     * @return mixed
     */
    public function titleAdd(){
        $paper =Paper::all();
        $material =Material::all();
        $this->assign('material', $material);
        $this->assign('paper', $paper);
        $id =input('id');
        $info =Title::get(['id'=>$id]);
        //var_dump($info);exit;
        //试卷id
        $pid =input('pid');
        //已关联试题
        $titles = [];
        if($pid){
            $titles = Db::table('estimate_title')->where('p_id',$pid)->order('title_number asc')->select();
        }
        //选项数组
        $answers = [
            ['option'=>'A'],
            ['option'=>'B'],
            ['option'=>'C'],
            ['option'=>'D']
        ];
        //选项
        $option = json_decode($info['option'],true);
        //var_dump($option);exit;
        $this->assign('info', $info);
        $this->assign('pid', $pid);
        $this->assign('titles', $titles);
        $this->assign('answers', $answers);
        $this->assign('option', $option);
        return $this->fetch();
    }

    /**
     * 试题添加提交
     * @return mixed
     */
    public function addTitle(){
        if(request()->isPost()){
            $data=input('post.');
            $validate = validate('Title');
            if(!$validate->check($data)){
                $this->error($validate->getError());
            }
            $titleData=array();
            $titleData['id']=trim($data['id']);
            $titleData['p_id']=trim($data['p_id']);
            $titleData['material_id']=trim($data['material_id']);
            $titleData['title_number']=trim($data['title_number']);
            $titleData['score']=$data['score'];
            $titleData['stem']=!empty($data['stem']) ? $data['stem'] : '';
            $titleData['answer']=!empty($data['answer']) ? $data['answer'] : '';
            $titleData['analysis']=!empty($data['analysis']) ? $data['analysis'] : '';
            $titleData['title_status']=1;

            //组装选项json格式
            $option = [
                ['id'=>'A','content'=>$data['op_content_A'],'image'=>$data['op_image_A'],'alt'=>$data['A']],
                ['id'=>'B','content'=>$data['op_content_B'],'image'=>$data['op_image_B'],'alt'=>$data['B']],
                ['id'=>'C','content'=>$data['op_content_C'],'image'=>$data['op_image_C'],'alt'=>$data['C']],
                ['id'=>'D','content'=>$data['op_content_D'],'image'=>$data['op_image_D'],'alt'=>$data['D']]
            ];
            $titleData['option']=json_encode($option);
            $op = '';
            if(!empty($data['id'])){
                $titleData['update_by']=$this->user['uid'];
                $titleData['update_at']=time();
                $op = '修改';
                $add=db('title')->update($titleData);
            }else{
                $titleData['create_by']=$this->user['uid'];
                $titleData['create_at']=time();
                $op = '添加';
                $add=db('title')->insert($titleData);
            }
            if($add){
                //$redis= new RedisPackage();
                //$redis::LPush();
                $this->success($op.'成功！','admin/estimate/titleAdd?id='.$titleData['id'].'&pid='.$titleData['p_id']);
            }else{
                $this->error($op.'失败！', 'admin/estimate/titleAdd?id='.$titleData['id'].'&pid='.$titleData['p_id']);
            }
        }
    }

    /**
     * 材料列表
     * @return mixed
     */
    public function materialList(){
        $list =Db::table('estimate_material')
            ->where('material_status',1)
            ->order('create_at desc')
            ->paginate(10,false,['query'=>request()->param()]);

        $this->assign('list', $list);
        return $this->fetch();
    }

    /**
     * 材料添加页面
     * @return mixed
     */
    public function materialAdd(){
        $id =input('id');
        $info =Material::get(['id'=>$id]);
        $this->assign('info', $info);
        return $this->fetch();
    }

    /**
     * 材料添加提交
     * @return mixed
     */
    public function addMaterial(){
        if(request()->isPost()){
            $data=input('post.');
            $validate = validate('Material');
            if(!$validate->check($data)){
                $this->error($validate->getError());
            }
            if(!trim($data['material_name'])){
                $this->error('材料ID不能为空！', 'admin/estimate/materialAdd');
            }
            $mateData=array();
            $mateData['id']=$data['id'];
            $mateData['material_name']=trim($data['material_name']);
            $mateData['content']=!empty($data['content']) ? $data['content'] : '';
            $mateData['material_status']=1;
            $mateData['create_at']=time();
            $op = '';
            if(!empty($data['id'])){
                $mateData['update_by']=$this->user['uid'];
                $mateData['update_at']=time();
                $op = '修改';
                $add=db('material')->update($mateData);
            }else{
                $mateData['create_by']=$this->user['uid'];
                $mateData['create_at']=time();
                $op = '添加';
                $add=db('material')->insert($mateData);
            }
            if($add){
                $this->success($op.'成功！','admin/estimate/materialList');
            }else{
                $this->error($op.'失败！', 'admin/estimate/materialList');
            }
        }
    }

    /**
     * 上传
     */
    public function uploadPhoto(){
        $file = $this->request->file('file');
        if(!empty($file)){
            // 移动到框架应用根目录/public/uploads/ 目录下
            $info = $file->validate(['size'=>1048576,'ext'=>'jpg,png,gif'])->rule('uniqid')->move(ROOT_PATH . 'public' . DS . 'uploads'. DS .'admin' . DS .'image' . DS .date('Ymd'));
            $error = $file->getError();
            //验证文件后缀后大小
            if(!empty($error)){
                dump($error);exit;
            }
            if($info){
                // 成功上传后 获取上传信息
                // 输出 jpg
                $info->getExtension();
                // 输出 20160820/42a79759f284b767dfcb2a0197904287.jpg
                $info->getSaveName();
                // 输出 42a79759f284b767dfcb2a0197904287.jpg
                $photo = $info->getFilename();

            }else{
                // 上传失败获取错误信息
                $file->getError();
            }
        }else{
            $photo = '';
        }
        if($photo !== ''){
            return ['code'=>201,'msg'=>'成功','src'=>'/estimate/public/uploads/admin/image/'.date("Ymd").'/'.$photo];
        }else{
            return ['code'=>404,'msg'=>'失败'];
        }
    }

    /*
     * 删除图片
     */
    public function delPhoto(){
        $imgsrc = input('post.');
        $src = substr($imgsrc['imgsrc'],17);
        $r = unlink('../../../public/'.$src);
        if($r){
            return ['code'=>201,'msg'=>'成功'];
        }else{
            return ['code'=>404,'msg'=>'失败'];
        }
    }

    /*
     * 地市导入
     */
    public function cityImport(){
        $paper =Paper::all();
        $this->assign('paper', $paper);
        return $this->fetch();
    }

    /*
     * 地市导入
     */
    public function saveCityImport(){
        vendor("PHPExcel.PHPExcel"); //方法一
        $objPHPExcel = new \PHPExcel();
        $p_data=input('post.');

        //获取表单上传文件
        $file = request()->file('excel');
        //var_dump($file);exit;
        if(empty($file)){
            $this->error('没有选择excel文件！','/estimate/admin/estimate/dataImport');
        }
        $info = $file->validate(['size'=>186780,'ext'=>'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');
        //var_dump($info);exit;
        if($info){
            $exclePath = $info->getSaveName();  //获取文件名
            $file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址
            $objReader =\PHPExcel_IOFactory::createReader('Excel2007');
            $obj_PHPExcel =$objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
            echo "<pre>";
            $excel_array=$obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);
            $data = [];
            $i=0;
            foreach($excel_array as $k=>$v) {
                $data[$k]['p_id'] = $p_data['p_id'];
                $data[$k]['city_name'] = $v[0];
                $data[$k]['create_by'] = $this->user['uid'];
                $data[$k]['create_at'] = time();
                $i++;
            }

            $success=Db::name('city')->insertAll($data); //批量插入数据
            //$i=
            $error=$i-$success;
            echo "总{$i}条，成功{$success}条，失败{$error}条。";
            // Db::name('t_station')->insertAll($city); //批量插入数据
        }else{
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }

    /*
     * 职位导入
     */
    public function stationImport(){
        $paper =Paper::all();
        $this->assign('paper', $paper);
        return $this->fetch();
    }


    /*
     * 职位导入
     */
    public function saveStationImport(){
        vendor("PHPExcel.PHPExcel"); //方法一
        $objPHPExcel = new \PHPExcel();

        $p_data=input('post.');

        $city =Db::table('estimate_city')
            ->where('p_id',$p_data['p_id'])
            ->column('id','city_name');

        //获取表单上传文件
        $file = request()->file('excel');
        //var_dump($file);exit;
        if(empty($file)){
            $this->error('没有选择excel文件！','/estimate/admin/estimate/dataImport');
        }
        $info = $file->validate(['size'=>306780,'ext'=>'xlsx,xls,csv'])->move(ROOT_PATH . 'public' . DS . 'excel');
        //var_dump($info);exit;
        if($info){
            $exclePath = $info->getSaveName();  //获取文件名
            $file_name = ROOT_PATH . 'public' . DS . 'excel' . DS . $exclePath;   //上传文件的地址
            $objReader =\PHPExcel_IOFactory::createReader('Excel2007');
            $obj_PHPExcel =$objReader->load($file_name, $encode = 'utf-8');  //加载文件内容,编码utf-8
            echo "<pre>";
            $excel_array=$obj_PHPExcel->getsheet(0)->toArray();   //转换为数组格式
            array_shift($excel_array);  //删除第一个数组(标题);
            $data = [];
            $i=0;
            foreach($excel_array as $k=>$v) {
                //var_dump($city);exit;
                $data[$k]['p_id'] = $p_data['p_id'];
                $data[$k]['c_id'] = $city[$v[1]];
                $data[$k]['c_name'] = $v[1];
                $data[$k]['company'] = $v[2];
                $data[$k]['station_name'] = $v[3];
                $data[$k]['station_number'] = $v[4];
                $data[$k]['create_by'] = $this->user['uid'];
                $data[$k]['create_at'] = time();
                //var_dump($data);exit;
                $i++;
            }

            $success=Db::name('station')->insertAll($data); //批量插入数据
            //$i=
            $error=$i-$success;
            echo "总{$i}条，成功{$success}条，失败{$error}条。";
            // Db::name('t_station')->insertAll($city); //批量插入数据
        }else{
            // 上传失败获取错误信息
            echo $file->getError();
        }
    }

    /*
     * 前台用户列表
     */
    public function userList(){
        $post=input('post.');
        $query = [
            'mobile_phone'=>!empty($post['mobile_phone']) ? $post['mobile_phone'] : '',
        ];
        $where=[];
        if(!empty($query['mobile_phone'])){
            $where['mobile_phone'] = $query['mobile_phone'];
        }
        $list =Db::table('estimate_user')
            ->where($where)
            ->order('create_at desc')
            ->paginate(20,false,['query'=>request()->param()]);

        $this->assign('list', $list);
        $this->assign('query', $query);
        return $this->fetch();
    }

    /*
    * 前台用户得分列表
    */
    public function userScore(){
        $post=input('post.');
        $query = [
            'mobile_phone'=>!empty($post['mobile_phone']) ? $post['mobile_phone'] : '',
        ];
        $where=[];
        if(!empty($query['mobile_phone'])){
            $where['mobile_phone'] = $query['mobile_phone'];
        }

        $list = Db::table('estimate_user_score')
            ->alias('us')
            ->join('estimate_user u','us.user_id = u.id','LEFT')
            ->field('us.id,us.p_id,us.user_id,us.user_name,us.error_num,us.correct_num,us.user_score,us.create_at,u.mobile_phone')
            ->where($where)
            ->order('create_at desc')
            ->paginate(20,false,['query'=>request()->param()]);

        $this->assign('list', $list);
        $this->assign('query', $query);
        return $this->fetch();
    }

    /*
    * 前台用户得分删除
    */
    public function userScoreDel(){
        $get=input('get.');

        $r =Db::table('estimate_user_score')
            ->where('id',$get['id'])
            ->delete();

        if($r){
            $this->success('删除成功！','admin/estimate/userScore');
        }else{
            $this->error('删除失败！','admin/estimate/userScore');
        }
    }

}
