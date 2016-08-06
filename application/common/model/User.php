<?php
namespace app\common\model;

use \think\Response;

class User extends Base {
    
	public static function getList($page, $page_size) {
		
		$total = parent::where("is_admin = 1")->count();
		
		$page_count = page_count($total, $page_size);
		
		$offset = ($page - 1) * $page_size;
		$limit = $page_size;
		
		$data = parent::all(function($query) use($offset, $limit) {
			$query->where("is_admin = 1")->limit($offset, $limit)->order([ "id" => "desc" ]);
		});
		
		$list = [];
		foreach($data as $v) {
			$item = $v->toArray();
			$item["add_time"] = date("Y-m-d H:i:s", $item["add_time"]);
			$list[] = $item;
		}
		$r = [
			"page_size" => $page_size,
			"page_count" => $page_count,
			"total" => intval($total),
			"page" => $page,
			"list" => $list,
		];		
		return $r;
	}
	
	//管理员登录
	public static function adminLogin($name, $pwd, $remember = 0, $vcode = "") {

		$user = parent::where("name = '" . $name . "' and is_admin = 1")->find();
		if($user) {
			$user = $user->toArray();
			
			if($user["err_login"] >= 3) {
				
				if($vcode == "") {
					return Response::result(NULL, 1, "验证码不能为空", "json");
				}
				else {
					$verify = new \org\Verify();
					if(!$verify->check($vcode, 1)) {
						return Response::result(NULL, 1, "验证码错误", "json");
					}
				}
			}
			
			$arr = NULL;
			if($user["pwd"] == $pwd) {
				$user["err_login"] = 0;
				
				session("user", $user);
				
				$id = $user["id"];
				unset($user["id"]);
				parent::where("id = " . $id)->update($user);
				
				if($remember == 1) {
					$curr_user_name = \utility\Encrypt::encode($user["name"]);
					
					cookie('curr_user_name', $curr_user_name, ['expire' => 86400 * 7]); //保存7天
					//echo $curr_user_name;exit();
				}
				
				$arr = Response::result(NULL, 0, "登录成功", "json");
			}
			else {				
				$user["err_login"] += 1;
				$id = $user["id"];
				unset($user["id"]);
				parent::where("id = " . $id)->update($user);
				
				$arr = Response::result(NULL, 1, "密码错误", "json");
			}			
			return $arr;
		}
		else {
			$arr = Response::result(NULL, 1, "没有此用户", "json");
			return $arr;
		}
	}
	
	public static function delById($id = 0) {
		
		$user = session("user");
		if($user["id"] == $id) {
			return Response::result(NULL, 1, "不能删除自己", "json");
		}
		
		$result = parent::where("id = " . $id)->delete();
		if($result) {
			return Response::result(NULL, 0, "删除成功", "json");
		}
		
		return Response::result(NULL, 1, "删除失败", "json");
	}
	
	//退出登录
	public static function logout() {
	
		session("user", NULL);
		
		if(isset($_COOKIE["curr_user_name"]))
			cookie("curr_user_name", null);
			
		return Response::result(NULL, 0, "退出成功", "json");
	}
	
	public static function findByName($name, $other = []) {
		$where = [
			"name" => $name
		];		
		$where = array_merge($where, $other);
		$data = parent::where($where)->find();
		return $data->toArray();
	}
	
	public static function saveData($data, $id = 0) {
		
		if($id) {
			parent::where("id = " . $id)->update($data);
			return Response::result(NULL, 0, "更新成功", "json");
		}
		else {
			$total = parent::where("name = '" . $data["name"] . "'")->count();
			if($total > 0) {
				return Response::result(NULL, 1, "该用户已存在", "json");
			}
			
			$data["add_time"] = time();
			$data["is_admin"] = 1;
			
			parent::create($data);
			return Response::result(NULL, 0, "添加成功", "json");
		}
	}
	
	public static function updatePwd($old_pwd, $pwd, $pwd2) {
		$curr_user = session("user");
		
		$user = parent::where("name = '" . $curr_user["name"] . "' and pwd = '" . $old_pwd . "'")->find();		
		if($user) {
			$user = $user->toArray();
			$user["pwd"] = $pwd;
			$id = $user["id"];
			unset($user["id"]);
			parent::where("id = " . $id)->update($user);
			return Response::result(NULL, 0, "修改密码成功", "json");
		}
		else {
			return Response::result(NULL, 1, "旧密码不正确", "json");
		}
		
	}
	
}
