<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Submission;
use App\Models\Registration;
use App\Models\Statistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticController extends Controller
{
    // Generate statistics for an event
    public function generate($eventId)
    {
        $event = Event::findOrFail($eventId);

        // Total submissions
        $totalSubmissions = Submission::where('event_id', $eventId)->count();
        
        // Accepted/Rejected submissions
        $acceptedSubmissions = Submission::where('event_id', $eventId)
            ->where('status', 'accepted')
            ->count();
        
        $rejectedSubmissions = Submission::where('event_id', $eventId)
            ->where('status', 'rejected')
            ->count();
        
        // Acceptance rate
        $acceptanceRate = $totalSubmissions > 0 
            ? ($acceptedSubmissions / $totalSubmissions) * 100 
            : 0;

        // Total participants
        $totalParticipants = Registration::where('event_id', $eventId)->count();

        // Participants by country
        $participantsByCountry = Registration::where('event_id', $eventId)
            ->join('users', 'registrations.user_id', '=', 'users.id')
            ->select('users.country', DB::raw('count(*) as count'))
            ->whereNotNull('users.country')
            ->groupBy('users.country')
            ->get();

        $totalCountries = $participantsByCountry->count();

        // Submissions by institution
        $submissionsByInstitution = Submission::where('event_id', $eventId)
            ->join('users', 'submissions.user_id', '=', 'users.id')
            ->select('users.institution', DB::raw('count(*) as count'))
            ->whereNotNull('users.institution')
            ->groupBy('users.institution')
            ->orderBy('count', 'desc')
            ->get();

        // Submissions by type
        $submissionsByType = Submission::where('event_id', $eventId)
            ->select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->get();

        // Additional statistics
        $data = [
            'participants_by_country' => $participantsByCountry,
            'submissions_by_institution' => $submissionsByInstitution,
            'submissions_by_type' => $submissionsByType,
            'participants_by_profile' => Registration::where('event_id', $eventId)
                ->select('profile_type', DB::raw('count(*) as count'))
                ->groupBy('profile_type')
                ->get(),
            'payment_status' => Registration::where('event_id', $eventId)
                ->select('payment_status', DB::raw('count(*) as count'))
                ->groupBy('payment_status')
                ->get()
        ];

        // Save or update statistics
        $statistic = Statistic::updateOrCreate(
            ['event_id' => $eventId],
            [
                'total_submissions' => $totalSubmissions,
                'accepted_submissions' => $acceptedSubmissions,
                'rejected_submissions' => $rejectedSubmissions,
                'total_participants' => $totalParticipants,
                'total_countries' => $totalCountries,
                'acceptance_rate' => round($acceptanceRate, 2),
                'data' => $data
            ]
        );

        return response()->json([
            'message' => 'Statistics generated successfully',
            'statistics' => $statistic
        ]);
    }

    // Get statistics for an event
    public function show($eventId)
    {
        $statistic = Statistic::where('event_id', $eventId)->first();

        if (!$statistic) {
            // Generate if not exists
            return $this->generate($eventId);
        }

        return response()->json($statistic);
    }

    // Get dashboard statistics (Super Admin)
    public function dashboard()
    {
        $stats = [
            'total_events' => Event::count(),
            'upcoming_events' => Event::where('status', 'upcoming')->count(),
            'ongoing_events' => Event::where('status', 'ongoing')->count(),
            'completed_events' => Event::where('status', 'completed')->count(),
            'total_users' => \App\Models\User::count(),
            'total_submissions' => Submission::count(),
            'pending_submissions' => Submission::where('status', 'pending')->count(),
            'accepted_submissions' => Submission::where('status', 'accepted')->count(),
            'total_registrations' => Registration::count(),
            
            // Recent events
            'recent_events' => Event::orderBy('created_at', 'desc')->limit(5)->get(),
            
            // Events by month (last 12 months)
            'events_by_month' => Event::select(
                DB::raw('DATE_FORMAT(start_date, "%Y-%m") as month'),
                DB::raw('count(*) as count')
            )
                ->where('start_date', '>=', now()->subYear())
                ->groupBy('month')
                ->orderBy('month')
                ->get(),
            
            // Top institutions by submissions
            'top_institutions' => Submission::join('users', 'submissions.user_id', '=', 'users.id')
                ->select('users.institution', DB::raw('count(*) as count'))
                ->whereNotNull('users.institution')
                ->groupBy('users.institution')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json($stats);
    }

    // Get statistics summary for all events
    public function summary()
    {
        $statistics = Statistic::with('event')->get();

        $summary = [
            'total_events_analyzed' => $statistics->count(),
            'total_submissions_all_events' => $statistics->sum('total_submissions'),
            'total_participants_all_events' => $statistics->sum('total_participants'),
            'average_acceptance_rate' => $statistics->avg('acceptance_rate'),
            'total_countries_reached' => $statistics->sum('total_countries'),
            'by_event' => $statistics
        ];

        return response()->json($summary);
    }
}