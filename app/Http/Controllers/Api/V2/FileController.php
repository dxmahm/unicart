<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Resources\V2\UploadedFileCollection;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Storage;
class FileController extends Controller
{
    public function index(){
        $all_uploads = (auth()->user()->user_type == 'seller') ? Upload::where('user_id',auth()->user()->id) : Upload::query();
        

        $all_uploads = $all_uploads->paginate(20)->appends(request()->query());

       return new UploadedFileCollection($all_uploads);

    }

    // any  base 64 image through uploader
    public function imageUpload(Request $request)
    {

        $type = array(
            "jpg" => "image",
            "jpeg" => "image",
            "png" => "image",
            "svg" => "image",
            "webp" => "image",
            "gif" => "image",
        );

        try {
            $image = $request->image;
            $originalFilename = basename($request->filename ?? 'image.jpg');
            $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

            if (!isset($type[$extension])) {
                return response()->json([
                    'result' => false,
                    'message' => translate("Only image can be uploaded"),
                    'path' => "",
                    'upload_id' => 0
                ]);
            }

            $realImage = base64_decode($image);
            if ($realImage === false) {
                return response()->json([
                    'result' => false,
                    'message' => translate("Invalid image data"),
                    'path' => "",
                    'upload_id' => 0
                ]);
            }

            $dir = public_path('uploads/all');
            if (!File::isDirectory($dir)) {
                File::makeDirectory($dir, 0755, true, true);
            }

            $newFileName = rand(10000000000, 9999999999) . date("YmdHis") . "." . $extension;
            $newFullPath = "$dir/$newFileName";

            $file_put = file_put_contents($newFullPath, $realImage);
            if ($file_put === false) {
                return response()->json([
                    'result' => false,
                    'message' => translate("Uploading error"),
                    'path' => "",
                    'upload_id' => 0
                ]);
            }

            $newPath = "uploads/all/$newFileName";

            if (env('FILESYSTEM_DRIVER') == 's3') {
                Storage::disk('s3')->put($newPath, file_get_contents(base_path('public/') . $newPath));
                unlink(base_path('public/') . $newPath);
            }

            $upload = new Upload;
            $upload->file_original_name = pathinfo($originalFilename, PATHINFO_FILENAME);
            $upload->extension = $extension;
            $upload->file_name = $newPath;
            $upload->user_id = auth()->user()->id;
            $upload->type = $type[$upload->extension];
            $upload->file_size = File::size($newFullPath);
            $upload->save();

            return response()->json([
                'result' => true,
                'message' => translate("Image updated"),
                'path' => uploaded_asset($upload->id),
                'upload_id' => $upload->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => "",
                'upload_id' => 0
            ]);
        }
    }
}
