<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\Submission;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    // Global search
    public function search(Request $request)
    {
        $query = $request->input('q');

        if (empty($query)) {
            return response()->json([
                'message' => 'Search query is required'
            ], 400);
        }

        $results = [
            'events' => Event::where('title', 'like', "%{$query}%")
                ->orWhere('description', 'like', "%{$query}%")
                ->orWhere('theme', 'like', "%{$query}%")
                ->limit(10)
                ->get(),
            
            'users' => User::where('name', 'like', "%{$query}%")
                ->orWhere('institution', 'like', "%{$query}%")
                ->limit(10)
                ->get(),
            
            'submissions' => Submission::where('title', 'like', "%{$query}%")
                ->orWhere('abstract', 'like', "%{$query}%")
                ->orWhere('keywords', 'like', "%{$query}%")
                ->with('event')
                ->limit(10)
                ->get()
        ];

        return response()->json($results);
    }

    // Search events
    public function searchEvents(Request $request)
    {
        $query = $request->input('q');
        $filters = $request->only(['status', 'from_date', 'to_date']);

        $events = Event::query();

        if (!empty($query)) {
            $events->where(function($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                  ->orWhere('description', 'like', "%{$query}%")
                  ->orWhere('theme', 'like', "%{$query}%");
            });
        }

        if (!empty($filters['status'])) {
            $events->where('status', $filters['status']);
        }

        if (!empty($filters['from_date'])) {
            $events->where('start_date', '>=', $filters['from_date']);
        }

        if (!empty($filters['to_date'])) {
            $events->where('end_date', '<=', $filters['to_date']);
        }

        return response()->json($events->paginate(20));
    }

    // Search users
    public function searchUsers(Request $request)
    {
        $query = $request->input('q');

        $users = User::where('name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('institution', 'like', "%{$query}%")
            ->orWhere('research_domain', 'like', "%{$query}%")
            ->with('roles')
            ->paginate(20);

        return response()->json($users);
    }
}