<?php
namespace App\Http\Controllers;

use App\Models\Registration;
use App\Models\Event;
use Illuminate\Http\Request;

class RegistrationController extends Controller
{
    // Get all registrations for an event (Organizer only)
    public function index($eventId)
    {
        $registrations = Registration::where('event_id', $eventId)
            ->with('user')
            ->get();

        return response()->json($registrations);
    }

    // Register for event
    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'profile_type' => 'required|in:participant,communicant,invited'
        ]);

        // Check if already registered
        $exists = Registration::where('event_id', $validated['event_id'])
            ->where('user_id', $request->user()->id)
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Already registered'], 400);
        }

        $registration = Registration::create([
            'event_id' => $validated['event_id'],
            'user_id' => $request->user()->id,
            'profile_type' => $validated['profile_type'],
            'payment_status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Registered successfully',
            'registration' => $registration
        ], 201);
    }

    // Update registration
    public function update(Request $request, $id)
    {
        $registration = Registration::findOrFail($id);

        // Check authorization
        if ($registration->user_id != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'profile_type' => 'sometimes|in:participant,communicant,invited'
        ]);

        $registration->update($validated);

        return response()->json([
            'message' => 'Registration updated successfully',
            'registration' => $registration
        ]);
    }

    // Cancel registration
    public function destroy($id)
    {
        $registration = Registration::findOrFail($id);

        // Check authorization
        if ($registration->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $registration->delete();

        return response()->json(['message' => 'Registration cancelled successfully']);
    }

    // Update payment status (Organizer only)
    public function updatePaymentStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_status' => 'required|in:pending,paid'
        ]);

        $registration = Registration::findOrFail($id);
        $registration->update(['payment_status' => $validated['payment_status']]);

        return response()->json([
            'message' => 'Payment status updated',
            'registration' => $registration
        ]);
    }

    // Generate badge (Organizer only)
    public function generateBadge($id)
    {
        $registration = Registration::with(['user', 'event'])->findOrFail($id);

        // TODO: Implement badge generation logic (PDF)

        $registration->update(['badge_generated' => true]);

        return response()->json([
            'message' => 'Badge generated successfully',
            'registration' => $registration
        ]);
    }

    // Get my registrations
    public function myRegistrations(Request $request)
    {
        $registrations = Registration::where('user_id', $request->user()->id)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($registrations);
    }
}