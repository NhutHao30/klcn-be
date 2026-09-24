<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NewsController extends Controller
{
    private function underDevelopment()
    {
        return response()->json([
            'success' => false,
            'message' => 'Chức năng đang được phát triển',
            'data' => null
        ], 200);
    }

    public function index(Request $request)
    {
        return $this->underDevelopment();
    }

    public function show($id)
    {
        return $this->underDevelopment();
    }

    public function getComments($id)
    {
        return $this->underDevelopment();
    }

    public function postComment(Request $request, $id)
    {
        return $this->underDevelopment();
    }

    public function deleteComment($id)
    {
        return $this->underDevelopment();
    }

    public function adminIndex(Request $request)
    {
        return $this->underDevelopment();
    }

    public function store(Request $request)
    {
        return $this->underDevelopment();
    }

    public function update(Request $request, $id)
    {
        return $this->underDevelopment();
    }

    public function destroy($id)
    {
        return $this->underDevelopment();
    }
}
