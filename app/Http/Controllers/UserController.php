<?php

namespace App\Http\Controllers;

use Illuminate\Support\Carbon;
use App\Models\Administrator;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{

    private function isAdministrator()
    {
        $admin = User::where('id', Auth::id())->first();
        return $admin !== null;
    }

     /**
     * Display a listing of the resource.
     */
    public function index($name)
    {
        if (!$this->isAdministrator()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }
    
        $user = User::where('username', $name)->first();
    
        if (!$user) {
            return response()->json([
                'status' => 'not found',
                'message' => 'User not found'
            ], 404);
        }
    
        return response()->json([
            'username' => $user->username,
            'created_at' => $user->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $user->updated_at->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$this->isAdministrator()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'required|unique:users|min:4|max:60',
            'password' => 'required|min:5|max:20'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('username')) {
                return response()->json([
                    'status' => 'invalid',
                    'message' => 'Username already exists'
                ], 400);
            }

            return response()->json([
                'status' => 'invalid',
                'violations' => $this->formatValidationErrors($validator->errors())
            ], 400);
        }

        $user = User::create([
            'username' => $request->username,
            'password' => bcrypt($request->password)
        ]);

        return response()->json([
            'status' => 'success',
            'username' => $user->username
        ], 201);
    }
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!$this->isAdministrator()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the administrator'
            ], 403);
        }

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'username' => 'unique:users,username,' . $user->id . '|min:4|max:60',
            'password' => 'min:5|max:20'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();

            if ($errors->has('username')) {
                return response()->json([
                    'status' => 'invalid',
                    'message' => 'Username already exists'
                ], 400);
            }

            return response()->json([
                'status' => 'invalid',
                'violations' => $this->formatValidationErrors($validator->errors())
            ], 400);
        }

        $user->username = $request->username;
        $user->password = bcrypt($request->password);
        $user->save();

        return response()->json([
            'status' => 'success',
            'username' => $user->username
        ], 201);
    }

    /**
     * Delete a user (admin only)
     */
    public function destroy($id)
     {
        $user = User::find($id); 

         if (!$user) {
             return response()->json([
               'status' => 'not-found',
                  'message' => 'User Not found'
                ], 404);
            }
                
        try {
              $user->delete();
                 return response()->json(null, 204); 
                } catch (\Exception $e) {
                return response()->json([
                     'status' => 'error',
                      'message' => 'Something went wrong'
                    ], 500);
                }
                
     }

    private function formatValidationErrors($errors)
    {
        $formattedErrors = [];
        foreach ($errors->all() as $error) {
            $field = $errors->keys()[0];
            $formattedErrors[$field] = ['message' => $error];
        }
        return $formattedErrors;
    }
}
