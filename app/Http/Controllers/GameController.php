<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Score;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use illuminate\Support\Str;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 0);
        $size = $request->input('size', 10);
        $sortBy = $request->input('sortBy', 'title');
        $sortDir = $request->input('sortDir', 'asc');

        $allowedSortFields = ['title', 'popular', 'uploaddate'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'title';

        $query = Game::whereHas('versions');

        switch ($sortBy) {
            case 'title':
                $query->orderBy('title', $sortDir);
                break;
            case 'popular':
                $query->withCount('scores')
                      ->orderBy('scores_count', $sortDir);
                break;
            case 'uploaddate':
                $query->with('versions')
                      ->orderBy(
                          GameVersion::select('created_at')
                              ->whereColumn('game_id', 'games.id')
                              ->latest()
                              ->limit(1),
                          $sortDir
                      );
                break;
        }

        $games = $query->paginate($size, ['*'], 'page', $page);

        return response()->json([
            'page' => $games->currentPage() - 1,
            'size' => $games->perPage(),
            'totalElements' => $games->total(),
            'content' => $games->map(function ($game) {
                $latestVersion = $game->versions()->latest()->first();
                return [
                    'slug' => $game->slug,
                    'title' => $game->title,
                    'description' => $game->description,
                    'thumbnail' => $latestVersion ? $latestVersion->thumbnail : null,
                    'uploadTimestamp' => $latestVersion ? $latestVersion->created_at->toIso8601String() : null,
                    'author' => $game->username,
                    'scoreCount' => $game->scores()->count()
                ];
            })
        ]);
    }

    public function show($slug)
    {
        $game = Game::where('slug', $slug)->firstOrFail();
        $latestVersion = $game->versions()->latest()->first();

        return response()->json([
            'slug' => $game->slug,
            'title' => $game->title,
            'description' => $game->description,
            'thumbnail' => $latestVersion ? $latestVersion->thumbnail : null,
            'uploadTimestamp' => $latestVersion ? $latestVersion->created_at->toIso8601String() : null,
            'author' => $game->User->username,
            'scoreCount' => $game->scores()->count(),
            'gamePath' => $latestVersion ? "/games/{$game->slug}/{$latestVersion->version_number}/" : null
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|min:3|max:60',
            'description' => 'required|max:200'
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'violations' => $validator->errors()
            ], 400);
        }
    
        try {
            $slug = Str::slug($request->input('title'));
    
            $existingGame = Game::where('slug', $slug)->first();
            if ($existingGame) {
                return response()->json([
                    'status' => 'invalid',
                    'message' => 'Game title already exists with the same slug'
                ], 400);
            }
    
            $game = Game::create([
                'title' => $request->input('title'),
                'description' => $request->input('description'),
                'slug' => $slug,
                'created_by' => Auth::id() 
            ]);
    
            return response()->json([
                'status' => 'success',
                'slug' => $game->slug
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    

    public function update(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->firstOrFail();
    
        if ($game->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }
    
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|min:3|max:60',
            'description' => 'sometimes|max:200',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'violations' => $validator->errors()
            ], 400);
        }

        $game->update([
            'title' => $request->input('title', $game->title), 
            'description' => $request->input('description', $game->description), 
        ]);
    
        return response()->json([
            'status' => 'success',
            'message' => 'Game updated successfully',
            'data' => $game
        ]);
    }
    
    public function destroy($slug)
    {
        $game = Game::where('slug', $slug)->firstOrFail();

        if ($game->author_id !== Auth::id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        $game->delete();

        return response()->json(null, 204);
    }

    public function uploadVersion(Request $request, $slug)
    {
        $game = Game::where('slug', $slug)->firstOrFail();

        if ($game->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'forbidden',
                'message' => 'You are not the game author'
            ], 403);
        }

        if (!$request->hasFile('zipfile')) {
            return response()->json('No file uploaded', 400);
        }

        $file = $request->file('zipfile');
        
        $latestVersion = $game->versions()->latest()->first();
        $versionNumber = $latestVersion ? $latestVersion->version_number + 1 : 1;


        $path = $file->store("games/{$slug}/{$versionNumber}");

        $gameVersion = GameVersion::create([
            'game_id' => $game->id,
            'version' => $versionNumber,
            'storage_path' => $path,
            'thumbnail' => $request->hasFile('thumbnail') 
                ? $request->file('thumbnail')->store("games/{$slug}/{$versionNumber}") 
                : null
        ]);

        return response()->json('Game version uploaded successfully', 200);
    }

    public function getScores($slug)
{

    $game = Game::where('slug', $slug)->first();
        dd($game);
    $game_version = GameVersion::where('game_id', $game->id)->first();
    
        $scores = Score::where('game_version_id', $game_version->id)
            ->with(['user' => function($query) {
                $query->select('id', 'username');
            }])
            ->select('user_id', 'score', 'created_at as timestamp') 
            ->orderBy('score', 'desc')
            ->get();
        $response = [
            'username' => $game->username,
            'registeredTimestamp' => $game->created_at->toIso8601String(),
            'authoredGames' => [
                [
                    'slug' => $game->slug,
                    'title' => $game->title,
                    'description' => $game->description,
                ]
            ],
            'highscores' => $scores->map(function($score) {
                return [
                    'game' => [
                        'slug' => $score->game->slug,
                        'title' => $score->game->title,
                        'description' => $score->game->description,
                    ],
                    'score' => $score->score,
                    'timestamp' => $score->timestamp->toIso8601String(), 
                ];
            })
        ];
        return response()->json($response);
}

    
    public function postScore(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'score' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'invalid',
                'message' => 'Request body is not valid.',
                'violations' => $validator->errors()
            ], 400);
        }

        $game = Game::where('slug', $slug)->firstOrFail();
        $latestVersion = $game->versions()->latest()->first();

        Score::create([
            'game_id' => $game->id,
            'user_id' => Auth::user()->id,
            'score' => $request->input('score'),
            'game_version_id' => $latestVersion->id
        ]);

        return response()->json([
            'status' => 'success'
        ], 201);
    }
}


