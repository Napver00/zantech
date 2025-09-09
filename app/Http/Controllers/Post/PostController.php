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
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while creating the post.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // Show all posts with filters, search, pagination
    public function index(Request $request)
    {
        try {
            $query = Post::query();

            // Search by title
            if ($request->has('title')) {
                $query->where('title', 'like', '%' . $request->title . '%');
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by created_at date
            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }

            // Pagination
            $perPage = $request->input('limit', 5);
            $posts = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Format thumbnail url
            $posts->getCollection()->transform(function ($post) {
                return [
                    'id'         => $post->id,
                    'title'      => $post->title,
                    'slug'       => $post->slug,
                    'thumbnail'  => $post->thumbnail ? url('public/' . $post->thumbnail) : null,
                    'category'   => $post->category,
                    'tags'       => $post->tags,
                    'meta_title' => $post->meta_title,
                    'status'     => $post->status,
                    'created_at' => $post->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Posts retrieved successfully.',
                'data'    => $posts,
                'errors'  => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while retrieving posts.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }

    public function indexPublished(Request $request)
    {
        try {
            $query = Post::where('status', 'published'); // only published posts

            // Search by title
            if ($request->has('title')) {
                $query->where('title', 'like', '%' . $request->title . '%');
            }

            // Filter by category
            if ($request->has('category')) {
                $query->where('category', $request->category);
            }

            // Filter by created_at date
            if ($request->has('date')) {
                $query->whereDate('created_at', $request->date);
            }

            // Pagination
            $perPage = $request->input('limit', 5);
            $posts = $query->orderBy('created_at', 'desc')->paginate($perPage);

            // Format thumbnail URL
            $posts->getCollection()->transform(function ($post) {
                return [
                    'id'         => $post->id,
                    'title'      => $post->title,
                    'slug'       => $post->slug,
                    'thumbnail'  => $post->thumbnail ? url('public/' . $post->thumbnail) : null,
                    'category'   => $post->category,
                    'tags'       => $post->tags,
                    'meta_title' => $post->meta_title,
                    'status'     => $post->status,
                    'created_at' => $post->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Published posts retrieved successfully.',
                'data'    => $posts,
                'errors'  => null,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while retrieving published posts.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // Show single post with author name
    public function show($id)
    {
        try {
            $post = Post::findOrFail($id);

            $author = User::find($post->author_id);

            $post->author_name = $author ? $author->name : null;
            $post->thumbnail_url = $post->thumbnail ? url('public/' . $post->thumbnail) : null;

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Post retrieved successfully.',
                'data'    => $post,
                'errors'  => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Post not found.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while retrieving the post.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // Update Post
    public function update(Request $request, $id)
    {
        try {
            $post = Post::findOrFail($id);

            $request->validate([
                'title'        => 'nullable|string|max:255',
                'content'      => 'nullable',
                'tags'         => 'nullable|array',
                'thumbnail'    => 'nullable|file|mimes:jpg,jpeg,png,gif,webp|max:2048',
                'meta_title'   => 'nullable|string|max:255',
                'meta_description' => 'nullable|string|max:300',
            ]);

            $data = $request->only(['title', 'content', 'tags']);

            // Auto-generate meta fields if not provided
            $company = "Zantech Robotic Company in Bangladesh";
            if ($request->filled('meta_title')) {
                $data['meta_title'] = $request->meta_title;
            } elseif ($request->filled('title')) {
                $data['meta_title'] = $request->title . ' | ' . $company;
            }

            if ($request->filled('meta_description')) {
                $data['meta_description'] = $request->meta_description;
            } elseif ($request->filled('title')) {
                $data['meta_description'] = "Learn about " . $request->title . " from " . $company . ".";
            }

            // Handle thumbnail update
            if ($request->hasFile('thumbnail')) {
                // delete old thumbnail if exists
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
                'status'  => 200,
                'message' => 'Post updated successfully.',
                'data'    => $post,
                'errors'  => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Post not found.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while updating the post.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }



    // Change Status (Draft <-> Published)
    public function toggleStatus($id)
    {
        try {
            $post = Post::findOrFail($id);

            $post->status = $post->status === 'draft' ? 'published' : 'draft';
            $post->save();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Post status updated successfully.',
                'data'    => ['id' => $post->id, 'status' => $post->status],
                'errors'  => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Post not found.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while updating the status.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }


    // Delete Post + Thumbnail
    public function destroy($id)
    {
        try {
            $post = Post::findOrFail($id);

            // Delete thumbnail if exists
            if ($post->thumbnail) {
                $fullPath = public_path($post->thumbnail);
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }

            $post->delete();

            return response()->json([
                'success' => true,
                'status'  => 200,
                'message' => 'Post deleted successfully.',
                'data'    => ['id' => $id],
                'errors'  => null,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'status'  => 404,
                'message' => 'Post not found.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 500,
                'message' => 'Something went wrong while deleting the post.',
                'data'    => null,
                'errors'  => $e->getMessage(),
            ], 500);
        }
    }
}
