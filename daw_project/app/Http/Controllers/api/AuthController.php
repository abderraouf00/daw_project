<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:author,participant,committee_member,workshop_leader,invited_speaker',
            'institution' => 'nullable|string|max:255',
            'research_field' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:2',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // Créer l'utilisateur
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'institution' => $request->institution,
            'research_field' => $request->research_field,
            'bio' => $request->bio,
            'phone' => $request->phone,
            'country' => $request->country ?? 'DZ',
        ];

        // Upload de la photo de profil
        if ($request->hasFile('photo')) {
            $userData['photo'] = $request->file('photo')->store('profiles', 'public');
        }

        $user = User::create($userData);

        // Assigner le rôle
        $user->assignRole($request->role);

        // Créer le token
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Inscription réussie. Bienvenue !',
            'user' => $user->load('roles'),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Connexion d'un utilisateur
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'remember_me' => 'nullable|boolean',
        ]);

        $user = User::where('email', $request->email)->first();

        // Vérifier les identifiants
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants fournis sont incorrects.'],
            ]);
        }

        // Supprimer les anciens tokens (optionnel)
        if (!$request->remember_me) {
            $user->tokens()->delete();
        }

        // Créer un nouveau token
        $tokenName = $request->remember_me ? 'long_term_token' : 'auth_token';
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'message' => 'Connexion réussie',
            'user' => $user->load('roles'),
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * Déconnexion (supprimer le token actuel)
     */
    public function logout(Request $request)
    {
        // Supprimer le token actuel
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Déconnexion réussie'
        ]);
    }

    /**
     * Déconnexion de tous les appareils
     */
    public function logoutAll(Request $request)
    {
        // Supprimer tous les tokens de l'utilisateur
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Déconnexion de tous les appareils réussie'
        ]);
    }

    /**
     * Obtenir le profil de l'utilisateur connecté
     */
    public function profile(Request $request)
    {
        $user = $request->user()->load(['roles', 'permissions']);
        
        return response()->json([
            'user' => $user,
            'statistics' => $user->getStatistics(),
            'photo_url' => $user->photo_url,
            'role' => $user->role_name,
        ]);
    }

    /**
     * Mettre à jour le profil
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'institution' => 'nullable|string|max:255',
            'research_field' => 'nullable|string|max:255',
            'bio' => 'nullable|string|max:1000',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|size:2',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $data = $request->only([
            'name', 'institution', 'research_field', 
            'bio', 'phone', 'country'
        ]);

        // Gérer l'upload de la photo
        if ($request->hasFile('photo')) {
            // Supprimer l'ancienne photo
            if ($user->photo) {
                Storage::disk('public')->delete($user->photo);
            }

            // Stocker la nouvelle photo
            $data['photo'] = $request->file('photo')->store('profiles', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil mis à jour avec succès',
            'user' => $user->fresh()->load('roles')
        ]);
    }

    /**
     * Supprimer la photo de profil
     */
    public function deletePhoto(Request $request)
    {
        $user = $request->user();

        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
            $user->update(['photo' => null]);
        }

        return response()->json([
            'message' => 'Photo de profil supprimée'
        ]);
    }

    /**
     * Changer le mot de passe
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        // Vérifier l'ancien mot de passe
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Le mot de passe actuel est incorrect'
            ], 422);
        }

        // Mettre à jour le mot de passe
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Supprimer tous les tokens (forcer la reconnexion)
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Mot de passe modifié avec succès. Veuillez vous reconnecter.'
        ]);
    }

    /**
     * Demander un email de réinitialisation de mot de passe
     */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email'
        ]);

        // Envoyer l'email de réinitialisation
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Email de réinitialisation envoyé avec succès'
            ]);
        }

        return response()->json([
            'message' => 'Impossible d\'envoyer l\'email de réinitialisation'
        ], 500);
    }

    /**
     * Réinitialiser le mot de passe
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();

                // Supprimer tous les tokens
                $user->tokens()->delete();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Mot de passe réinitialisé avec succès'
            ]);
        }

        return response()->json([
            'message' => 'Le lien de réinitialisation est invalide ou expiré'
        ], 400);
    }

    /**
     * Obtenir le tableau de bord de l'utilisateur
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        
        $dashboardData = $user->getDashboardData();

        return response()->json($dashboardData);
    }

    /**
     * Mettre à jour l'adresse email
     */
    public function updateEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $request->user()->id,
            'password' => 'required',
        ]);

        $user = $request->user();

        // Vérifier le mot de passe
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Mot de passe incorrect'
            ], 422);
        }

        $user->update(['email' => $request->email]);

        return response()->json([
            'message' => 'Email mis à jour avec succès',
            'user' => $user
        ]);
    }

    /**
     * Vérifier si un email existe déjà
     */
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $exists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $exists,
            'available' => !$exists
        ]);
    }

    /**
     * Obtenir les tokens actifs de l'utilisateur
     */
    public function activeTokens(Request $request)
    {
        $tokens = $request->user()->tokens()
                         ->select('id', 'name', 'created_at', 'last_used_at')
                         ->get();

        return response()->json([
            'tokens' => $tokens,
            'total' => $tokens->count()
        ]);
    }

    /**
     * Révoquer un token spécifique
     */
    public function revokeToken(Request $request)
    {
        $request->validate([
            'token_id' => 'required|exists:personal_access_tokens,id'
        ]);

        $request->user()->tokens()
               ->where('id', $request->token_id)
               ->delete();

        return response()->json([
            'message' => 'Token révoqué avec succès'
        ]);
    }

    /**
     * Supprimer le compte utilisateur
     */
    public function deleteAccount(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'confirmation' => 'required|in:DELETE,delete'
        ]);

        $user = $request->user();

        // Vérifier le mot de passe
        if (!Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Mot de passe incorrect'
            ], 422);
        }

        // Supprimer la photo
        if ($user->photo) {
            Storage::disk('public')->delete($user->photo);
        }

        // Supprimer tous les tokens
        $user->tokens()->delete();

        // Soft delete
        $user->delete();

        return response()->json([
            'message' => 'Compte supprimé avec succès'
        ]);
    }
}