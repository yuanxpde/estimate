<?php
namespace app\index\controller;

use app\index\model\Login as lo;
use think\Controller;
use app\index\model\user;
use think\Db;
use redis\RedisPackage;
use think\Log;

class Index extends Controller
{
    /**
     * 首页
     * @return mixed
     */
    public function index()
    {
        $this->assign('paper', ['paper_name'=>'']);
        return $this->fetch();
    }

    /**
     * 测试存redis缓存 - 取redis缓存使用
     * @param RedisPackage $redisPackage
     */
    public function redisTest(){
        $redis= new RedisPackage();

        $arr = $redis::ZRANGE("paper_11",1,200);
        //var_dump($data);exit;
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("title_".$value);
            $info[$title['title_number']] = $title;
        }
        var_dump($info);exit;
        $add = [1,2,3,4,5];
        foreach ($add as $k=>$value){
            $user_name = 'yuan';
            $mobile_phone = '15501053025';
            $data['city_id'] = 2;
            $data['station_id'] = 2;
            $redis::hMset("user:".$value,array('uid'=>$value,'user_name'=>$user_name,'mobile_phone'=>$mobile_phone,'city_id'=>$data['city_id'],'station_id'=>$data['station_id']));
            $redis::zAdd('user_sort',$value,$value);
        }

        $data = $redis::ZRANGE("user_sort",1,5);

        $info = [];
        foreach ($data as $v){
            $info[] = $redis::hGetAll("user:".$v);
        }
        var_dump($info);exit;
        echo 123;
        //$redis::LPush('paper',$uid);
        //var_dump( $redis->lRange('paper', 0, -1) );
    }

    /**
     * 404
     * @return mixed
     */
    public function errorHtml()
    {
        return $this->fetch();
    }

    /**
     * 试卷页面
     * @return mixed
     */
    public function paper()
    {
        //$url = $_SERVER['REQUEST_URI'];
        //$id = $this->findNum($url);
        //$this->assign('id', $id);
        $this->assign('info', ['paper_name'=>'']);
        $this->assign('id', '');
        return $this->fetch();
    }

    public function findNum($str=''){
        $str=trim($str);
        if(empty($str)){return '';}
        $result='';
        for($i=0;$i<strlen($str);$i++){
            if(is_numeric($str[$i])){
                $result.=$str[$i];
            }
        }
        return $result;
    }

    /**
     * 获取试卷
     * @return mixed
     */
    public function getPaper()
    {
        $get=input('get.');
        $where=[];
        $where['title_status'] = 1;
        if(!empty($get['p_id'])){
            $where['p_id'] = $get['p_id'];
        }

        $list =Db::table('estimate_title')
            ->where($where)
            ->order('title_number asc')
            ->paginate(10,false,['query'=>request()->param()])
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
                return $item;
            });
        if($list){
            $result = json_encode(['code'=>200,'data'=>$list]);
        }else{
            $result = json_encode(['code'=>201,'data'=>'no data']);
        }
        echo $result;exit;
    }

    /**
     * 同步试题的正确率
     * @return mixed
     */
    public function titleRate()
    {
        $list =Db::table('estimate_title')
            ->where('p_id',14)
            ->column('id,answer,correct_num','title_number');

        $where = [];
        $where['p_id'] = 14;
        $where['id'] = ['between',[5500,6000]];

        $user_score = Db::table('estimate_user_score')->where($where)->column('user_answer');

        foreach ($list as $k=>$v){
            $num=0;
            foreach ($user_score as $key=>$value){
                if(!empty($value)){
                    $all_answer = json_decode($value,true);
                    foreach ($all_answer as $m=>$n){
                        if($m == $k){
                            if($n == $v['answer']){
                                $num += 1;
                            }
                        }
                    }
                }
            }
            $titleData=array();
            $titleData['id']=$v['id'];
            $titleData['correct_num']=$num+$v['correct_num'];
            $result = db('title')->update($titleData);
        }
    }

    /**
     * 提交试卷
     * @return mixed
     */
    public function savePaper()
    {
        $user_id = $_COOKIE['user_id'];
        $post = input('post.');
        $all_answer = [];
        foreach ($post['paper'] as $key=>$v){
            if(preg_match('/[A-Za-z]+/',$v)){
                $op = substr($v,-1,1);
            }else {
                $op = '';
            }
            $num = $this->findNum($v);
            $all_answer[$num] = $op;
        }
        /*查询用户是否已经答过该试卷*/
        $where=[];
        if(!empty($post['paper_id'])){
            $where['p_id'] = $post['paper_id'];
        }
        if(!empty($user_id)){
            $where['user_id'] = $user_id;
        }
        $user = Db::table('estimate_user_score')->where($where)->select();
        if(!empty($user)){
            $result = ['code'=>211,'data'=>'您已经答过该试卷了！'];
            echo json_encode($result);exit;
        }
        $redis= new RedisPackage();
        $arr = $redis::ZRANGE("paper_".$post['paper_id'],1,200);
        //var_dump($data);exit;
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("title_".$value);
            $info[$title['title_number']] = $title;
        }
        $list = [];
        if(!empty($info)){
            $list = $info;
        }else{
            $list =Db::table('estimate_title')
                ->where('p_id',$post['paper_id'])
                ->order('title_number asc')
                ->column('id,answer,score','title_number');
            //var_dump($list);exit;
        }

        $score = 0;
        $correct_num = 0;
        $error_num = 0;
        $error_title = [];

        foreach ($all_answer as $k=>$value){
                if($value == $list[$k]['answer']){
                    $score += $list[$k]['score'];
                    $correct_num += 1;
                }else{
                    $error_num += 1;
                    $error_title[]=$k;
                }
        }

        $user = Db::table('estimate_user')->where('id',$user_id)->column('station_id,user_name','id');

        //保存
        $userData=array();
        $userData['p_id']=$post['paper_id'];
        $userData['user_id']=$user_id;
        $userData['user_name']=$user[$user_id]['user_name'];
        $userData['user_score']=$score;
        $userData['station_id']=$user[$user_id]['station_id'];
        $userData['correct_num']=$correct_num;
        $userData['error_num']=$error_num;
        $userData['user_answer']=!empty($all_answer) ? json_encode($all_answer) : [];
        $userData['error_title']=!empty($error_title) ? json_encode($error_title) : [];
        $userData['create_at']=time();
        $add=db('user_score')->insert($userData);
        if($add){
            $result = ['code'=>200,'data'=>'success'];
        }else{
            $result = ['code'=>210,'data'=>'提交失败'];
        }
        echo json_encode($result);exit;
    }

    /**
     * 判断是否答过试卷
     */
    public function isHave(){
        $post = input('post.');
        $where=[];
        if(!empty($post['user_id'])){
            $where['user_id'] = $post['user_id'];
        }
        $url = parse_url($_SERVER['HTTP_REFERER']);
        $p_id = basename($url['path'],'.html');
        if(!empty($p_id)){
            $where['p_id'] = $p_id;
        }
        $user = Db::table('estimate_user_score')->where($where)->select();
        if(!empty($user)){
            $result = json_encode(['code'=>200,'data'=>'success']);
        }else{
            $result = json_encode(['code'=>201,'data'=>'no data']);
        }
        echo $result;exit;
    }

    /**
     * 查看排名页面滚动数据缓存
     * @return mixed
     */
    public function rankRedis()
    {
        $p_id = 14;
        $list = $this->getTwoTen($p_id);
        //var_dump($list);
        $redis= new RedisPackage();
        if(!empty($list)){
            $m=1;
            foreach ($list as $k=>$v){
                $redis::zRem('rank_'.$v['p_id'],$m);
                $m++;
            }
            $i=1;
            foreach ($list as $k=>$v){
                $redis::hMset('user_score_'.$v['id'], $v);
                $redis::zAdd('rank_'.$p_id,$i,$v['id']);
                $i++;
            }
        }

        $arr = $redis::ZRANGE("rank_".$p_id,1,50);
        //var_dump($arr);exit;
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("user_score_".$value);
            $info[$title['id']] = $title;
        }
        var_dump($info);exit;

    }

    /**
     * 查看排名页面滚动数据缓存(脚本执行)
     * @return mixed
     */
    public function rankShell()
    {
        $redis= new RedisPackage();
        $paper = Db::table('estimate_paper')
            ->where('paper_status',1)
            ->column('id,paper_name');
        if(!empty($paper)){
            foreach ($paper as $key=>$value){
                $list = $this->getTwoTen($key);
                if(!empty($list)){
                    $m=1;
                    foreach ($list as $k=>$v){
                        $redis::zRem('rank_'.$v['p_id'],$m);
                        $m++;
                    }
                    $i=1;
                    foreach ($list as $k=>$v){
                        $redis::hMset('user_score_'.$v['id'], $v);
                        $redis::zAdd('rank_'.$v['p_id'],$i,$v['id']);
                        $i++;
                    }
                }
            }
        }
        Log::write(json_encode($paper));
        /*$arr = $redis::ZRANGE("rank_1",1,50);
        //var_dump($arr);exit;
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("user_score_".$value);
            $info[$title['id']] = $title;
        }
        var_dump($info);exit;*/
    }

    /**
     * 查看排名页面
     * @return mixed
     */
    public function rank()
    {
        if(empty($_COOKIE['user_id'])){
            header("Location: /estimate/index/login/index");
            exit;
        }else{
            $user_id = $_COOKIE['user_id'];
            if(empty($_COOKIE['paper'])){
                $paper_id = input('paper');
            }else{
                $paper_id = $_COOKIE['paper'];
            }
        }
        $user = Db::table('estimate_user')->where('id',$user_id)->column('station_id','id');

        $rank = Db::query(
            'select s1.user_name,s1.user_score,s1.correct_num,s1.error_num,s1.user_name,(select count(DISTINCT user_score) from `estimate_user_score` s2 where s2.user_score > s1.user_score and s2.station_id = '.$user[$user_id].' and s2.p_id = '.$paper_id.') + 1 as rank,
                       (select count(DISTINCT user_score) from `estimate_user_score` s2 where s2.user_score > s1.user_score and s2.p_id = '.$paper_id.') + 1 as c_rank  
                  from `estimate_user_score` s1 
                  WHERE user_id = '.$user_id.' and p_id = '.$paper_id
        );

        $all = Db::query(
            'select avg(user_score) as avg_score
                  from `estimate_user_score` s1 
                  WHERE p_id = '.$paper_id.' and user_score > 40'
        );
        $count = Db::query(
            'select count(*) as count
                  from `estimate_user_score` s1 
                  WHERE p_id = '.$paper_id
        );
        $all[0]['avg_score'] = round($all[0]['avg_score'],2);
        $all[0]['count'] = $count[0]['count'];

        $where = [];
        $where['station_id'] = $user[$user_id];
        $where['user_score'] = ['>',$rank[0]['user_score']];

        $pre = Db::table('estimate_user_score')
            ->where($where)
            ->order("user_score asc")
            ->limit(1)
            ->column('user_score');

        $where1 = [];
        $where1['station_id'] = $user[$user_id];
        $where1['user_score'] = ['<',$rank[0]['user_score']];
        $next = Db::table('estimate_user_score')
            ->where($where1)
            ->order("user_score desc")
            ->limit(1)
            ->column('user_score');

        if($pre){
            $pre_score = abs($rank[0]['user_score']-$pre[0]);
        }else{
            $pre_score = 0;
        }

        if($next){
            $next_score = abs($rank[0]['user_score']-$next[0]);
        }else{
            $next_score = 0;
        }
        $rank[0]['user_score'] = floatval($rank[0]['user_score']);

        //获取滚动数据
        $redis= new RedisPackage();
        $arr = $redis::ZRANGE('rank_'.$paper_id,1,50);
        $info = [];
        foreach ($arr as $value){
            $title = $redis::hGetAll("user_score_".$value);

            $info[$title['id']] = $title;
        }
        $arr = [];
        if(!empty($info)){
            $arr = $info;
        }else{
            $arr = $this->getTwoTen();
        }

        $this->assign('list', $arr);
        $this->assign('paper_id', $paper_id);
        $this->assign('pre', $pre_score);
        $this->assign('next', $next_score);
        $this->assign('rank', $rank[0]);
        $this->assign('all', $all[0]);
        return $this->fetch();
    }

    /**
     * 查看最近10条考试数据
     * @return mixed
     */
    public function getTwoTen($p_id)
    {
        if(!empty($p_id)){
            $list = Db::table('estimate_user_score')
                ->alias('us')
                ->join('estimate_user u','us.user_id = u.id','LEFT')
                ->where('us.p_id',$p_id)
                ->order("us.user_score desc")
                ->limit(50)
                ->column('us.id,us.p_id,us.user_id,us.user_score,us.create_at,u.user_name,u.city_name,u.station');
            //var_dump($list);exit;
            if($list){
                foreach ($list as $k=>$v){
                    $rank = Db::query(
                        'select (SELECT count(DISTINCT user_score) FROM `estimate_user_score` AS s2 WHERE s1.user_score < s2.user_score and s2.p_id = '.$v['p_id'].')+1 AS c_rank
                  from `estimate_user_score` s1 
                  WHERE user_id = '.$v['user_id'].' and p_id = '.$v['p_id']
                    );
                    //echo DB('estimate_user_score')->getlastsql();
                    //echo '</br>';
                    $list[$k]['user_score'] = floatval($v['user_score']);
                    $list[$k]['c_rank'] = $rank[0]['c_rank'];
                }
            }
        }else{
            $list = [];
        }

        //var_dump($list);exit;
        return $list;
    }

    /**
     * 查看解析页面
     * @return mixed
     */
    public function parsing()
    {
        if(empty($_COOKIE['user_id']) || empty($_COOKIE['paper'])){
            header("Location: /estimate/index/login/index");
            exit;
        }else{
            $user_id = $_COOKIE['user_id'];
            $paper_id = $_COOKIE['paper'];
        }
        $score = Db::table('estimate_user_score')
            ->where(['user_id'=>$user_id,'p_id'=>$paper_id])
            ->limit(1)
            ->column('id,p_id,user_answer,error_title','user_id');

        $where = [];
        if(!empty($score[$user_id]['p_id'])){
            $where['p_id'] = $score[$user_id]['p_id'];
        }
        $get = input('get.');
        if(!empty($get['type'])&&$get['type']==1){
            $error = json_decode($score[$user_id]['error_title'],true);
            $error_title = implode(',',$error);
            $where['title_number'] = ['in',$error_title];
        }
        $user_answer = json_decode($score[$user_id]['user_answer'],true);

        $paper = Db::table('estimate_paper')
            ->where('id',$score[$user_id]['p_id'])
            ->limit(1)
            ->column('paper_name');

        $list =Db::table('estimate_title')
            ->where($where)
            ->order('title_number asc')
            ->paginate(10,false,['query'=>request()->param()])
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
                }
                return $item;
            });

        $this->assign('list', $list);
        $this->assign('paper', $paper[0]);
        $this->assign('user_answer', $user_answer);

        return $this->fetch();
    }

    /**
     * 获取答案
     * @return mixed
     */
    public function getAnswer(){
        if(empty($_COOKIE['user_id']) || empty($_COOKIE['paper'])){
            echo json_encode(['code'=>201,'data'=>'no data']);
            exit;
        }else{
            $user_id = $_COOKIE['user_id'];
            $paper_id = $_COOKIE['paper'];
        }
        $count = Db::query(
            'select count(*) as count
                  from `estimate_user_score` s1 
                  WHERE p_id = '.$paper_id
        );

        $count = !empty($count) ? $count : 0;
        $score = Db::table('estimate_user_score')
            ->where(['user_id'=>$user_id,'p_id'=>$paper_id])
            ->limit(1)
            ->column('id,p_id,user_answer,error_title','user_id');
        if(!empty($score)){
            echo json_encode(['code'=>200,'data'=>json_decode($score[$user_id]['user_answer'],true),'num'=>$count[0]['count']]);
            exit;
        }else{
            echo json_encode(['code'=>202,'data'=>'no data','num'=>$count]);
            exit;
        }
    }

    /**
     * 获取岗位
     * @return mixed
     */
    public function getStation()
    {
        if(request()->isPost()) {
            $station =Db::table('estimate_station')
                ->where('c_id',input('c_id'))
                ->column('id,station_name,station_number','id');
            if($station){
                echo json_encode(['code'=>200,'data'=>$station]);
                exit;
            }else{
                echo json_encode(['code'=>201,'data'=>'没有岗位']);
                exit;
            }
        }
        echo json_encode(['code'=>202,'data'=>'没有数据']);exit;
    }



    /**
     * 生成静态页面
     * @return mixed
     */
    public function paperHtml(){
        $get=input('get.');
        $user =User::get(['id'=>$get['id']]);
        $this->assign('info', $user);
        //echo 123;exit;
        $this->buildHtml($get['id'],'public/html/',APP_PATH.'index/view/index/index.html');
    }


    /**
     * 清除缓存
     */
    public function clear() {
        if ($this->delete_dir_file(CACHE_PATH) || $this->delete_dir_file(TEMP_PATH)) {
            $this->success('清除缓存成功');
        } else {
            $this->error('清除缓存失败');
        }
    }

    /**
     * 循环删除目录和文件
     * @param string $dir_name
     * @return bool
     */
    public function delete_dir_file($dir_name) {
        $result = false;
        if(is_dir($dir_name)){
            if ($handle = opendir($dir_name)) {
                while (false !== ($item = readdir($handle))) {
                    if ($item != '.' && $item != '..') {
                        if (is_dir($dir_name . DS . $item)) {
                            delete_dir_file($dir_name . DS . $item);
                        } else {
                            unlink($dir_name . DS . $item);
                        }
                    }
                }
                closedir($handle);
                if (rmdir($dir_name)) {
                    $result = true;
                }
            }
        }

        return $result;
    }

}
