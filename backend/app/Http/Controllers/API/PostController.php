<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Thread;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePost;
use App\Http\Resources\PostResource;
use App\Http\Resources\ThreadProfileResource;
use App\Http\Resources\ThreadBriefResource;
use App\Http\Resources\PaginateResource;
use App\Sosadfun\Traits\PostObjectTraits;


class PostController extends Controller
{
    use PostObjectTraits;
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */

    public function __construct()
    {
        $this->middleware('auth:api')->except('show');

    }

    /**
    * Store a newly created resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @return \Illuminate\Http\Response
    */
    public function store($id, StorePost $form)
    {
        $thread = Thread::on('mysql::write')->find($id);
        $post = $form->storePost($thread);
        $post = $this->postProfile($post->id);
        return response()->success(new PostResource($post));
    }

    /**
    * Display the specified resource.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function show($thread, $post)
    {
        $post = $this->postProfile($post);
        if(!$post){abort(404);}
        $thread = $this->findThread($thread);
        if(!$thread){abort(404);}
        if($thread->id!=$post->thread_id){abort(403);}

        return response()->success([
            'thread' => new ThreadBriefResource($thread),
            'post' => new PostResource($post),
        ]);
    }


    /**
    * Update the specified resource in storage.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function update($post, StorePost $form)
    {
        $post = Post::on('mysql::write')->find($post);
        $form->updatePost($post);
        $this->clearPost($post->id);
        $post = $this->postProfile($post->id);
        return response()->success(new PostResource($post));

    }

    /**
    * Remove the specified resource from storage.
    *
    * @param  int  $id
    * @return \Illuminate\Http\Response
    */
    public function destroy($post)
    {
        $post = Post::on('mysql::write')->find($post);
        if($post->user_id===auth('api')->id()){
            if($post->type==='post'||$post->type==='comment'){
                $post->delete();
            }else{
                // TODO
            }
        }
    }

    public function fold($post)
    {
        $post = Post::findOrFail($id);
        if(!$post){abort(404);}
        $thread=$post->thread;
        if(!$thread||!$post){abort(404);}
        if($thread->is_locked||$thread->user_id!=auth('api')->id()||auth('api')->user()->no_posting){abort(403);}

        if($post->fold_state>0){abort(409);}

        if($post->user->isAdmin()){abort(413);}

        $post->update(['fold_state'=>2]);

        return response()->success(new PostResource($post));
    }
}
