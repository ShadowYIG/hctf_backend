<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Team;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;
use APIReturn;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class TeamController extends Controller
{
    private $team;
    public function __construct(Team $team) {
        $this->team = $team;
    }

    /**
     * 登陆
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Aklis
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');
        $access_token = null;

        try {
            if (!$access_token = JWTAuth::attempt($credentials)) {
                return APIReturn::error("invalid_email_or_password", "Email 与密码不匹配", 401);
            }
        } catch (JWTAuthException $err) {
            return APIReturn::error("failed_to_create_token", "无法创建认证Token", 500);
        }

        return APIReturn::success(['access_token' => $access_token]);
    }

    /**
     * 注册
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Aklis
     */
    public function register(Request $request) {
        $input = $request->only('teamName', 'email', 'password');
        try {
            $team = $this->team->create([
                'team_name' => $input['teamName'],
                'email' => $input['email'],
                'password' => bcrypt($input['password']),
                'signUpTime' => Carbon::now('Asia/Shanghai'),
                'lastLoginTime' => Carbon::now('Asia/Shanghai'),
            ]);
        } catch (\Exception $err) {
            return APIReturn::error("email_or_team_already_exist", "队伍或Email已经存在", 500);
        }

        return APIReturn::success([
            'msg' => 'Welcome to HCTF 2017!',
        ]);
    }

    public function getAuthInfo(Request $request) {
        $team = JWTAuth::parseToken()->toUser();
        $team->lastLoginTime = Carbon::now('Asia/Shanghai');
        $team->save();
        return APIReturn::success($team);
    }



    /**
     * 列出所有队伍
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function listTeams(Request $request){
        $page = $request->input('page') ?? '1';
        try{
            $teams = Team::with('logs')->skip(($page - 1) * 20)->take(20)->get();
            $counts = Team::count();
            return APIReturn::success([
                "total" => $counts,
                "teams" => $teams
            ]);
        }
        catch (\Exception $e){
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }

    /**
     * 批量查询用户信息 * 公开方法
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function publicListTeams(Request $request){
        $validator = \Validator::make($request->only("teamId"), [
            'teamId' => 'required|array',
        ], [
           'teamId.required' => '缺少 teamId 字段',
           'teamId.array' => 'teamId 字段只能为数组'
        ]);

        if ($validator->fails()){
            return APIReturn::error('invalid_parameters', $validator->errors()->all(), 400);
        }

        try{
            $result = Team::with(["logs" => function($query){
                $query->where("status", "correct")->orderBy("created_at", "asc");
            }])->whereIn("team_id", $request->input('teamId'))->get();
            $result->makeHidden(['email', 'admin', 'banned', 'created_at', 'updated_at', 'lastLoginTime', 'signUpTime']);
            // 隐藏提交的 Flag 内容
            $result->each(function($team){
                $team->logs->each(function($log){
                    $log->makeHidden('flag');
                });
            });
            return APIReturn::success($result);
        }
        catch (\Exception $e){
            dd($e);
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }

    /**
     * 封禁队伍
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function banTeam(Request $request){
        $validator = \Validator::make($request->all(), [
            'teamId' => 'required|array'
        ], [
            'teamId.required' => '缺少队伍ID字段',
            'teamId.array' => '队伍ID必须为数组'
        ]);

        if ($validator->fails()){
            return APIReturn::error('invalid_parameters', $validator->errors()->all(), 400);
        }
        try{
            Team::where('team_id', $request->input('teamId'))->update([
                'banned' => true
            ]);
            return APIReturn::success();
        }
        catch (\Exception $e){
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }

    /**
     * 解除封禁
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function unbanTeam(Request $request){
        $validator = \Validator::make($request->all(), [
            'teamId' => 'required|array'
        ], [
            'teamId.required' => '缺少队伍ID字段',
            'teamId.array' => '队伍ID必须为数组'
        ]);

        if ($validator->fails()){
            return APIReturn::error('invalid_parameters', $validator->errors()->all(), 400);
        }
        try{
            Team::where('team_id', $request->input('teamId'))->update([
                'banned' => false
            ]);
            return APIReturn::success();
        }
        catch (\Exception $e){
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }

    /**
     * 设定为管理员
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function setAdmin(Request $request){
        $validator = \Validator::make($request->all(), [
            'teamId' => 'required|array'
        ], [
            'teamId.required' => '缺少队伍ID字段',
            'teamId.array' => '队伍ID必须为数组'
        ]);

        if ($validator->fails()){
            return APIReturn::error('invalid_parameters', $validator->errors()->all(), 400);
        }
        try{
            Team::where('team_id', $request->input('teamId'))->update([
                'admin' => true
            ]);
            return APIReturn::success();
        }
        catch (\Exception $e){
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }

    /**
     * 强制重设密码
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function forceResetPassword(Request $request){
        $validator = \Validator::make($request->all(), [
            'teamId' => 'required|integer'
        ], [
            'teamId.required' => '缺少队伍ID字段',
            'teamId.integer' => '队伍ID必须为整数'
        ]);

        if ($validator->fails()){
            return APIReturn::error('invalid_parameters', $validator->errors()->all(), 400);
        }

        try{
            $newPassword = str_random(32);
            $team = Team::find($request->input('teamId'));
            $team->password = bcrypt(hash('sha256', $newPassword));
            $team->save();
            return APIReturn::success([
                'newPassword' => $newPassword
            ]);
        }
        catch (\Exception $e){
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }

    /**
     * 获得排行
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     * @author Eridanus Sora <sora@sound.moe>
     */
    public function getRanking(Request $request){
        try{
            $result = Team::where('admin', '=', '0')->orderByScore()->take(20)->get();

            $result->makeHidden([
                'email', 'admin', 'banned', 'created_at', 'updated_at', 'lastLoginTime', 'signUpTime', 'flag', 'category_id', 'level_id', 'challenge_id', 'log_id', 'score'
            ]);
            return APIReturn::success($result);
        }
        catch (\Exception $e){
            dump($e);
            return APIReturn::error("database_error", "数据库读写错误", 500);
        }
    }
}