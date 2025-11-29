<?php
namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Event;
use Illuminate\Http\Request;
use PDF; // barryvdh/laravel-dompdf

class CertificateController extends Controller
{
    public function myCertificates()
    {
        $certificates = Certificate::with('event:id,title,start_date')
                                   ->where('user_id', auth()->id())
                                   ->latest('generated_at')
                                   ->get();

        return response()->json($certificates);
    }

    public function generateForEvent(Request $request, Event $event)
    {
        $request->validate(['type' => 'required|in:participant,author,committee_member,organizer']);

        $users = [];

        if ($request->type === 'participant') {
            $users = $event->registrations()->pluck('user_id');
        } elseif ($request->type === 'author') {
            $users = $event->submissions()->pluck('user_id')->unique();
        } elseif ($request->type === 'committee_member') {
            $users = $event->committeeMembers()->pluck('users.id');
        } elseif ($request->type === 'organizer') {
            $users = [$event->organizer_id];
        }

        $generated = 0;
        foreach ($users as $userId) {
            Certificate::firstOrCreate([
                'user_id' => $userId,
                'event_id' => $event->id,
                'type' => $request->type,
            ], [
                'certificate_number' => 'CERT-' . strtoupper(uniqid()),
                'generated_at' => now(),
            ]);
            $generated++;
        }

        return response()->json(['message' => "{$generated} attestations générées"]);
    }

    public function generateSingle(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'event_id' => 'required|exists:events,id',
            'type' => 'required|in:participant,author,committee_member,organizer,speaker,workshop_leader'
        ]);

        $certificate = Certificate::create([
            'user_id' => $request->user_id,
            'event_id' => $request->event_id,
            'type' => $request->type,
            'certificate_number' => 'CERT-' . strtoupper(uniqid()),
            'generated_at' => now(),
        ]);

        return response()->json(['message' => 'Attestation générée', 'certificate' => $certificate]);
    }

    public function download(Certificate $certificate)
    {
        // TODO: Générer un vrai PDF avec DomPDF
        // Pour l'instant retourner les données
        
        $data = [
            'certificate_number' => $certificate->certificate_number,
            'user' => $certificate->user->name,
            'event' => $certificate->event->title,
            'type' => $certificate->type,
            'date' => $certificate->generated_at->format('d/m/Y'),
        ];

        return response()->json($data);
    }
}