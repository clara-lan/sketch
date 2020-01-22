<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Thread;
use App\Models\Post;
use App\Http\Resources\ThreadProfileResource;
use App\Http\Resources\ThreadInfoResource;
use App\Http\Resources\PostInfoResource;
use App\Http\Resources\PaginateResource;
use App\Http\Resources\PostBriefResource;
use App\Sosadfun\Traits\ThreadQueryTraits;
use App\Sosadfun\Traits\ThreadObjectTraits;


class BookController extends Controller
{
    use ThreadQueryTraits;
    use ThreadObjectTraits;
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */

    public function __construct()
    {

    }

    public function show($id)
    {   $book = DB::table('books')->where('id','=',$id)->first();
        if($book){
            return response()->error([
                'book_id' => $book->id,
                'thread_id' => $book->thread_id,
                'url' => route('thread.show', $book->thread_id),
            ], 301);
        }else{
            abort(404);
        }
    }

    public function index(Request $request)
    {
        $request_data = $this->sanitize_book_request_data($request);

        if($request_data&&!auth('api')->check()){abort(401);}

        $query_id = $this->process_thread_query_id($request_data);

        $books = $this->find_books_with_query($query_id, $request_data);

        return response()->success([
            'threads' => ThreadInfoResource::collection($books),
            'paginate' => new PaginateResource($books),
            'request_data' => $request_data,
        ]);
    }

    public function store()
    {
        // TODO 这个函数是否保留，待讨论
    }

    public function update_tongren($id, Request $request)
    {
        $thread = Thread::on('mysql::write')->find($id);
        $user = auth('api')->user();
        if(!$thread||$thread->user_id!=$user->id||($thread->is_locked&&!$user->isAdmin())||$thread->channel_id<>2){abort(403);}

        $thread->tongren_data_sync($request->all());
        $this->clearThread($id);
        $thread = $this->threadProfile($id);

        return response()->success([
            'thread' => new ThreadProfileResource($thread),
        ]);
    }
}
