<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PolicyDocument;
use App\Models\Signatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Who signs the investment agreements for the company. Super-admin only, password required, every change audited.
 *
 * A signature is the company's commitment, so: images live on the private disk and are never served to the public,
 * they are only embedded into PDFs; replacing one never alters a document already issued (each records which
 * signatory it used); and old signatories are kept, not deleted.
 */
class SignatoryController extends Controller
{
    private const MAX_WIDTH = 600;

    private const MAX_HEIGHT = 240;

    public function index(): View
    {
        return view('admin.signatory', [
            'signatories' => Signatory::with('uploader')->orderByDesc('id')->get(),
            'issuedWith' => PolicyDocument::selectRaw('signatory_id, count(*) as n')->groupBy('signatory_id')->pluck('n', 'signatory_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:150'],
            'signature' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:1024', 'dimensions:min_width=150,min_height=40,max_width=4000,max_height=2000'],
            'password' => ['required', 'current_password'],
        ], ['password.current_password' => 'Your password is incorrect.', 'signature.dimensions' => 'The signature image must be between 150x40 and 4000x2000 pixels.']);

        $png = $this->normalise($request->file('signature'));
        if ($png === null) {
            return back()->withErrors(['signature' => 'That image could not be read. Please upload a PNG or JPG of the signature.'])->withInput();
        }

        $signatory = DB::transaction(function () use ($data, $png, $request) {
            $path = 'signatures/'.Str::random(32).'.png';
            Storage::put($path, $png);
            Signatory::where('is_active', true)->update(['is_active' => false]);
            $signatory = Signatory::create(['name' => $data['name'], 'title' => $data['title'], 'signature_path' => $path, 'is_active' => true, 'uploaded_by' => $request->user()->id]);
            activity()->performedOn($signatory)->causedBy($request->user())->withProperties(['name' => $data['name'], 'title' => $data['title']])->log('Signatory set');

            return $signatory;
        });

        return back()->with('status', "{$signatory->name} is now the signatory. New agreements will carry this signature; ones already issued are unchanged.");
    }

    public function activate(Request $request, Signatory $signatory): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'Your password is incorrect.']);

        DB::transaction(function () use ($signatory, $request) {
            Signatory::where('is_active', true)->update(['is_active' => false]);
            $signatory->update(['is_active' => true]);
            activity()->performedOn($signatory)->causedBy($request->user())->log('Signatory reactivated');
        });

        return back()->with('status', "{$signatory->name} is the active signatory again.");
    }

    /** Shown only on this page, to the super-admin, never cached. */
    public function image(Signatory $signatory): Response
    {
        abort_unless(Storage::exists($signatory->signature_path), 404);

        return response(Storage::get($signatory->signature_path), 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, no-store']);
    }

    /** Re-encode as a PNG no larger than the page needs, keeping transparency; also drops anything hidden in the file. */
    private function normalise(UploadedFile $file): ?string
    {
        $info = @getimagesize($file->getRealPath());
        $source = match ($info[2] ?? null) {
            IMAGETYPE_PNG => @imagecreatefrompng($file->getRealPath()),
            IMAGETYPE_JPEG => @imagecreatefromjpeg($file->getRealPath()),
            default => false,
        };
        if (! $source) {
            return null;
        }

        [$w, $h] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::MAX_WIDTH / $w, self::MAX_HEIGHT / $h);
        [$nw, $nh] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
        imagecopyresampled($out, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagepng($out, null, 9);

        return (string) ob_get_clean();
    }
}
