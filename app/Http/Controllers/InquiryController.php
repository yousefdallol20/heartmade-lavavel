<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InquiryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company' => [
                'required',
                'string',
                'max:255',
            ],

            'contact' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',
            ],

            'phone' => [
                'required',
                'string',
                'max:40',
            ],

            'quantity' => [
                'required',
                'string',
                Rule::in([
                    '20-50 items',
                    '50-100 items',
                    '100-300 items',
                    '300+ items',
                ]),
            ],

            'details' => [
                'required',
                'string',
                'max:10000',
            ],
        ]);

        $inquiry = DB::transaction(
            fn () => Inquiry::create($validated)
        );

        return response()->json([
            'success' => true,
            'message' => 'Your inquiry has been received successfully.',
            'reference' => 'INQUIRY-'
                .str_pad(
                    (string) $inquiry->id,
                    6,
                    '0',
                    STR_PAD_LEFT
                ),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $inquiries = Inquiry::query()
            ->orderByDesc('id')
            ->paginate(20);

        return response()->json($inquiries);
    }

    public function show(
        Request $request,
        Inquiry $inquiry
    ): JsonResponse {
        $this->authorizeAdmin($request);

        return response()->json([
            'data' => $inquiry,
        ]);
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(
            (bool) $request->user()?->is_admin,
            403,
            'Admin access is required.'
        );
    }
}
