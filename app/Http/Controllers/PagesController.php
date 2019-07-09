<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Helpers\PageObjects;
use App\Helpers\ConstantObjects;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Auth;
use Carbon\Carbon;

use App\Sosadfun\Traits\AdministrationTraits;

class PagesController extends Controller
{
    use AdministrationTraits;

    public function __construct()
    {
        $this->middleware('auth', [
            'only' => ['search', 'self_adminnistrationrecords'],
        ]);
    }

    public function home()
    {
        $quotes = PageObjects::quotes();
        $short_recom = PageObjects::short_recommendations();
        $thread_recom = PageObjects::thread_recommendation();
        $channels = ConstantObjects::allChannels();
        $channel_threads = [];
        foreach($channels as $channel){
            if($channel->is_public){
                $channel_threads[$channel->id] = [
                    'channel' => $channel,
                    'threads' => PageObjects::channel_threads($channel->id)
                ];
            }
        }
        return view('pages/home',compact('quotes','short_recom','thread_recom','channel_threads'));
    }
    public function about()
    {
        return view('pages/about');
    }

    public function help()
    {
        $users_online = PageObjects::users_online();
        $webstat = PageObjects::web_stat();
        return view('pages/help',compact('webstat','users_online'));
    }

    public function test()
    {
        return view('pages/test');
    }

    public function error($error_code)
    {
        $errors = array(
            "401" => "抱歉，您未登陆",
            "403" => "抱歉，由于设置，您无权限访问该页面",
            "404" => "抱歉，该页面不存在或已删除",
            "405" => "抱歉，数据库不支持本操作",//修改或增添
            "409" => "抱歉，数据冲突。",
        );
        $error_message = $errors[$error_code];
        return view('errors.errorpage', compact('error_message'));
    }
    public function administrationrecords(Request $request)
    {
        $page = is_numeric($request->page)? $request->page:'1';
        $records = Cache::remember('adminrecords-p'.$page, config('constants.online_count_interval'), function () use($page) {
            return $this->findAdminRecords(0, $page);
        });
        return view('pages.adminrecords',compact('records'))->with('record_page_set','total');
    }

    public function self_adminnistrationrecords(Request $request)
    {
        $page = is_numeric($request->page)? $request->page:'1';
        $records = $this->findAdminRecords(Auth::id(), $page);

        return view('pages.adminrecords',compact('records'))->with('record_page_set','self');
    }

    public function search(Request $request){
        $user = Auth::user();
        $cool_time = 1;
        if((!Auth::user()->admin)&&($user->lastsearched_at>Carbon::now()->subMinutes($cool_time)->toDateTimeString())){
            return redirect('/')->with('warning','1分钟内只能进行一次搜索');
        }else{
            $user->lastsearched_at=Carbon::now();
            $user->save();
        }
        $group = 10;
        if(Auth::check()){$group = Auth::user()->group;}
        if(($request->search)&&($request->search_options=='threads')){
            $query = $this->join_no_book_thread_tables()
            ->where([['threads.deleted_at', '=', null],['channels.channel_state','<',$group],['threads.public','=',1],['threads.title','like','%'.$request->search.'%']]);
            $simplethreads = $this->return_no_book_thread_fields($query)
            ->orderBy('threads.lastresponded_at', 'desc')
            ->simplePaginate(config('constants.index_per_page'))
            ->appends($request->only('page','search','search_options'));
            $show = ['channel' => false,'label' => false,];
            return view('pages.search_threads',compact('simplethreads','show'))->with('show_as_collections',0)->with('show_channel',1);
        }
        if(($request->search)&&($request->search_options=='users')){
            $users = User::where('name','like', '%'.$request->search.'%')->simplePaginate(config('constants.index_per_page'))
            ->appends($request->only('page','search','search_options'));
            return view('pages.search_users',compact('users'));
        }
        if($request->search_options=='tongren_yuanzhu'){
            $query = $this->join_book_tables()
            ->where([['threads.deleted_at', '=', null],['threads.public','=',1],['threads.channel_id','=',2]]);
            if ($request->search){
                $query->where('tongrens.tongren_yuanzhu','like','%'.$request->search.'%');
            }
            if ($request->tongren_cp){
                $query->where('tongrens.tongren_cp','like','%'.$request->tongren_cp.'%');
            }
            $books = $this->return_book_fields($query)
            ->orderBy('threads.lastresponded_at', 'desc')
            ->simplePaginate(config('constants.index_per_page'))
            ->appends($request->only('page','search','tongren_cp','search_options'));
            return view('pages.search_books', compact('books'))->with('show_as_collections', false);
        }
        return redirect('/')->with('warning','请输入搜索内容');
    }

    public function contacts()
    {
        return view('pages.contacts');
    }

    public function recommend_records(Request $request)
    {
        $page = is_numeric($request->page)? $request->page:'1';
        $short_reviews = Cache::remember('recommendation_indexes'.$page, 1, function () {
            $short_reviews = \App\Models\Post::join('reviews', 'posts.id', '=', 'reviews.post_id')
            ->reviewRecommend('recommend_only')
            ->reviewEditor('editor_only')
            ->reviewLong('short_only')
            ->reviewOrdered('latest_created')
            ->select('posts.*')
            ->paginate(config('preference.items_per_page'));
            $short_reviews->load('review.reviewee.author');
            return $short_reviews;
        });
        return view('reviews.index',compact('short_reviews'));
    }
}
