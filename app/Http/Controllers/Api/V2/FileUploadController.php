<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Storage;

class FileUploadController extends Controller
{
    //

    public function  image_upload(Request $request)
    {
        $user = User::find(auth()->user()->id);
        if (!$user) {
            return response()->json([
                'result' => false,
                'message' => translate("User not found."),
                'path' => ""
            ]);
        }

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
                    'message' => "Only image can be uploaded",
                    'path' => ""
                ]);
            }

            $realImage = base64_decode($image);
            if ($realImage === false) {
                return response()->json([
                    'result' => false,
                    'message' => "Invalid image data",
                    'path' => ""
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
                    'message' => "Uploading error",
                    'path' => ""
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
            $upload->user_id = $user->id;
            $upload->type = $type[$upload->extension];
            $upload->file_size = File::size($newFullPath);
            $upload->save();

            $user->avatar_original = $upload->id;
            $user->save();



            return response()->json([
                'result' => true,
                'message' => translate("Image updated"),
                'path' => uploaded_asset($upload->id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'result' => false,
                'message' => $e->getMessage(),
                'path' => ""
            ]);
        }
    }
}
