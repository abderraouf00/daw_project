<?php
namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Submission;
use App\Models\Registration;
use App\Models\Message;
use App\Models\Notification;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    // User dashboard
    public function index(Request $request)
    {
        $user = $request->user();

        $dashboard = [
            // My events
            'my_events' => Event::where('created_by', $user->id)->count(),
            
            // My submissions
            'my_submissions' => Submission::where('user_id', $user->id)->count(),
            'pending_submissions' => Submission::where('user_id', $user->id)
                ->where('status', 'pending')
                ->count(),
            'accepted_submissions' => Submission::where('user_id', $user->id)
                ->where('status', 'accepted')
                ->count(),
            
            // My registrations
            'my_registrations' => Registration::where('user_id', $user->id)->count(),
            'upcoming_registered_events' => Registration::where('user_id', $user->id)
                ->whereHas('event', function($q) {
                    $q->where('status', 'upcoming')
                      ->where('start_date', '>', now());
                })
                ->count(),
            
            // Review assignments
            'my_review_assignments' => $user->reviewAssignments()->count(),
            'pending_reviews' => $user->reviewAssignments()
                ->where('status', 'pending')
                ->count(),
            
            // Committee memberships
            'committee_memberships' => $user->committees()->count(),
            
            // Messages
            'unread_messages' => Message::where('receiver_id', $user->id)
                ->where('is_read', false)
                ->count(),
            
            // Notifications
            'unread_notifications' => Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),
            
            // Recent activity
            'recent_submissions' => Submission::where('user_id', $user->id)
                ->with('event')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            
            'recent_registrations' => Registration::where('user_id', $user->id)
                ->with('event')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
            
            'upcoming_events' => Registration::where('user_id', $user->id)
                ->whereHas('event', function($q) {
                    $q->where('start_date', '>', now())
                      ->where('status', 'upcoming');
                })
                ->with('event')
                ->limit(5)
                ->get()
        ];

        return response()->json($dashboard);
    }

    // Organizer dashboard
    public function organizerDashboard(Request $request)
    {
        $user = $request->user();

        // Get events created by user
        $eventIds = Event::where('created_by', $user->id)->pluck('id');

        $dashboard = [
            'total_events' => $eventIds->count(),
            'upcoming_events' => Event::whereIn('id', $eventIds)
                ->where('status', 'upcoming')
                ->count(),
            'ongoing_events' => Event::whereIn('id', $eventIds)
                ->where('status', 'ongoing')
                ->count(),
            
            'total_submissions' => Submission::whereIn('event_id', $eventIds)->count(),
            'pending_submissions' => Submission::whereIn('event_id', $eventIds)
                ->where('status', 'pending')
                ->count(),
            
            'total_registrations' => Registration::whereIn('event_id', $eventIds)->count(),
            'pending_payments' => Registration::whereIn('event_id', $eventIds)
                ->where('payment_status', 'pending')
                ->count(),
            
            'my_events' => Event::where('created_by', $user->id)
                ->withCount(['submissions', 'registrations', 'sessions'])
                ->orderBy('start_date', 'desc')
                ->get(),
            
            'recent_submissions' => Submission::whereIn('event_id', $eventIds)
                ->with(['event', 'mainAuthor'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
            
            'recent_registrations' => Registration::whereIn('event_id', $eventIds)
                ->with(['event', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
        ];

        return response()->json($dashboard);
    }

    // Reviewer dashboard
    public function reviewerDashboard(Request $request)
    {
        $user = $request->user();

        $dashboard = [
            'total_assignments' => $user->reviewAssignments()->count(),
            'pending_reviews' => $user->reviewAssignments()
                ->where('status', 'pending')
                ->count(),
            'completed_reviews' => $user->reviewAssignments()
                ->where('status', 'completed')
                ->count(),
            
            'assignments' => $user->reviewAssignments()
                ->with(['submission.event', 'review'])
                ->orderBy('created_at', 'desc')
                ->get(),
            
            'pending_assignments' => $user->reviewAssignments()
                ->where('status', 'pending')
                ->with(['submission.event', 'submission.mainAuthor'])
                ->get()
        ];

        return response()->json($dashboard);
    }
}
                                          