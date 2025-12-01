<?php
namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Models\Event;
use App\Models\Registration;
use Illuminate\Http\Request;
use PDF; // Assuming you use a PDF library like barryvdh/laravel-dompdf

class CertificateController extends Controller
{
    // Generate certificate for user
    public function generate(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'type' => 'required|in:participant,communicant,committee,organizer'
        ]);

        $event = Event::findOrFail($validated['event_id']);
        $user = $request->user();

        // Check if certificate already exists
        $existing = Certificate::where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('type', $validated['type'])
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Certificate already generated',
                'certificate' => $existing
            ]);
        }

        // Generate PDF certificate
        $pdf = $this->generatePDF($user, $event, $validated['type']);
        
        $fileName = "certificate_{$user->id}_{$event->id}_{$validated['type']}.pdf";
        $filePath = "certificates/{$fileName}";
        
        // Save PDF
        \Storage::disk('public')->put($filePath, $pdf->output());

        // Create certificate record
        $certificate = Certificate::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'type' => $validated['type'],
            'file_path' => $filePath,
            'issued_at' => now()
        ]);

        return response()->json([
            'message' => 'Certificate generated successfully',
            'certificate' => $certificate
        ], 201);
    }

    // Get my certificates
    public function myCertificates(Request $request)
    {
        $certificates = Certificate::where('user_id', $request->user()->id)
            ->with('event')
            ->orderBy('issued_at', 'desc')
            ->get();

        return response()->json($certificates);
    }

    // Download certificate
    public function download($id)
    {
        $certificate = Certificate::findOrFail($id);

        // Check authorization
        if ($certificate->user_id != auth()->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $filePath = storage_path('app/public/' . $certificate->file_path);

        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Certificate file not found'], 404);
        }

        return response()->download($filePath);
    }

    // Generate certificates for all participants (Organizer only)
    public function generateForEvent(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);

        // Check authorization
        if ($event->created_by != $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $registrations = Registration::where('event_id', $eventId)->get();
        $generated = 0;

        foreach ($registrations as $registration) {
            // Skip if already generated
            $exists = Certificate::where('user_id', $registration->user_id)
                ->where('event_id', $eventId)
                ->where('type', $registration->profile_type)
                ->exists();

            if ($exists) continue;

            // Generate certificate
            $pdf = $this->generatePDF($registration->user, $event, $registration->profile_type);
            
            $fileName = "certificate_{$registration->user_id}_{$eventId}_{$registration->profile_type}.pdf";
            $filePath = "certificates/{$fileName}";
            
            \Storage::disk('public')->put($filePath, $pdf->output());

            Certificate::create([
                'user_id' => $registration->user_id,
                'event_id' => $eventId,
                'type' => $registration->profile_type,
                'file_path' => $filePath,
                'issued_at' => now()
            ]);

            $generated++;
        }

        return response()->json([
            'message' => "Generated {$generated} certificates",
            'count' => $generated
        ]);
    }

    // Helper: Generate PDF
    private function generatePDF($user, $event, $type)
    {
        // TODO: Implement actual PDF generation with template
        $data = [
            'user' => $user,
            'event' => $event,
            'type' => $type,
            'issued_at' => now()->format('Y-m-d')
        ];

        // return PDF::loadView('certificates.template', $data);
        
        // For now, return a placeholder
        return (object)['output' => function() { return 'PDF Content'; }];
    }
}
