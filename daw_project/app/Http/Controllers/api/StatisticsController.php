<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function eventStatistics(Event $event)
    {
        return response()->json([
            'event' => $event->only(['id', 'title']),
            'submissions' => [
                'total' => $event->submissions()->count(),
                'pending' => $event->submissions()->where('status', 'pending')->count(),
                'under_review' => $event->submissions()->where('status', 'under_review')->count(),
                'accepted' => $event->submissions()->where('status', 'accepted')->count(),
                'rejected' => $event->submissions()->where('status', 'rejected')->count(),
                'by_type' => $event->submissions()
                                  ->select('type', DB::raw('count(*) as count'))
                                  ->groupBy('type')
                                  ->get(),
            ],
            'registrations' => [
                'total' => $event->registrations()->count(),
                'by_type' => $event->registrations()
                                  ->select('type', DB::raw('count(*) as count'))
                                  ->groupBy('type')
                                  ->get(),
                'payment_pending' => $event->registrations()->where('payment_status', 'pending')->count(),
                'payment_paid' => $event->registrations()->where('payment_status', 'paid')->count(),
            ],
            'participation' => [
                'sessions' => $event->sessions()->count(),
                'workshops' => $event->workshops()->count(),
                'questions' => $event->sessions()->withCount('questions')->get()->sum('questions_count'),
            ]
        ]);
    }

    public function submissionStats(Event $event)
    {
        $submissions = $event->submissions()->with('author:id,institution,country')->get();

        return response()->json([
            'by_institution' => $submissions->groupBy('author.institution')
                                           ->map(fn($items) => $items->count())
                                           ->sortDesc()
                                           ->take(10),
            'by_country' => $submissions->groupBy('author.country')
                                       ->map(fn($items) => $items->count())
                                       ->sortDesc(),
            'acceptance_rate' => [
                'total' => $submissions->count(),
                'accepted' => $submissions->where('status', 'accepted')->count(),
                'rate' => $submissions->count() > 0 
                    ? round(($submissions->where('status', 'accepted')->count() / $submissions->count()) * 100, 2)
                    : 0
            ]
        ]);
    }

    public function participantStats(Event $event)
    {
        $registrations = $event->registrations()->with('user:id,institution,country')->get();

        return response()->json([
            'total' => $registrations->count(),
            'by_institution' => $registrations->groupBy('user.institution')
                                            ->map(fn($items) => $items->count())
                                            ->sortDesc()
                                            ->take(10),
            'by_country' => $registrations->groupBy('user.country')
                                        ->map(fn($items) => $items->count())
                                        ->sortDesc(),
        ]);
    }

    public function dashboard()
    {
        $user = auth()->user();

        return response()->json($user->getDashboardData());
    }
}
