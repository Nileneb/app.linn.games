<?php

namespace App\Http\Controllers;

use App\Mail\ContactConfirmationMail;
use App\Mail\ContactInquiryMail;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'project_type' => 'required|string|max:100',
            'message' => 'required|string|max:5000',
            'timeline' => 'nullable|string|max:255',
        ]);

        $contact = Contact::create($validated);

        Mail::to('info@linn.games')->queue(new ContactInquiryMail($contact));
        Mail::to($contact->email)->queue(new ContactConfirmationMail($contact));

        return response()->json(['success' => true, 'message' => 'Anfrage erfolgreich gesendet.']);
    }
}
