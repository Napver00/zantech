<?php

namespace App\Http\Controllers\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Post;
use App\Models\User;

class PostController extends Controller
{
    //Create post
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'content'     => 'required',
            'category'    => 'nullable|string',
            'tags'        => 'nullable|array',
            'thumbnail'   => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
        ]);

        $data = $request->only([
            'title',
            'content',
            'category',
            'tags',
        ]);

        // slug
        $data['slug'] = Str::slug($request->title, '-');
        $data['author_id'] = auth()->id(); // logged in user id
        $data['status'] = 'draft';

        // Auto-generate meta fields
        $company = "Zantech Robotic Company in Bangladesh";
        $data['meta_title'] = $request->title . ' | ' . $company;
        $data['meta_description'] = "Learn about " . $request->title . " from " . $company . ".";

        // Save thumbnail
        if ($request->hasFile('thumbnail')) {
            $file = $request->file('thumbnail');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $filename = Str::slug($originalName, '_') . '_zantech_' . time() . '.' . $extension;
            $file->move(public_path('thumbnails'), $filename);

            $data['thumbnail'] = 'thumbnails/' . $filename;
        }

        $post = Post::create($data);

        return response()->json([
            'success' => true,
            'status'  => 200,
            'message' => 'Post created successfully.',
            'data'    => $post,
            'errors'  => null,
        ], 200);
    }


    // Show all posts with filters, search, pagination
    public function index(Request $request)
    {
        $query = Post::query();

        // search by title
        if ($request->has('title')) {
            $query->where('title', 'like', '%' . $request->title . '%');
        }

        // filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // filter by created_at date
        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        // pagination
        $perPage = $request->input('limit', 5);
        $posts = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // format thumbnail url
        $posts->getCollection()->transform(function ($post) {
            return [
                'id'             => $post->id,
                'title'          => $post->title,
                'slug'           => $post->slug,
                'thumbnail'      => $post->thumbnail ? url('public/' . $post->thumbnail) : null,
                'category'       => $post->category,
                'tags'           => $post->tags,
                'meta_title'     => $post->meta_title,
                'status'         => $post->status,
                'created_at'     => $post->created_at,
            ];
        });

        return response()->json($posts);
    }

    // Show single post with author name
    public function show($id)
    {
        $post = Post::find($id);

        if (!$post) {
            return response()->json(['success' => false, 'message' => 'Post not found'], 404);
        }

        $author = User::find($post->author_id);

        $post->author_name = $author ? $author->name : null;
        $post->thumbnail_url = $post->thumbnail ? url('public/' . $post->thumbnail) : null;

        return response()->json([
            'success' => true,
            'data'    => $post,
        ]);
    }

    // Update Post
    public function update(Request $request, $id)
    {
        $post = Post::findOrFail($id);

        $data = $request->only(['title', 'content', 'category', 'tags']);

        if ($request->hasFile('thumbnail')) {
            // delete old
            if ($post->thumbnail) {
                $fullPath = public_path($post->thumbnail);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            $file = $request->file('thumbnail');
            $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $extension = $file->getClientOriginalExtension();

            $filename = Str::slug($originalName, '_') . '_zantech_' . time() . '.' . $extension;
            $file->move(public_path('thumbnails'), $filename);

            $data['thumbnail'] = 'thumbnails/' . $filename;
        }

        $post->update($data);

        return response()->json([
            'success' => true,
            'data'    => $post,
        ]);
    }

    // Change Status (Draft <-> Published)
    public function toggleStatus($id)
    {
        $post = Post::findOrFail($id);

        $post->status = $post->status === 'draft' ? 'published' : 'draft';
        $post->save();

        return response()->json([
            'success' => true,
            'status'  => $post->status,
        ]);
    }

    // Delete Post + Thumbnail
    public function destroy($id)
    {
        $post = Post::findOrFail($id);

        if ($post->thumbnail) {
            $fullPath = public_path($post->thumbnail);
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        $post->delete();

        return response()->json([
            'success' => true,
            'message' => 'Post deleted successfully',
        ]);
    }
}
