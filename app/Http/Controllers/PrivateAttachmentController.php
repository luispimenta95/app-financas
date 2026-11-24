<?php

namespace App\Http\Controllers;

use App\Support\PrivateAttachments;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PrivateAttachmentController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $relative = PrivateAttachments::normalize($path);
        abort_unless($relative !== null, 404);
        abort_unless(PrivateAttachments::userOwns($user, $relative), 404);

        $absolute = PrivateAttachments::absolutePath($relative);
        abort_unless($absolute !== null, 404);

        return response()->file($absolute);
    }
}
